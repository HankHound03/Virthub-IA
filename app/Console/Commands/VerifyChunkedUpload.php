<?php

namespace App\Console\Commands;

use App\Services\ChunkedUploadService;
use Illuminate\Console\Command;

/**
 * Comando temporal de verificacion de la subida por trozos.
 */
class VerifyChunkedUpload extends Command
{
    protected $signature = 'virthub:verify-chunks {--megas=25}';

    protected $description = 'Verifica de extremo a extremo la subida por trozos';

    public function handle(ChunkedUploadService $uploads): int
    {
        $megas = max(1, (int) $this->option('megas'));
        $totalBytes = $megas * 1024 * 1024;

        $this->info("Tamano de archivo: {$megas} MB ({$totalBytes} bytes)");
        $this->info('Limite de PHP: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size'));
        $this->info('Tamano de trozo configurado: ' . round(((int) config('virthub.uploads.chunk_bytes')) / 1048576, 2) . ' MB');
        $this->newLine();

        // Contenido determinista para poder verificar la integridad despues.
        $content = random_bytes($totalBytes);

        try {
            $session = $uploads->initiate('verificador', 'prueba-grande.bin', $totalBytes, 'application/octet-stream');
        } catch (\Throwable $e) {
            $this->error('Fallo al iniciar: ' . $e->getMessage());

            return self::FAILURE;
        }

        $uploadId = $session['upload_id'];
        $chunkBytes = (int) $session['chunk_bytes'];
        $totalChunks = (int) $session['total_chunks'];

        $this->info("Sesion {$uploadId}: {$totalChunks} trozos de " . round($chunkBytes / 1048576, 2) . ' MB');
        $this->newLine();

        $started = microtime(true);

        for ($i = 0; $i < $totalChunks; $i++) {
            $slice = substr($content, $i * $chunkBytes, $chunkBytes);

            try {
                $uploads->appendChunk($uploadId, 'verificador', $i, $slice);
            } catch (\Throwable $e) {
                $this->error("Fallo en el trozo {$i}: " . $e->getMessage());

                return self::FAILURE;
            }

            if ($i % 2 === 0 || $i === $totalChunks - 1) {
                $this->line('  trozo ' . ($i + 1) . '/' . $totalChunks . ' enviado (' . number_format(strlen($slice)) . ' bytes)');
            }
        }

        $elapsed = microtime(true) - $started;
        $this->newLine();
        $this->info('Trozos enviados en ' . round($elapsed, 2) . ' s');

        try {
            $attachment = $uploads->complete($uploadId, 'verificador');
        } catch (\Throwable $e) {
            $this->error('Fallo al ensamblar: ' . $e->getMessage());

            return self::FAILURE;
        }

        $path = public_path($attachment['path']);
        $this->newLine();
        $this->line('  ruta final : ' . $attachment['path']);
        $this->line('  tamano     : ' . number_format($attachment['size']) . ' bytes');
        $this->line('  mime       : ' . $attachment['mime']);

        if (! is_file($path)) {
            $this->error('El archivo final no existe en disco.');

            return self::FAILURE;
        }

        // Verificacion de integridad: el archivo debe ser identico al original.
        $finalSize = filesize($path);
        $matches = $finalSize === $totalBytes && hash_file('sha256', $path) === hash('sha256', $content);

        $this->line('  en disco   : ' . number_format($finalSize) . ' bytes');
        $this->newLine();

        if (! $matches) {
            $this->error('INTEGRIDAD FALLIDA: el archivo ensamblado no coincide con el original.');

            return self::FAILURE;
        }

        $this->info('INTEGRIDAD OK: el archivo ensamblado es identico al original (sha256 coincide).');

        // Limpieza: quitamos el archivo y el recibo de la prueba.
        @unlink($path);
        $uploads->abort($uploadId, 'verificador');

        return self::SUCCESS;
    }
}
