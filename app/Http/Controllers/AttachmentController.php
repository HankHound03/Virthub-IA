<?php

namespace App\Http\Controllers;

use App\Services\ChunkedUploadService;
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
