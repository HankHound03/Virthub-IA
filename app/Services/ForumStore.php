<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class ForumStore
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/forum.json');
    }

    public function latestPosts(int $limit = 100): array
    {
        $posts = $this->readPosts();
        $posts = array_values(array_reverse($posts));

        return array_slice($posts, 0, max(1, $limit));
    }

    public function addPost(string $author, string $content, ?string $title = null): array
    {
        $author = trim($author);
        $content = trim($content);
        $title = $title !== null ? trim($title) : null;

        if ($author === '') {
            throw new RuntimeException('Autor invalido para el post.');
        }

        if ($content === '') {
            throw new RuntimeException('El contenido del post no puede estar vacio.');
        }

        $posts = $this->readPosts();
        $record = [
            'id' => Str::uuid()->toString(),
            'author' => $author,
            'title' => $title,
            'content' => $content,
            'image_path' => null,
            'reactions' => [],
            'comments' => [],
            'reports' => [],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $posts[] = $record;
        $posts = array_slice($posts, -300);

        $this->writePosts($posts);

        return $record;
    }

    public function findById(string $postId): ?array
    {
        $postId = trim($postId);

        if ($postId === '') {
            return null;
        }

        foreach ($this->readPosts() as $post) {
            if (($post['id'] ?? '') === $postId) {
                return $post;
            }
        }

        return null;
    }

    public function setPostImagePath(string $postId, ?string $imagePath): void
    {
        $posts = $this->readPosts();
        $updated = false;

        foreach ($posts as &$post) {
            if (($post['id'] ?? '') === $postId) {
                $post['image_path'] = $imagePath;
                $updated = true;
                break;
            }
        }
        unset($post);

        if (!$updated) {
            throw new RuntimeException('No existe una publicacion con ese id.');
        }

        $this->writePosts($posts);
    }

    public function toggleReaction(string $postId, string $username, string $reaction): array
    {
        $postId = trim($postId);
        $username = trim($username);
        $reaction = trim($reaction);

        if ($postId === '' || $username === '' || $reaction === '') {
            throw new RuntimeException('Datos invalidos para reaccionar.');
        }

        $posts = $this->readPosts();
        $updatedPost = null;
        $updated = false;

        foreach ($posts as &$post) {
            if (($post['id'] ?? '') !== $postId) {
                continue;
            }

            $reactions = is_array($post['reactions'] ?? null) ? $post['reactions'] : [];
            $users = is_array($reactions[$reaction] ?? null) ? $reactions[$reaction] : [];

            if (in_array($username, $users, true)) {
                $users = array_values(array_filter($users, function (string $user) use ($username): bool {
                    return $user !== $username;
                }));
            } else {
                $users[] = $username;
            }

            if (count($users) === 0) {
                unset($reactions[$reaction]);
            } else {
                $reactions[$reaction] = array_values(array_unique($users));
            }

            $post['reactions'] = $reactions;
            $updatedPost = $post;
            $updated = true;
            break;
        }
        unset($post);

        if (!$updated) {
            throw new RuntimeException('No existe una publicacion con ese id.');
        }

        $this->writePosts($posts);

        return $updatedPost ?? [];
    }

    public function deletePost(string $postId): ?array
    {
        $postId = trim($postId);

        if ($postId === '') {
            return null;
        }

        $posts = $this->readPosts();
        $kept = [];
        $deleted = null;

        foreach ($posts as $post) {
            if (($post['id'] ?? '') === $postId) {
                $deleted = $post;
                continue;
            }

            $kept[] = $post;
        }

        if (!$deleted) {
            return null;
        }

        $this->writePosts($kept);

        return $deleted;
    }

    public function addComment(string $postId, string $author, string $content): array
    {
        $postId = trim($postId);
        $author = trim($author);
        $content = trim($content);

        if ($postId === '' || $author === '' || $content === '') {
            throw new RuntimeException('Datos invalidos para comentar.');
        }

        $posts = $this->readPosts();
        $updated = false;
        $updatedPost = null;

        foreach ($posts as &$post) {
            if (($post['id'] ?? '') !== $postId) {
                continue;
            }

            $comments = is_array($post['comments'] ?? null) ? $post['comments'] : [];
            $comments[] = [
                'id' => Str::uuid()->toString(),
                'author' => $author,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $post['comments'] = array_slice($comments, -200);
            $updatedPost = $post;
            $updated = true;
            break;
        }
        unset($post);

        if (!$updated) {
            throw new RuntimeException('No existe una publicacion con ese id.');
        }

        $this->writePosts($posts);

        return $updatedPost ?? [];
    }

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

        $posts = $this->readPosts();
        $updated = false;
        $updatedPost = null;

        foreach ($posts as &$post) {
            if (($post['id'] ?? '') !== $postId) {
                continue;
            }

            $reports = is_array($post['reports'] ?? null) ? $post['reports'] : [];
            $reports[] = [
                'id' => Str::uuid()->toString(),
                'reporter' => $reporter,
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $post['reports'] = array_slice($reports, -100);
            $updatedPost = $post;
            $updated = true;
            break;
        }
        unset($post);

        if (!$updated) {
            throw new RuntimeException('No existe una publicacion con ese id.');
        }

        $this->writePosts($posts);

        return $updatedPost ?? [];
    }

    public function removeReport(string $postId, string $reportId): bool
    {
        $postId = trim($postId);
        $reportId = trim($reportId);

        if ($postId === '' || $reportId === '') {
            return false;
        }

        $posts = $this->readPosts();
        $updated = false;
        $removed = false;

        foreach ($posts as &$post) {
            if (($post['id'] ?? '') !== $postId) {
                continue;
            }

            $reports = is_array($post['reports'] ?? null) ? $post['reports'] : [];
            $nextReports = [];

            foreach ($reports as $report) {
                if ((string) ($report['id'] ?? '') === $reportId) {
                    $removed = true;
                    continue;
                }

                $nextReports[] = $report;
            }

            $post['reports'] = $nextReports;
            $updated = true;
            break;
        }
        unset($post);

        if ($updated) {
            $this->writePosts($posts);
        }

        return $removed;
    }

    private function ensureStore(): void
    {
        $dirPath = dirname($this->filePath);

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
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

    private function writePosts(array $posts): void
    {
        $encoded = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new RuntimeException('No se pudo guardar el foro JSON.');
        }

        file_put_contents($this->filePath, $encoded, LOCK_EX);
    }
}