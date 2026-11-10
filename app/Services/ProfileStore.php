<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class ProfileStore
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/profile_posts.json');
    }

    public function latestPostsFor(string $username, int $limit = 50): array
    {
        $username = trim($username);
        $posts = array_values(array_filter($this->readPosts(), function (array $post) use ($username): bool {
            return ($post['author'] ?? '') === $username;
        }));

        return array_slice(array_reverse($posts), 0, max(1, $limit));
    }

    public function addPost(string $author, string $content): array
    {
        $author = trim($author);
        $content = trim($content);

        if ($author === '' || $content === '') {
            throw new RuntimeException('El contenido de la publicacion no puede estar vacio.');
        }

        return $this->updatePosts(function (array &$posts) use ($author, $content): array {
            $record = [
                'id' => Str::uuid()->toString(),
                'author' => $author,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $posts[] = $record;
            $posts = array_slice($posts, -1000);

            return $record;
        });
    }

    /**
     * Borra una publicacion del perfil.
     *
     * Devuelve la publicacion eliminada, o null si no existe o no es de esa
     * persona. Se comprueba el autor aqui y no en el controlador para que nadie
     * pueda borrar la publicacion de otro pasando un identificador.
     */
    public function deletePost(string $author, string $postId): ?array
    {
        $author = trim($author);
        $postId = trim($postId);

        if ($author === '' || $postId === '') {
            return null;
        }

        return $this->updatePosts(function (array &$posts) use ($author, $postId): ?array {
            foreach ($posts as $indice => $post) {
                if (($post["id"] ?? "") !== $postId) {
                    continue;
                }

                if (($post["author"] ?? "") !== $author) {
                    return null;
                }

                unset($posts[$indice]);
                $posts = array_values($posts);

                return $post;
            }

            return null;
        });
    }

    /**
     * Anota un reporte sobre una publicacion del perfil.
     *
     * Los reportes viven dentro de la propia publicacion, igual que en el foro,
     * para que borrar la publicacion se lleve tambien sus reportes.
     */
    public function addReport(string $postId, string $reporter, string $reason): array
    {
        $postId = trim($postId);
        $reporter = trim($reporter);
        $reason = trim($reason);

        if ($postId === '' || $reporter === '') {
            throw new RuntimeException('Datos invalidos para reportar.');
        }

        if ($reason === '') {
            $reason = 'Sin detalle';
        }

        return $this->updatePosts(function (array &$posts) use ($postId, $reporter, $reason): array {
            foreach ($posts as &$post) {
                if (($post['id'] ?? '') !== $postId) {
                    continue;
                }

                $reports = is_array($post['reports'] ?? null) ? $post['reports'] : [];
                $reporte = [
                    'id' => Str::uuid()->toString(),
                    'reporter' => $reporter,
                    'reason' => $reason,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                $reports[] = $reporte;
                $post['reports'] = array_slice($reports, -100);
                unset($post);

                return $reporte;
            }

            unset($post);

            throw new RuntimeException('Esa publicacion ya no existe.');
        });
    }

    /**
     * Todos los reportes de perfiles, con la forma que espera el panel.
     *
     * @return array<int, array<string, string>>
     */
    public function allReports(): array
    {
        $salida = [];

        foreach ($this->readPosts() as $post) {
            foreach ((array) ($post['reports'] ?? []) as $reporte) {
                if (! is_array($reporte)) {
                    continue;
                }

                $autor = (string) ($post['author'] ?? 'usuario');

                $salida[] = [
                    'kind' => 'perfil',
                    'reported_at' => (string) ($reporte['created_at'] ?? ''),
                    'reporter' => (string) ($reporte['reporter'] ?? ''),
                    'reason' => (string) ($reporte['reason'] ?? ''),
                    'post_id' => (string) ($post['id'] ?? ''),
                    'post_title' => 'Perfil de ' . $autor,
                    'post_content' => (string) ($post['content'] ?? ''),
                    'post_author' => $autor,
                    'report_id' => (string) ($reporte['id'] ?? ''),
                ];
            }
        }

        return $salida;
    }

    /** Quita un reporte ya revisado. */
    public function removeReport(string $postId, string $reportId): bool
    {
        return $this->updatePosts(function (array &$posts) use ($postId, $reportId): bool {
            foreach ($posts as &$post) {
                if (($post['id'] ?? '') !== $postId) {
                    continue;
                }

                $reports = is_array($post['reports'] ?? null) ? $post['reports'] : [];
                $quedan = array_values(array_filter($reports, function (array $r) use ($reportId): bool {
                    return (string) ($r['id'] ?? '') !== $reportId;
                }));

                $post['reports'] = $quedan;
                unset($post);

                return count($quedan) !== count($reports);
            }

            unset($post);

            return false;
        });
    }

    private function ensureStore(): void
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function readPosts(): array
    {
        $this->ensureStore();
        $content = file_get_contents($this->filePath);

        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function updatePosts(callable $callback): mixed
    {
        $this->ensureStore();
        $handle = fopen($this->filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir las publicaciones del perfil.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear las publicaciones del perfil.');
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            $decoded = json_decode($content !== false ? $content : '', true);
            $posts = is_array($decoded) ? $decoded : [];
            $result = $callback($posts);
            $encoded = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new RuntimeException('No se pudo guardar las publicaciones del perfil.');
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}