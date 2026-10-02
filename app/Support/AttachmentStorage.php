<?php

namespace App\Support;

/**
 * Acceso a los archivos subidos por los usuarios.
 *
 * Los adjuntos viven en storage/app/public, que en Docker esta en el volumen
 * persistente, y se sirven a traves del enlace public/storage.
 *
 * Existe porque los registros guardan la ruta relativa (uploads/...) y antes se
 * pintaban siempre con asset(), sin comprobar que el archivo siguiera en disco.
 * Cuando faltaba, el navegador mostraba el texto alternativo y la interfaz se
 * veia rota: eso es exactamente lo que ocurre con los adjuntos perdidos antes de
 * que existiera almacenamiento persistente.
 */
class AttachmentStorage
{
    /** Ruta absoluta de un adjunto a partir de su ruta relativa guardada. */
    public static function path(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);

        if ($relativePath === '') {
            return null;
        }

        // Evita que una ruta manipulada salga del directorio de subidas.
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (str_contains($normalized, '..')) {
            return null;
        }

        return storage_path('app/public/' . $normalized);
    }

    /** Indica si el archivo existe realmente en disco. */
    public static function exists(?string $relativePath): bool
    {
        $path = self::path($relativePath);

        return $path !== null && is_file($path);
    }

    /** Tamano legible del archivo, o null si no existe. */
    public static function humanSize(?string $relativePath, int $fallbackBytes = 0): ?string
    {
        $path = self::path($relativePath);
        $bytes = ($path !== null && is_file($path)) ? (int) filesize($path) : $fallbackBytes;

        if ($bytes <= 0) {
            return null;
        }

        if ($bytes >= 1024 ** 3) {
            return round($bytes / 1024 ** 3, 2) . ' GB';
        }

        if ($bytes >= 1024 ** 2) {
            return round($bytes / 1024 ** 2, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }
}
