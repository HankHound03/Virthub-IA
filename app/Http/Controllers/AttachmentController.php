<?php

namespace App\Http\Controllers;

use App\Services\AvisoStore;
use App\Services\ChunkedUploadService;
use App\Services\ModerationService;
use App\Support\ActiveUser;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Subidas por trozos para adjuntos grandes.
 *
 * Se usan cuando el archivo supera lo que una sola peticion puede manejar con
 * comodidad. El cliente pide una sesion, envia el contenido en pedazos de 8 MB
 * y finalmente pide el ensamblado.
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly ChunkedUploadService $uploads,
        private readonly SecurityAudit $audit,
        private readonly ModerationService $moderation,
        private readonly AvisoStore $avisos,
    ) {}

    /**
     * Abre la sesion de subida y devuelve como trocear el archivo.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'size' => 'required|integer|min:1',
            'mime' => 'nullable|string|max:150',
        ]);

        try {
            $session = $this->uploads->initiate(
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['filename'],
                (int) $validated['size'],
                (string) ($validated['mime'] ?? '')
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($session, 201);
    }

    /**
     * Recibe un trozo. El cuerpo de la peticion es el contenido binario en crudo,
     * no un formulario: asi el trozo no se materializa como objeto de subida.
     */
    public function chunk(Request $request, string $uploadId, int $index): JsonResponse
    {
        $content = $request->getContent();

        if ($content === '' || $content === false) {
            return response()->json(['error' => 'El trozo llego vacio.'], 422);
        }

        try {
            $result = $this->uploads->appendChunk(
                $uploadId,
                ActiveUser::username(ActiveUser::from($request)),
                $index,
                $content
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Ensambla los trozos y devuelve el adjunto listo para asociar al post.
     */
    public function complete(Request $request, string $uploadId): JsonResponse
    {
        try {
            $attachment = $this->uploads->complete(
                $uploadId,
                ActiveUser::username(ActiveUser::from($request))
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        /*
         * Revision de la imagen antes de darla por buena.
         *
         * Se hace aqui, al terminar la subida, y no al publicar: asi la foto no
         * llega nunca a estar visible ni unos segundos. Solo se revisan imagenes;
         * videos y documentos siguen su curso.
         */
        $username = ActiveUser::username(ActiveUser::from($request));
        $revision = $this->revisarSiEsImagen($attachment, $username);

        if ($revision !== null) {
            return $revision;
        }

        $this->audit->log($request, 'attachment.uploaded', ActiveUser::from($request), [
            'upload_id' => $uploadId,
            'path' => $attachment['path'],
            'size' => $attachment['size'],
        ]);

        // Se devuelve el id como recibo: el cliente lo presenta al publicar y el
        // servidor resuelve la ruta real, nunca al reves.
        return response()->json([
            'upload_id' => $uploadId,
            'attachment' => $attachment,
        ], 201);
    }

    /**
     * Pasa la imagen por la moderacion y, si no es apta, la retira.
     *
     * Devuelve la respuesta de error si la foto se retiro, o null si puede seguir
     * adelante (apta, moderacion apagada o servicio no disponible).
     *
     * @param array{type: string, name: string, mime: string, path: string, size: int} $attachment
     */
    private function revisarSiEsImagen(array $attachment, string $username): ?JsonResponse
    {
        // El servicio marca las imagenes como 'photo' (no 'image').
        if (! in_array((string) ($attachment['type'] ?? ''), ['photo', 'image'], true)) {
            return null;
        }

        if (! $this->moderation->habilitado()) {
            return null;
        }

        $ruta = storage_path('app/public/' . $attachment['path']);

        $resultado = $this->moderation->revisarImagen($ruta, (string) ($attachment['name'] ?? 'imagen'));

        if (! $resultado['prohibida']) {
            return null;
        }

        // Fuera la foto: ni se publica ni se queda ocupando disco.
        @unlink($ruta);

        $nombre = (string) ($attachment['name'] ?? 'una foto');
        $motivo = (string) ($resultado['motivo'] ?? 'Contenido no permitido.');

        $this->avisos->crear(
            $username,
            'Foto retirada del foro',
            'La imagen "' . $nombre . '" se retiro del foro porque se detecto contenido no permitido. '
                . 'Si crees que es un error, responde a este aviso o escribe al administrador.',
            'moderacion',
            [
                'archivo' => $nombre,
                'motivo' => $motivo,
                'puntuacion' => $resultado['puntuacion'] ?? 0,
            ]
        );

        $this->avisos->crear(
            'admin',
            'Foto retirada automaticamente',
            $username . ' intento subir "' . $nombre . '" y la moderacion la retiro por contenido no permitido '
                . '(puntuacion ' . ($resultado['puntuacion'] ?? 0) . ').',
            'moderacion',
            [
                'usuario' => $username,
                'archivo' => $nombre,
                'motivo' => $motivo,
                'puntuacion' => $resultado['puntuacion'] ?? 0,
            ]
        );

        $this->audit->log(request(), 'attachment.blocked', ActiveUser::from(request()), [
            'usuario' => $username,
            'archivo' => $nombre,
            'puntuacion' => $resultado['puntuacion'] ?? 0,
        ]);

        return response()->json([
            'error' => 'La imagen se retiro porque se detecto contenido no permitido.',
            'moderacion' => [
                'retirada' => true,
                'motivo' => $motivo,
            ],
        ], 422);
    }

    /**
     * Descarta una subida en curso y libera el espacio ocupado.
     */
    public function abort(Request $request, string $uploadId): JsonResponse
    {
        try {
            $this->uploads->abort($uploadId, ActiveUser::username(ActiveUser::from($request)));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['aborted' => true]);
    }
}
