<?php

namespace App\Support;

use Throwable;

/**
 * Limpieza del escritorio de invitado.
 *
 * Los invitados comparten un unico contenedor, y su carpeta vive en el disco del
 * anfitrion (C:\webtop\ct7). La aplicacion corre en otro contenedor, asi que no
 * puede tocar esos archivos salvo que se le monte la carpeta; eso se hace en
 * docker-compose.yml como /guest-data.
 *
 * Que se borra y que no:
 *
 *   SI  documentos, descargas, imagenes, papelera, historial de VS Code,
 *       perfiles de navegador y el historial del shell. Es la informacion
 *       personal que deja un invitado.
 *
 *   NO  las aplicaciones (VS Code, Packet Tracer en /config/squashfs-root), la
 *       configuracion de MATE ni las apps de proot: si se borraran, el siguiente
 *       invitado encontraria un escritorio sin nada.
 */
class GuestWorkspace
{
    /** Donde se monta la carpeta del escritorio de invitado (ver compose). */
    private const RAIZ = '/guest-data';

    /**
     * Carpetas cuyo CONTENIDO se vacia (la carpeta se conserva).
     *
     * @var list<string>
     */
    private const CARPETAS = [
        'Desktop',
        'Documents',
        'Downloads',
        'Pictures',
        'Music',
        'Videos',
        'Public',
        'Templates',
        '.config/Code/User/History',
        '.config/Code/Backups',
        '.config/Code/User/workspaceStorage',
        '.local/share/Trash/files',
        '.local/share/Trash/info',
        '.mozilla/firefox',
        '.config/chromium',
        '.config/google-chrome',
    ];

    /**
     * Archivos sueltos que se eliminan.
     *
     * @var list<string>
     */
    private const ARCHIVOS = [
        '.bash_history',
        '.zsh_history',
        '.local/share/recently-used.xbel',
        '.config/Code/User/globalStorage/storage.json',
    ];

    /**
     * @param  string  $raiz  Carpeta del escritorio de invitado. Se puede cambiar
     *                        en las pruebas para no tocar la real.
     */
    public function __construct(private readonly string $raiz = self::RAIZ) {}

    /**
     * Indica si la carpeta del invitado esta accesible.
     */
    public function disponible(): bool
    {
        return is_dir($this->raiz);
    }

    /**
     * Borra la informacion personal del escritorio de invitado.
     *
     * Devuelve las rutas que se vaciaron o eliminaron, util para registrar y
     * para las pruebas.
     *
     * @return list<string>
     */
    public function limpiar(): array
    {
        if (! $this->disponible()) {
            return [];
        }

        $tocadas = [];

        foreach (self::CARPETAS as $relativa) {
            $ruta = $this->raiz . '/' . $relativa;

            if (! is_dir($ruta)) {
                continue;
            }

            $this->vaciar($ruta);
            $tocadas[] = $relativa;
        }

        foreach (self::ARCHIVOS as $relativa) {
            $ruta = $this->raiz . '/' . $relativa;

            if (is_file($ruta) && @unlink($ruta)) {
                $tocadas[] = $relativa;
            }
        }

        return $tocadas;
    }

    /**
     * Borra el contenido de una carpeta, sin borrar la carpeta.
     */
    private function vaciar(string $carpeta): void
    {
        $elementos = @scandir($carpeta);

        if (! is_array($elementos)) {
            return;
        }

        foreach ($elementos as $elemento) {
            if ($elemento === '.' || $elemento === '..') {
                continue;
            }

            $ruta = $carpeta . '/' . $elemento;

            // Si algo no se deja borrar (un archivo en uso por el escritorio), se
            // sigue con lo demas: es preferible limpiar el resto que abortar.
            try {
                is_dir($ruta) && ! is_link($ruta)
                    ? $this->borrarArbol($ruta)
                    : @unlink($ruta);
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function borrarArbol(string $ruta): void
    {
        $elementos = @scandir($ruta);

        if (is_array($elementos)) {
            foreach ($elementos as $elemento) {
                if ($elemento === '.' || $elemento === '..') {
                    continue;
                }

                $hijo = $ruta . '/' . $elemento;

                is_dir($hijo) && ! is_link($hijo)
                    ? $this->borrarArbol($hijo)
                    : @unlink($hijo);
            }
        }

        @rmdir($ruta);
    }
}
