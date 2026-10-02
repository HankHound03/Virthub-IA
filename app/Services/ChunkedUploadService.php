<?php

namespace App\Services;

use RuntimeException;

/**
 * Subidas por trozos para archivos grandes.
 *
 * PHP tiene un techo para las subidas en una sola peticion: el proceso de
 * Apache tendria que recibir y bufferizar el archivo completo, y con varios
 * gigabytes eso agota memoria y tiempo. Con trozos, cada peticion es pequena y
 * el archivo se va escribiendo a disco incrementalmente.
 *
 * El contenido del trozo se lee como cuerpo en crudo y se copia con streams,
 * de modo que no se carga en memoria como objeto de subida.
 *
 * Estados de una sesion, en storage/app/chunked/{uploadId}/:
 *   meta.json      -> datos de la subida y numero de trozos recibidos
 *   part.bin       -> el contenido acumulado
 *   completed.json -> marca de finalizacion (evita ensamblar dos veces)
 */
class ChunkedUploadService
{
    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = storage_path('app/chunked');
    }

    /**
     * Abre una sesion de subida y devuelve los datos que el cliente necesita
     * para trocear el archivo.
     *
     * @return array<string, mixed>
     */
    public function initiate(string $owner, string $originalName, int $totalBytes, string $mime = ''): array
    {
        $maxBytes = $this->maxFileBytes();

        if ($totalBytes <= 0) {
            throw new RuntimeException('El archivo esta vacio.');
        }

        if ($totalBytes > $maxBytes) {
            throw new RuntimeException(
                'El archivo supera el maximo permitido de ' . $this->humanSize($maxBytes) . '.'
            );
        }

        $extension = $this->safeExtension($originalName);

        if (! in_array($extension, $this->allowedExtensions(), true)) {
            throw new RuntimeException('La extension .' . $extension . ' no esta permitida.');
        }

        $uploadId = bin2hex(random_bytes(16));
        $chunkBytes = $this->chunkBytes();
        $totalChunks = (int) ceil($totalBytes / $chunkBytes);

        $dir = $this->sessionDir($uploadId);
        $this->ensureDir($dir);

        // part.bin se crea vacio: los trozos se anaden al final.
        if (file_put_contents($dir . '/part.bin', '') === false) {
            throw new RuntimeException('No se pudo preparar el archivo de destino.');
        }

        $meta = [
            'upload_id' => $uploadId,
            'owner' => $owner,
            'original_name' => $this->sanitizeName($originalName),
            'extension' => $extension,
            'declared_mime' => $mime,
            'total_bytes' => $totalBytes,
            'chunk_bytes' => $chunkBytes,
            'total_chunks' => $totalChunks,
            'received_chunks' => 0,
            'received_bytes' => 0,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        $this->writeMeta($uploadId, $meta);

        return [
            'upload_id' => $uploadId,
            'chunk_bytes' => $chunkBytes,
            'total_chunks' => $totalChunks,
        ];
    }

    /**
     * Anade un trozo al final del archivo en construccion.
     *
     * @return array<string, mixed>
     */
    public function appendChunk(string $uploadId, string $owner, int $index, string $content): array
    {
        $meta = $this->loadMeta($uploadId);

        $this->assertOwnership($meta, $owner);
        $this->assertNotCompleted($uploadId);

        $totalChunks = (int) $meta['total_chunks'];

        if ($index < 0 || $index >= $totalChunks) {
            throw new RuntimeException('Indice de trozo fuera de rango.');
        }

        // Los trozos deben llegar en orden: escribir al final solo es correcto
        // si el que toca es el siguiente. El cliente reintenta los que fallan.
        $expected = (int) $meta['received_chunks'];

        if ($index !== $expected) {
            throw new RuntimeException(
                'Trozo fuera de orden: se esperaba el ' . $expected . ' y llego el ' . $index . '.'
            );
        }

        $receivedBytes = (int) $meta['received_bytes'];
        $totalBytes = (int) $meta['total_bytes'];
        $expectedSize = $index === $totalChunks - 1
            ? $totalBytes - $receivedBytes
            : (int) $meta['chunk_bytes'];

        if (strlen($content) !== $expectedSize) {
            throw new RuntimeException(
                'El trozo ' . $index . ' mide ' . strlen($content) .
                ' bytes y se esperaban ' . $expectedSize . '.'
            );
        }

        $path = $this->sessionDir($uploadId) . '/part.bin';
        $handle = fopen($path, 'ab');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo en construccion.');
        }

        try {
            if (fwrite($handle, $content) === false) {
                throw new RuntimeException('No se pudo escribir el trozo.');
            }

            fflush($handle);
        } finally {
            fclose($handle);
        }

        $meta['received_chunks'] = $index + 1;
        $meta['received_bytes'] = $receivedBytes + strlen($content);
        $meta['updated_at'] = date('c');
        $this->writeMeta($uploadId, $meta);

        return [
            'received_chunks' => (int) $meta['received_chunks'],
            'total_chunks' => $totalChunks,
            'received_bytes' => (int) $meta['received_bytes'],
            'total_bytes' => $totalBytes,
        ];
    }

    /**
     * Verifica que estan todos los trozos y mueve el archivo a su destino final.
     *
     * @return array{type: string, name: string, mime: string, path: string, size: int}
     */
    public function complete(string $uploadId, string $owner): array
    {
        // Si ya se ensamblo, devolvemos el mismo resultado: el cliente puede
        // reintentar la finalizacion sin duplicar el archivo.
        $completed = $this->readCompleted($uploadId);

        if ($completed !== null) {
            $this->assertOwnership($completed, $owner);

            return $this->attachmentFromMeta($completed);
        }

        $meta = $this->loadMeta($uploadId);

        $this->assertOwnership($meta, $owner);

        if ((int) $meta['received_chunks'] !== (int) $meta['total_chunks']) {
            throw new RuntimeException(
                'Faltan trozos: ' . $meta['received_chunks'] . ' de ' . $meta['total_chunks'] . '.'
            );
        }

        $partPath = $this->sessionDir($uploadId) . '/part.bin';
        $actualSize = is_file($partPath) ? (int) filesize($partPath) : 0;
        $declaredSize = (int) $meta['total_bytes'];

        if ($actualSize !== $declaredSize) {
            throw new RuntimeException(
                'El archivo final mide ' . $actualSize . ' bytes y se esperaban ' . $declaredSize . '.'
            );
        }

        $type = $this->resolveType((string) $meta['extension']);
        $directory = $this->publicDirectory($type);
        $this->ensureDir($directory);

        $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $meta['extension'];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;

        if (! @rename($partPath, $destination)) {
            // rename puede fallar entre sistemas de archivos distintos.
            if (! @copy($partPath, $destination)) {
                throw new RuntimeException('No se pudo guardar el archivo subido.');
            }

            @unlink($partPath);
        }

        $meta['final_path'] = 'uploads/forum/' . $type . 's/' . $filename;
        $meta['final_mime'] = $this->detectMime($destination, (string) $meta['declared_mime']);
        $meta['completed_at'] = date('c');

        $this->writeCompleted($uploadId, $meta);

        return $this->attachmentFromMeta($meta);
    }

    /**
     * Descarta una subida en curso y libera el espacio.
     */
    public function abort(string $uploadId, string $owner): void
    {
        $meta = $this->loadMeta($uploadId);

        $this->assertOwnership($meta, $owner);

        $this->removeDirectory($this->sessionDir($uploadId));
    }

    /**
     * Canjea un recibo de subida por el adjunto real.
     *
     * El cliente nunca decide la ruta del archivo: solo presenta el id que el
     * servidor le dio al ensamblar. Aqui se comprueba que el recibo existe, que
     * es de este usuario y que el archivo sigue en disco. El recibo se consume
     * para que un mismo archivo no se pueda adjuntar dos veces.
     *
     * @param  array<int, string>  $uploadIds
     * @return array<int, array{type: string, name: string, mime: string, path: string, size: int}>
     */
    public function consumeReceipts(array $uploadIds, string $owner, int $maxFiles): array
    {
        $uploadIds = array_values(array_unique(array_filter($uploadIds, static fn ($id): bool => is_string($id) && $id !== '')));

        if (count($uploadIds) > $maxFiles) {
            throw new RuntimeException('Puedes adjuntar como maximo ' . $maxFiles . ' archivos.');
        }

        $attachments = [];

        foreach ($uploadIds as $uploadId) {
            $receipt = $this->readCompleted($uploadId);

            if ($receipt === null) {
                throw new RuntimeException('Uno de los adjuntos no existe o expiro.');
            }

            $this->assertOwnership($receipt, $owner);

            $attachment = $this->attachmentFromMeta($receipt);

            if ($attachment['path'] === '' || ! is_file(storage_path('app/public/' . ltrim($attachment['path'], '/')))) {
                throw new RuntimeException('Uno de los adjuntos ya no esta disponible.');
            }

            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * Elimina sesiones abandonadas y sus archivos temporales.
     *
     * @return int numero de sesiones limpiadas
     */
    public function pruneExpiredSessions(): int
    {
        if (! is_dir($this->baseDir)) {
            return 0;
        }

        $ttlSeconds = max(1, (int) config('virthub.uploads.session_ttl_hours', 24)) * 3600;
        $threshold = time() - $ttlSeconds;
        $pruned = 0;

        foreach ((array) glob($this->baseDir . '/*', GLOB_ONLYDIR) as $dir) {
            $metaPath = $dir . '/meta.json';
            $decoded = is_file($metaPath)
                ? json_decode((string) file_get_contents($metaPath), true)
                : null;

            $updatedAt = is_array($decoded) ? (int) ($decoded['updated_at_ts'] ?? 0) : 0;
            $mtime = $updatedAt > 0 ? $updatedAt : (int) @filemtime($dir);

            // Solo se limpian las subidas que quedaron a medias. Los recibos de
            // las completadas se conservan como registro de auditoria.
            if ($mtime > 0 && $mtime < $threshold && ! is_file($dir . '/completed.json')) {
                $this->removeDirectory($dir);
                $pruned++;
            }
        }

        return $pruned;
    }

    public function sessionExists(string $uploadId): bool
    {
        return is_file($this->sessionDir($uploadId) . '/meta.json');
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(string $uploadId, string $owner): array
    {
        $meta = $this->readCompleted($uploadId) ?? $this->loadMeta($uploadId);

        $this->assertOwnership($meta, $owner);

        return $meta;
    }

    // -----------------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------------

    private function maxFileBytes(): int
    {
        return max(1, (int) config('virthub.uploads.max_file_bytes', 5 * 1024 ** 3));
    }

    private function chunkBytes(): int
    {
        // Se respeta lo configurado; el minimo solo evita valores absurdos que
        // multiplicarian el numero de peticiones sin necesidad.
        return max(1024, (int) config('virthub.uploads.chunk_bytes', 8 * 1024 ** 2));
    }

    /**
     * @return array<int, string>
     */
    private function allowedExtensions(): array
    {
        return (array) config('virthub.uploads.allowed_extensions', []);
    }

    private function sessionDir(string $uploadId): string
    {
        // El id lo genera el servidor en hexadecimal; aun asi se filtra para que
        // nunca pueda contener separadores de ruta.
        $safeId = preg_replace('/[^a-f0-9]/', '', strtolower($uploadId)) ?: '';

        if ($safeId === '') {
            throw new RuntimeException('Identificador de subida invalido.');
        }

        return $this->baseDir . DIRECTORY_SEPARATOR . $safeId;
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio: ' . $dir);
        }
    }

    private function safeExtension(string $originalName): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        return preg_replace('/[^a-z0-9]/', '', $extension) ?: '';
    }

    private function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
        $name = trim($name);

        return $name !== '' ? mb_substr($name, 0, 200) : 'archivo';
    }

    /**
     * Traduce la extension a la carpeta publica donde se guarda.
     */
    private function resolveType(string $extension): string
    {
        $images = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'];
        $videos = ['mp4', 'webm', 'mov', 'avi', 'mkv'];

        return match (true) {
            in_array($extension, $images, true) => 'photo',
            in_array($extension, $videos, true) => 'video',
            default => 'file',
        };
    }

    private function publicDirectory(string $type): string
    {
        // Los adjuntos se guardan dentro de storage/app/public, que en Docker
        // vive en el volumen persistente. Escribirlos directamente en public/
        // los dejaria en la capa efimera del contenedor: sobrevivirian hasta la
        // siguiente reconstruccion. Se sirven a traves del enlace
        // public/storage -> storage/app/public.
        return storage_path('app/public/uploads/forum/' . $type . 's');
    }

    /**
     * Ruta publica (URL) de un adjunto dentro del almacenamiento persistente.
     *
     * Devuelve null mientras el enlace public/storage no exista, para que el
     * fallo sea explicito en lugar de dejar adjuntos que no se pueden abrir.
     */
    public static function isPubliclyReachable(): bool
    {
        return is_link(public_path('storage')) || is_dir(public_path('storage'));
    }

    private function detectMime(string $path, string $fallback): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return $fallback !== '' ? $fallback : 'application/octet-stream';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assertOwnership(array $meta, string $owner): void
    {
        if ((string) ($meta['owner'] ?? '') !== $owner) {
            // Mismo mensaje que una sesion inexistente: no revelamos si el id
            // existe pero pertenece a otro usuario.
            throw new RuntimeException('La subida no existe o no te pertenece.');
        }
    }

    private function assertNotCompleted(string $uploadId): void
    {
        if (is_file($this->sessionDir($uploadId) . '/completed.json')) {
            throw new RuntimeException('Esta subida ya fue completada.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMeta(string $uploadId): array
    {
        $path = $this->sessionDir($uploadId) . '/meta.json';

        if (! is_file($path)) {
            throw new RuntimeException('La subida no existe o expiro.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('La sesion de subida esta corrupta.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeMeta(string $uploadId, array $meta): void
    {
        $meta['updated_at_ts'] = time();

        $this->writeJson($this->sessionDir($uploadId) . '/meta.json', $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeCompleted(string $uploadId, array $meta): void
    {
        $this->writeJson($this->sessionDir($uploadId) . '/completed.json', $meta);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCompleted(string $uploadId): ?array
    {
        $path = $this->sessionDir($uploadId) . '/completed.json';

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new RuntimeException('No se pudo codificar el estado de la subida.');
        }

        if (file_put_contents($path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el estado de la subida.');
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{type: string, name: string, mime: string, path: string, size: int}
     */
    private function attachmentFromMeta(array $meta): array
    {
        return [
            'type' => $this->resolveType((string) ($meta['extension'] ?? '')),
            'name' => (string) ($meta['original_name'] ?? 'archivo'),
            'mime' => (string) ($meta['final_mime'] ?? 'application/octet-stream'),
            'path' => (string) ($meta['final_path'] ?? ''),
            'size' => (int) ($meta['total_bytes'] ?? 0),
        ];
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir . '/*') as $item) {
            is_dir($item) ? $this->removeDirectory($item) : @unlink($item);
        }

        @rmdir($dir);
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
