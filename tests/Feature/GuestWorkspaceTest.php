<?php

namespace Tests\Feature;

use App\Support\GuestWorkspace;
use Tests\TestCase;

/**
 * Limpieza del escritorio compartido de invitados.
 *
 * Se prueba contra una carpeta temporal, nunca contra C:\webtop\ct7: borrar los
 * datos reales durante una prueba seria un desastre.
 */
class GuestWorkspaceTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir() . '/virthub-guest-' . bin2hex(random_bytes(4));

        foreach ([
            'Desktop',
            'Documents',
            'Downloads',
            '.config/Code/User/History/abc123',
            '.config/mate',
            '.local/bin',
            '.local/share',
            'squashfs-root',
        ] as $carpeta) {
            mkdir($this->raiz . '/' . $carpeta, 0777, true);
        }

        file_put_contents($this->raiz . '/Desktop/notas.txt', 'del invitado');
        file_put_contents($this->raiz . '/Documents/tarea.docx', 'del invitado');
        file_put_contents($this->raiz . '/.config/Code/User/History/abc123/entradas.json', '{}');
        file_put_contents($this->raiz . '/.bash_history', 'comandos');
        file_put_contents($this->raiz . '/.local/share/recently-used.xbel', '<xml/>');

        // Lo que NO se debe tocar:
        file_put_contents($this->raiz . '/.config/mate/paneles.conf', 'configuracion de MATE');
        file_put_contents($this->raiz . '/.local/bin/packettracer', 'app de proot');
        file_put_contents($this->raiz . '/squashfs-root/AppRun', 'packet tracer');
    }

    protected function tearDown(): void
    {
        $this->borrarTodo($this->raiz);

        parent::tearDown();
    }

    private function borrarTodo(string $ruta): void
    {
        if (! is_dir($ruta)) {
            return;
        }

        foreach (scandir($ruta) ?: [] as $elemento) {
            if ($elemento === '.' || $elemento === '..') {
                continue;
            }

            $hijo = $ruta . '/' . $elemento;
            is_dir($hijo) && ! is_link($hijo) ? $this->borrarTodo($hijo) : @unlink($hijo);
        }

        @rmdir($ruta);
    }

    public function test_borra_la_informacion_personal(): void
    {
        (new GuestWorkspace($this->raiz))->limpiar();

        // Se va el contenido...
        $this->assertFileDoesNotExist($this->raiz . '/Desktop/notas.txt');
        $this->assertFileDoesNotExist($this->raiz . '/Documents/tarea.docx');
        $this->assertFileDoesNotExist($this->raiz . '/.config/Code/User/History/abc123/entradas.json');
        $this->assertFileDoesNotExist($this->raiz . '/.bash_history');
        $this->assertFileDoesNotExist($this->raiz . '/.local/share/recently-used.xbel');

        // ...pero las carpetas siguen, para que el escritorio las encuentre.
        $this->assertDirectoryExists($this->raiz . '/Desktop');
        $this->assertDirectoryExists($this->raiz . '/Documents');
    }

    public function test_conserva_las_aplicaciones_y_la_configuracion(): void
    {
        (new GuestWorkspace($this->raiz))->limpiar();

        // Si se borraran, el siguiente invitado encontraria un escritorio vacio.
        $this->assertFileExists($this->raiz . '/squashfs-root/AppRun');
        $this->assertFileExists($this->raiz . '/.local/bin/packettracer');
        $this->assertFileExists($this->raiz . '/.config/mate/paneles.conf');
    }

    public function test_sin_carpeta_no_falla(): void
    {
        $espacio = new GuestWorkspace($this->raiz . '/no-existe');

        $this->assertFalse($espacio->disponible());
        $this->assertSame([], $espacio->limpiar());
    }

    public function test_devuelve_lo_que_limpio(): void
    {
        $tocadas = (new GuestWorkspace($this->raiz))->limpiar();

        $this->assertContains('Desktop', $tocadas);
        $this->assertContains('Documents', $tocadas);
        $this->assertNotContains('squashfs-root', $tocadas);
    }
}
