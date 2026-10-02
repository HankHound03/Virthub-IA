<?php

namespace App\Console\Commands;

use App\Support\AttachmentStorage;
use Illuminate\Console\Command;

/**
 * Detecta y limpia referencias a adjuntos cuyo archivo ya no existe.
 *
 * Sirve para el dano ya ocurrido: los adjuntos se guardaban en la capa efimera
 * del contenedor, asi que las recreaciones los borraron y quedaron referencias
 * rotas en el foro y en los perfiles.
 *
 * Por defecto solo informa. Con --force elimina las referencias huerfanas.
 */
class PruneOrphanAttachments extends Command
{
    protected $signature = 'virthub:prune-attachments
                            {--force : Elimina las referencias huerfanas en lugar de solo listarlas}';

    protected $description = 'Detecta adjuntos cuyo archivo ya no existe en el almacenamiento';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('Buscando referencias a adjuntos inexistentes…');
        $this->newLine();

        $orphans = 0;
        $checked = 0;

        // --- Perfiles de usuario ---
        $users = $this->readJson(storage_path('app/data/users.json'));
        $profileOrphans = [];

        foreach ($users as $index => $user) {
            $path = (string) ($user['profile_image_path'] ?? '');

            if ($path === '') {
                continue;
            }

            $checked++;

            if (! AttachmentStorage::exists($path)) {
                $orphans++;
                $profileOrphans[] = [$user['username'] ?? '(sin nombre)', $path];

                if ($force) {
                    $users[$index]['profile_image_path'] = null;
                }
            }
        }

        $this->reportBlock('Perfiles de usuario', $profileOrphans);

        if ($force && $profileOrphans !== []) {
            $this->writeJson(storage_path('app/data/users.json'), $users);
        }

        // --- Publicaciones del foro ---
        $posts = $this->readJson(storage_path('app/data/forum.json'));
        $postOrphans = [];
        $changedPosts = false;

        foreach ($posts as $index => $post) {
            $author = (string) ($post['author'] ?? '?');
            $title = (string) ($post['title'] ?? '(sin titulo)');

            $imagePath = (string) ($post['image_path'] ?? '');

            if ($imagePath !== '') {
                $checked++;

                if (! AttachmentStorage::exists($imagePath)) {
                    $orphans++;
                    $postOrphans[] = [$author, $title, $imagePath];

                    if ($force) {
                        $posts[$index]['image_path'] = null;
                        $changedPosts = true;
                    }
                }
            }

            foreach ((array) ($post['attachments'] ?? []) as $attachmentIndex => $attachment) {
                $path = (string) ($attachment['path'] ?? '');

                if ($path === '') {
                    continue;
                }

                $checked++;

                if (! AttachmentStorage::exists($path)) {
                    $orphans++;
                    $postOrphans[] = [$author, $title, $path];

                    if ($force) {
                        unset($posts[$index]['attachments'][$attachmentIndex]);
                        $changedPosts = true;
                    }
                }
            }

            if ($force && $changedPosts && isset($posts[$index]['attachments'])) {
                $posts[$index]['attachments'] = array_values($posts[$index]['attachments']);
            }
        }

        $this->reportBlock('Publicaciones del foro', $postOrphans);

        if ($force && $changedPosts) {
            $this->writeJson(storage_path('app/data/forum.json'), $posts);
        }

        // --- Publicaciones de perfil ---
        $profilePosts = $this->readJson(storage_path('app/data/profile_posts.json'));
        $profilePostOrphans = [];
        $changedProfilePosts = false;

        foreach ($profilePosts as $index => $post) {
            foreach ((array) ($post['attachments'] ?? []) as $attachmentIndex => $attachment) {
                $path = (string) ($attachment['path'] ?? '');

                if ($path === '') {
                    continue;
                }

                $checked++;

                if (! AttachmentStorage::exists($path)) {
                    $orphans++;
                    $profilePostOrphans[] = [(string) ($post['author'] ?? '?'), $path];

                    if ($force) {
                        unset($profilePosts[$index]['attachments'][$attachmentIndex]);
                        $changedProfilePosts = true;
                    }
                }
            }

            if ($force && $changedProfilePosts && isset($profilePosts[$index]['attachments'])) {
                $profilePosts[$index]['attachments'] = array_values($profilePosts[$index]['attachments']);
            }
        }

        $this->reportBlock('Publicaciones de perfil', $profilePostOrphans);

        if ($force && $changedProfilePosts) {
            $this->writeJson(storage_path('app/data/profile_posts.json'), $profilePosts);
        }

        // --- Resumen ---
        $this->newLine();
        $this->line('Referencias revisadas: ' . $checked);
        $this->line('Referencias huerfanas: ' . $orphans);

        if ($orphans === 0) {
            $this->info('No hay nada que limpiar.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Modo informativo: no se ha cambiado nada.');
            $this->line('Para eliminar estas referencias, vuelve a ejecutarlo con --force.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Referencias huerfanas eliminadas. Las publicaciones se conservan.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function reportBlock(string $title, array $rows): void
    {
        if ($rows === []) {
            $this->line('  ' . $title . ': sin problemas');

            return;
        }

        $this->warn('  ' . $title . ': ' . count($rows) . ' referencia(s) rota(s)');

        foreach ($rows as $row) {
            $this->line('    - ' . implode(' | ', $row));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
            $this->error('No se pudo escribir ' . $path);
        }
    }
}
