<?php

namespace App\Http\Controllers;

use App\Services\ChunkedUploadService;
use App\Services\ForumStore;
use App\Support\ActiveUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly ChunkedUploadService $uploads,
    ) {}

    public function index(Request $request): View
    {
        $currentUser = ActiveUser::from($request);

        $maxFileBytes = (int) config('virthub.uploads.max_file_bytes', 5 * 1024 ** 3);

        return view('forum', [
            'currentUser' => $currentUser,
            'canPost' => ActiveUser::isRegistered($currentUser),
            'posts' => $this->forum->latestPosts((int) config('virthub.chat.posts_per_page', 120)),
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
        $validated = $this->validatePost($request);

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
            return redirect('/foro')->with('error', $e->getMessage());
        }

        return redirect('/foro')->with('success', 'Publicacion creada en el foro.');
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

        $this->deleteAttachment((string) ($deleted['image_path'] ?? ''));

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
            'files.*' => 'file|max:' . self::ATTACHMENT_MAX_BYTES,
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
     * @return array<int, array<string, string>>
     */
    private function storeAttachments(Request $request): array
    {
        $attachments = [];

        foreach (['photos' => 'photo', 'videos' => 'video', 'files' => 'file'] as $inputName => $type) {
            foreach ($request->file($inputName, []) as $uploaded) {
                // storage/app/public vive en el volumen persistente de Docker;
                // public/uploads se perderia al reconstruir el contenedor.
                $dir = storage_path('app/public/uploads/forum/' . $type . 's');

                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $extension = strtolower((string) $uploaded->getClientOriginalExtension()) ?: 'bin';
                $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;

                $uploaded->move($dir, $filename);

                $attachments[] = [
                    'type' => $type,
                    'name' => (string) $uploaded->getClientOriginalName(),
                    'mime' => (string) $uploaded->getMimeType(),
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
