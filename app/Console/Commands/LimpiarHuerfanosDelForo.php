<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Archivos del foro que ya no pertenecen a ninguna publicacion.
 *
 * Cuando se borra una publicacion hay que borrar tambien sus archivos. Durante un
 * tiempo no se hizo (solo se miraba un campo antiguo que las publicaciones nuevas
 * dejan vacio), asi que quedaron archivos pesados ocupando disco sin que nadie los
 * usara.
 *
 * Por defecto solo informa. Para borrarlos hace falta pedirlo explicitamente:
 *
 *   php artisan foro:huerfanos            (solo lista)
 *   php artisan foro:huerfanos --borrar   (los elimina)
 */
class LimpiarHuerfanosDelForo extends Command
{
    protected $signature = 'foro:huerfanos {--borrar : Elimina los archivos en vez de solo listarlos}';

    protected $description = 'Busca (y opcionalmente borra) archivos del foro que no usa ninguna publicacion';

    public function handle(): int
    {
        $base = storage_path('app/public/uploads/forum');

        if (! is_dir($base)) {
            $this->info('No hay carpeta de adjuntos del foro.');

            return self::SUCCESS;
        }

        $referencias = $this->referencias();

        $huerfanos = [];
        $total = 0;

        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterador as $archivo) {
            if (! $archivo->isFile()) {
                continue;
            }

            $relativa = 'uploads/forum/' . str_replace('\\', '/', substr($archivo->getPathname(), strlen($base) + 1));

            if (isset($referencias[$relativa])) {
                continue;
            }

            $huerfanos[$relativa] = $archivo->getSize();
            $total += $archivo->getSize();
        }

        if ($huerfanos === []) {
            $this->info('No hay archivos huerfanos.');

            return self::SUCCESS;
        }

        arsort($huerfanos);

        $this->warn(sprintf(
            '%d archivo(s) sin publicacion: %.2f MB',
            count($huerfanos),
            $total / 1048576
        ));

        foreach ($huerfanos as $ruta => $tamano) {
            $this->line(sprintf('  %-60s %8.1f MB', $ruta, $tamano / 1048576));
        }

        if (! $this->option('borrar')) {
            $this->newLine();
            $this->comment('No se ha borrado nada. Para eliminarlos: php artisan foro:huerfanos --borrar');

            return self::SUCCESS;
        }

        $borrados = 0;

        foreach (array_keys($huerfanos) as $ruta) {
            if (@unlink(storage_path('app/public/' . $ruta))) {
                $borrados++;
            }
        }

        $this->info(sprintf('Borrados %d archivo(s), %.2f MB liberados.', $borrados, $total / 1048576));

        return self::SUCCESS;
    }

    /**
     * Rutas que alguna publicacion usa.
     *
     * @return array<string, true>
     */
    private function referencias(): array
    {
        $contenido = @file_get_contents(storage_path('app/data/forum.json'));
        $datos = json_decode($contenido !== false ? $contenido : '', true);

        $posts = is_array($datos['posts'] ?? null) ? $datos['posts'] : (is_array($datos) ? $datos : []);

        $referencias = [];

        foreach ($posts as $post) {
            if (! empty($post['image_path'])) {
                $referencias[ltrim((string) $post['image_path'], '/')] = true;
            }

            foreach ((array) ($post['attachments'] ?? []) as $adjunto) {
                if (is_array($adjunto) && ! empty($adjunto['path'])) {
                    $referencias[ltrim((string) $adjunto['path'], '/')] = true;
                }
            }
        }

        return $referencias;
    }
}
