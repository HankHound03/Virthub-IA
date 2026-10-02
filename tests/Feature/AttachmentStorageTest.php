<?php

namespace Tests\Feature;

use App\Support\AttachmentStorage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Comprobacion de existencia de adjuntos.
 *
 * Existe porque los registros guardan rutas relativas y la interfaz las pintaba
 * siempre con asset(), sin verificar que el archivo siguiera en disco. Cuando
 * faltaba, el navegador mostraba el texto alternativo y la pagina se veia rota.
 */
class AttachmentStorageTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = storage_path('app/public');
        File::deleteDirectory($this->publicDir . '/uploads');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir . '/uploads');

        parent::tearDown();
    }

    private function writeUpload(string $relativePath, string $content = 'x'): void
    {
        $full = $this->publicDir . '/' . $relativePath;

        File::ensureDirectoryExists(dirname($full));
        file_put_contents($full, $content);
    }

    public function test_detecta_un_adjunto_que_existe(): void
    {
        $this->writeUpload('uploads/forum/photos/foto.jpg', str_repeat('A', 2048));

        $this->assertTrue(AttachmentStorage::exists('uploads/forum/photos/foto.jpg'));
    }

    public function test_detecta_un_adjunto_que_falta(): void
    {
        // Este es el caso real: el registro apunta al archivo pero el archivo se
        // perdio al recrear el contenedor.
        $this->assertFalse(AttachmentStorage::exists('uploads/forum/photos/perdida.jpg'));
    }

    public function test_una_ruta_vacia_o_nula_no_existe(): void
    {
        $this->assertFalse(AttachmentStorage::exists(''));
        $this->assertFalse(AttachmentStorage::exists(null));
        $this->assertFalse(AttachmentStorage::exists('   '));
    }

    public function test_rechaza_rutas_que_intentan_salir_del_directorio_de_subidas(): void
    {
        // Una ruta manipulada no debe permitir consultar fuera de storage/app/public.
        $this->assertNull(AttachmentStorage::path('../../../.env'));
        $this->assertNull(AttachmentStorage::path('uploads/../../.env'));
        $this->assertFalse(AttachmentStorage::exists('../../../.env'));
    }

    public function test_tolera_barras_invertidas_y_barras_iniciales(): void
    {
        $this->writeUpload('uploads/profiles/avatar.png');

        $this->assertTrue(AttachmentStorage::exists('/uploads/profiles/avatar.png'));
        $this->assertTrue(AttachmentStorage::exists('\\uploads\\profiles\\avatar.png'));
    }

    public function test_calcula_el_tamano_legible(): void
    {
        $this->writeUpload('uploads/forum/files/informe.pdf', str_repeat('B', 2048));

        $this->assertSame('2 KB', AttachmentStorage::humanSize('uploads/forum/files/informe.pdf'));
    }

    public function test_usa_el_tamano_guardado_cuando_el_archivo_falta(): void
    {
        // El tamano del registro sirve de referencia aunque el archivo ya no este.
        $this->assertSame('5 MB', AttachmentStorage::humanSize('uploads/no-existe.bin', 5242880));
        $this->assertNull(AttachmentStorage::humanSize('uploads/no-existe.bin', 0));
    }
}
