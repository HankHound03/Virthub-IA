<?php

namespace App\Http\Controllers;

use App\Services\ChunkedUploadService;
use App\Services\ForumStore;
use App\Support\ActiveUser;
use App\Support\Aviso;
use App\Support\VirusScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Foro comunitario: publicaciones, encuestas, reacciones, comentarios y
 * reportes de moderacion.
 */
class ForumController extends Controller
{
    /**
     * Limite de los adjuntos que llegan incrustados en el formulario. Los
     * archivos grandes no pasan por aqui: se suben por trozos y llegan como
     * recibos que se canjean con ChunkedUploadService.
     */
    public const ATTACHMENT_MAX_BYTES = 5242880;

    public function __construct(
        private readonly ForumStore $forum,
        private readonly \App\Services\ModerationService $moderacion,
        private readonly \App\Services\AvisoStore $avisos,
        private readonly ChunkedUploadService $uploads,
        private readonly VirusScanner $antivirus,
        private readonly \App\Services\UserStore $users,
    ) {}

    public function index(Request $request): View
    {
        $currentUser = ActiveUser::from($request);

        $maxFileBytes = (int) config('virthub.uploads.max_file_bytes', 5 * 1024 ** 3);

        $posts = $this->forum->latestPosts((int) config('virthub.chat.posts_per_page', 120));

        /*
         * Datos de perfil de quienes publican.
         *
         * Se consulta una vez por autor (no por publicacion) y se pasa a la vista,
         * que asi puede pintar la foto de perfil junto al nombre como en cualquier
         * foro.
         */
        $autores = [];

        // Autores de las publicaciones y de los comentarios: unos y otros se
        // muestran con su nombre de perfil.
        $nombres = [];

        foreach ($posts as $publicacion) {
            $nombres[] = (string) ($publicacion['author'] ?? '');

            foreach ((array) ($publicacion['comments'] ?? []) as $comentario) {
                $nombres[] = (string) ($comentario['author'] ?? '');
            }
        }

        foreach (array_unique(array_filter($nombres)) as $nombre) {
            if ($nombre === '') {
                continue;
            }

            $usuario = $this->users->findByUsername($nombre);

            $autores[$nombre] = [
                'profile_image_path' => (string) ($usuario['profile_image_path'] ?? ''),
                'profile_frame_color' => (string) ($usuario['profile_frame_color'] ?? '#6ea8ff'),
                'name' => (string) ($usuario['name'] ?? $nombre),
            ];
        }

        return view('forum', [
            'currentUser' => $currentUser,
            'canPost' => ActiveUser::isRegistered($currentUser),
            'posts' => $posts,
            'autores' => $autores,
            // Limite de los adjuntos en el formulario clasico (una sola peticion).
            'attachmentMaxBytes' => self::ATTACHMENT_MAX_BYTES,
            'attachmentMaxLabel' => $this->humanSize(self::ATTACHMENT_MAX_BYTES),
            // Limite real por archivo, alcanzable con subida por trozos.
            'chunkedMaxBytes' => $maxFileBytes,
            'chunkedMaxLabel' => $this->humanSize($maxFileBytes),
            'chunkBytes' => (int) config('virthub.uploads.chunk_bytes', 8 * 1024 ** 2),
            'maxFilesPerPost' => (int) config('virthub.uploads.max_files_per_post', 10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Los errores de validacion se convierten en aviso de sesion: si se
        // dejaran como error de validacion (que tambien viaja como flash), una
        // peticion intermedia podria consumirlo y el usuario no veria nada.
        try {
            $validated = $this->validatePost($request);
        } catch (ValidationException $e) {
            Aviso::poner('error', (string) ($e->validator->errors()->first() ?: 'Revisa los datos del formulario.'));

            return redirect('/foro')->withInput();
        }

        $poll = $this->buildPoll($validated);

        if ($poll instanceof RedirectResponse) {
            return $poll;
        }

        try {
            $attachments = array_merge(
                $this->consumeUploadedAttachments($request),
                $this->storeAttachments($request)
            );

            $this->forum->addPost(
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['content'],
                isset($validated['title']) ? (string) $validated['title'] : null,
                $poll,
                $attachments
            );
        } catch (RuntimeException $e) {
            // Aqui cae, entre otros, el rechazo del antivirus.
            Aviso::poner('error', $e->getMessage());

            return redirect('/foro');
        } catch (\Throwable $e) {
            /*
             * Red de seguridad: cualquier fallo inesperado (un archivo que
             * desaparece, un limite del sistema) se registra y se le cuenta al
             * usuario, en vez de devolverle una pagina de error 500 sin
             * explicacion y sin saber si su publicacion se creo.
             */
            logger()->error('Error al publicar en el foro: ' . $e->getMessage(), [
                'excepcion' => $e::class,
            ]);

            Aviso::poner('error', 'No se pudo publicar por un error inesperado. Intentalo de nuevo.');

            return redirect('/foro');
        }

        Aviso::poner('success', 'Publicacion creada en el foro.');

        return redirect('/foro');
    }

    public function vote(Request $request, string $postId): RedirectResponse
    {
        $validated = $request->validate(['option_id' => 'required|string']);

        try {
            $this->forum->votePoll(
                $postId,
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['option_id']
            );
        } catch (RuntimeException $e) {
            return redirect('/foro')->with('error', $e->getMessage());
        }

        return redirect('/foro')->with('success', 'Voto registrado en la encuesta.');
    }

    public function react(Request $request, string $postId): RedirectResponse
    {
        $validated = $request->validate([
            'reaction' => 'required|string|in:like,love,fire',
        ]);

        $reactions = ['like' => '👍', 'love' => '❤️', 'fire' => '🔥'];

        try {
            $this->forum->toggleReaction(
                $postId,
                ActiveUser::username(ActiveUser::from($request)),
                $reactions[(string) $validated['reaction']] ?? '👍'
            );
        } catch (RuntimeException $e) {
            return redirect('/foro')->with('error', $e->getMessage());
        }

        return redirect('/foro');
    }

    public function comment(Request $request, string $postId): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1500',
        ]);

        try {
            $this->forum->addComment(
                $postId,
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['content']
            );
        } catch (RuntimeException $e) {
            return redirect('/foro')->with('error', $e->getMessage());
        }

        return redirect('/foro');
    }

    public function report(Request $request, string $postId): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:8|max:280',
        ]);

        try {
            $this->forum->addReport(
                $postId,
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['reason']
            );
        } catch (RuntimeException $e) {
            return redirect('/foro')->with('error', $e->getMessage());
        }

        return redirect('/foro')->with('success', 'Reporte enviado a moderacion.');
    }

    public function destroy(Request $request, string $postId): RedirectResponse
    {
        $currentUser = ActiveUser::from($request);
        $post = $this->forum->findById($postId);

        if ($post === null) {
            return redirect('/foro')->with('error', 'No existe la publicacion solicitada.');
        }

        $isAdmin = ActiveUser::isAdmin($currentUser);
        $isOwner = ($post['author'] ?? '') === ActiveUser::username($currentUser);

        if (! $isAdmin && ! $isOwner) {
            return redirect('/foro')->with('error', 'Solo puedes borrar tus propias publicaciones.');
        }

        $deleted = $this->forum->deletePost($postId);

        if ($deleted === null) {
            return redirect('/foro')->with('error', 'No se pudo eliminar la publicacion.');
        }

        /*
         * Se borran TODOS los adjuntos, no solo la imagen antigua.
         *
         * Antes solo se miraba 'image_path', que las publicaciones nuevas dejan en
         * null: los archivos adjuntos (fotos, videos y documentos, incluidos los
         * subidos por trozos) se quedaban en el servidor ocupando espacio para
         * siempre.
         */
        $this->deleteAttachment((string) ($deleted['image_path'] ?? ''));

        foreach ((array) ($deleted['attachments'] ?? []) as $adjunto) {
            if (is_array($adjunto)) {
                $this->deleteAttachment((string) ($adjunto['path'] ?? ''));
            }
        }

        return redirect('/foro')->with('success', 'Publicacion eliminada del foro.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => 'nullable|string|max:120',
            'content' => 'required|string|max:5000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp,gif|max:' . self::ATTACHMENT_MAX_BYTES,
            'videos' => 'nullable|array|max:5',
            'videos.*' => 'file|mimes:mp4,webm,mov,avi|max:' . self::ATTACHMENT_MAX_BYTES,
            'files' => 'nullable|array|max:10',
            'files.*' => [
                'file',
                'max:' . self::ATTACHMENT_MAX_BYTES,
                /*
                 * Solo las extensiones de la lista blanca.
                 *
                 * Antes se aceptaba cualquier archivo. Como los adjuntos se
                 * publican bajo el docroot (public/storage), subir un .php
                 * significaba ejecutar codigo en el servidor. La misma lista que
                 * usa la subida por trozos, para que las dos vias coincidan.
                 */
                function (string $atributo, $archivo, \Closure $fallar): void {
                    $permitidas = (array) config('virthub.uploads.allowed_extensions', []);

                    if ($permitidas === [] || ! is_object($archivo)) {
                        return;
                    }

                    $extension = strtolower((string) $archivo->getClientOriginalExtension());

                    if ($extension === '' || ! in_array($extension, $permitidas, true)) {
                        $fallar('El tipo de archivo .' . ($extension !== '' ? $extension : '?') . ' no esta permitido.');
                    }
                },
            ],
            'poll_question' => 'nullable|string|max:180',
            'poll_options' => 'nullable|array|max:10',
            'poll_options.*' => 'nullable|string|max:120',
            // Recibos de las subidas por trozos ya completadas.
            'upload_ids' => 'nullable|array|max:' . (int) config('virthub.uploads.max_files_per_post', 10),
            'upload_ids.*' => ['string', 'regex:/^[a-f0-9]{32}$/'],
        ]);
    }

    /**
     * Canjea los recibos de subida por adjuntos reales.
     *
     * El cliente solo presenta identificadores; la ruta del archivo la resuelve
     * el servidor desde su propio registro, verifica que pertenece al usuario y
     * que el archivo sigue en disco.
     *
     * @return array<int, array{type: string, name: string, mime: string, path: string, size: int}>
     */
    private function consumeUploadedAttachments(Request $request): array
    {
        $uploadIds = $request->input('upload_ids', []);

        if (! is_array($uploadIds) || $uploadIds === []) {
            return [];
        }

        return $this->uploads->consumeReceipts(
            array_values(array_filter($uploadIds, 'is_string')),
            ActiveUser::username(ActiveUser::from($request)),
            (int) config('virthub.uploads.max_files_per_post', 10)
        );
    }

    /**
     * Pasa un archivo subido por el antivirus.
     *
     * Lanza RuntimeException con un mensaje entendible; el metodo que llama ya
     * la convierte en el aviso de error del foro.
     */
    private function verificarArchivo(\Illuminate\Http\UploadedFile $archivo): void
    {
        $analisis = $this->antivirus->escanear((string) $archivo->getRealPath());

        if ($analisis['limpio']) {
            return;
        }

        $nombre = (string) $archivo->getClientOriginalName();

        if ($analisis['virus'] !== null) {
            throw new RuntimeException(
                "El archivo «{$nombre}» contiene un virus ({$analisis['virus']}) y no se guardo."
            );
        }

        throw new RuntimeException(
            "No se pudo analizar «{$nombre}»: " . ($analisis['error'] ?? 'antivirus no disponible') . '.'
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{question: string, options: array<int, string>}|RedirectResponse|null
     */
    private function buildPoll(array $validated): array|RedirectResponse|null
    {
        $question = trim((string) ($validated['poll_question'] ?? ''));
        $options = [];

        foreach (($validated['poll_options'] ?? []) as $rawOption) {
            $option = trim((string) $rawOption);

            if ($option !== '') {
                $options[] = $option;
            }
        }

        $options = array_values(array_unique($options));

        if ($question === '' && $options !== []) {
            return redirect('/foro')->withInput()->with('error', 'Para crear una encuesta debes completar la pregunta.');
        }

        if ($question !== '' && count($options) < 2) {
            return redirect('/foro')->withInput()->with('error', 'La encuesta necesita al menos 2 opciones.');
        }

        if ($question === '') {
            return null;
        }

        return ['question' => $question, 'options' => $options];
    }

    /**
     * Avisa de una foto retirada: al usuario y al administrador.
     *
     * @param array{prohibida: bool, puntuacion: float, motivo: string} $revision
     */
    private function registrarFotoRetirada(Request $request, string $nombre, array $revision): void
    {
        $usuario = (string) \App\Support\ActiveUser::username(\App\Support\ActiveUser::from($request));

        if ($usuario === '') {
            $usuario = 'invitado';
        }

        $this->avisos->crear(
            $usuario,
            'Foto retirada del foro',
            'La imagen "' . $nombre . '" no se publico porque se detecto contenido no permitido. '
                . 'Si crees que es un error, escribe al administrador.',
            'moderacion',
            [
                'archivo' => $nombre,
                'motivo' => $revision['motivo'] ?? '',
                'puntuacion' => $revision['puntuacion'] ?? 0,
            ]
        );

        $this->avisos->crear(
            'admin',
            'Foto retirada automaticamente',
            $usuario . ' intento publicar "' . $nombre . '" y la moderacion la retiro por contenido no permitido '
                . '(puntuacion ' . ($revision['puntuacion'] ?? 0) . ').',
            'moderacion',
            [
                'usuario' => $usuario,
                'archivo' => $nombre,
                'puntuacion' => $revision['puntuacion'] ?? 0,
            ]
        );

        $request->session()->flash(
            'aviso_moderacion',
            'La imagen "' . $nombre . '" no se publico porque se detecto contenido no permitido.'
        );
    }

    /**
     * Tipo real de un adjunto a partir de su extension y su tipo MIME.
     *
     * Coincide con el que usa la subida por trozos, para que las dos vias guarden
     * las cosas en el mismo sitio y se comporten igual.
     */
    private function tipoDeArchivo(string $extension, string $mime): string
    {
        $imagenes = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'];
        $videos = ['mp4', 'webm', 'mov', 'avi', 'mkv'];

        if (in_array($extension, $imagenes, true) || str_starts_with($mime, 'image/')) {
            return 'photo';
        }

        if (in_array($extension, $videos, true) || str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function storeAttachments(Request $request): array
    {
        $attachments = [];

        foreach (['photos' => 'photo', 'videos' => 'video', 'files' => 'file'] as $inputName => $type) {
            foreach ($request->file($inputName, []) as $uploaded) {
                // Antivirus ANTES de mover nada: si esta infectado no llega a
                // tocar el disco del servidor.
                $this->verificarArchivo($uploaded);

                $extension = strtolower((string) $uploaded->getClientOriginalExtension()) ?: 'bin';

                /*
                 * El tipo se deduce del archivo, no del boton con el que se subio.
                 *
                 * Antes se usaba el nombre del campo ('photos', 'files'...): una
                 * imagen enviada por el boton "Archivo" quedaba como documento y,
                 * ademas, se saltaba la moderacion, porque solo se revisaba lo que
                 * llegaba por 'photos'.
                 */
                $type = $this->tipoDeArchivo($extension, (string) $uploaded->getClientMimeType());

                // storage/app/public vive en el volumen persistente de Docker;
                // public/uploads se perderia al reconstruir el contenedor.
                $dir = storage_path('app/public/uploads/forum/' . $type . 's');

                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;

                /*
                 * El tipo MIME se calcula ANTES de mover.
                 *
                 * getMimeType() lee el archivo del disco, y despues de move() el
                 * temporal ya no existe: Symfony lanzaba
                 * «The "/tmp/phpXXXX" file does not exist or is not readable» y la
                 * publicacion se perdia con un error 500 en lugar de guardarse.
                 *
                 * Si el navegador envio un tipo, se usa ese como respaldo.
                 */
                $mime = '';

                try {
                    $mime = (string) $uploaded->getMimeType();
                } catch (\Throwable) {
                    $mime = (string) $uploaded->getClientMimeType();
                }

                $uploaded->move($dir, $filename);

                /*
                 * Moderacion de la foto.
                 *
                 * Las fotos pequenas del formulario no pasan por la subida por
                 * trozos, asi que hay que revisarlas aqui tambien. Sin esto solo
                 * se moderaban los archivos grandes: una foto pequena entraba sin
                 * mirar.
                 */
                if ($type === 'photo' && $this->moderacion->habilitado()) {
                    $ruta = $dir . '/' . $filename;

                    $revision = $this->moderacion->revisarImagen(
                        $ruta,
                        (string) $uploaded->getClientOriginalName()
                    );

                    if ($revision['prohibida']) {
                        @unlink($ruta);

                        try {
                            $this->registrarFotoRetirada(
                                $request,
                                (string) $uploaded->getClientOriginalName(),
                                $revision
                            );
                        } catch (\Throwable $e) {
                            // Avisar nunca debe impedir que la publicacion siga.
                            logger()->error('No se pudo avisar de la foto retirada: ' . $e->getMessage(), [
                                'excepcion' => $e::class,
                            ]);
                        }

                        continue;
                    }
                }

                $attachments[] = [
                    'type' => $type,
                    'name' => (string) $uploaded->getClientOriginalName(),
                    'mime' => $mime,
                    'path' => 'uploads/forum/' . $type . 's/' . $filename,
                ];
            }
        }

        return $attachments;
    }

    private function deleteAttachment(string $path): void
    {
        if ($path === '') {
            return;
        }

        $fullPath = storage_path('app/public/' . ltrim($path, '/'));

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return round($bytes / 1024 ** 3, 1) . ' GB';
        }

        if ($bytes >= 1024 ** 2) {
            return round($bytes / 1024 ** 2) . ' MB';
        }

        return round($bytes / 1024) . ' KB';
    }
}
