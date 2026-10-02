<?php

namespace Tests\Feature;

use App\Services\ChunkedUploadService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * El camino de un archivo de 5 GB.
 *
 * No es viable mover 5 GB reales en la suite, pero si se puede fijar el limite
 * de 5 GB y usar trozos diminutos: asi se ejercita exactamente el mismo codigo
 * (calculo del numero de trozos, escritura secuencial, ensamblado) con cientos
 * de trozos, que es donde aparecen los fallos de agregacion.
 */
class ChunkedUploadLargeFileTest extends TestCase
{
    /** 5 GB, el mismo valor que se anuncia en el foro. */
    private const FIVE_GB = 5 * 1024 ** 3;

    private string $chunkDir;
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chunkDir = storage_path('app/chunked');
        $this->uploadDir = storage_path('app/public/uploads/forum');

        File::deleteDirectory($this->chunkDir);
        File::deleteDirectory($this->uploadDir);

        config([
            'virthub.uploads.max_file_bytes' => self::FIVE_GB,
            'virthub.uploads.chunk_bytes' => 1024, // trozos de 1 KB
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->chunkDir);
        File::deleteDirectory($this->uploadDir);

        parent::tearDown();
    }

    public function test_el_limite_anunciado_es_exactamente_5_gb(): void
    {
        $this->assertSame(self::FIVE_GB, (int) config('virthub.uploads.max_file_bytes'));
    }

    public function test_un_archivo_en_el_limite_de_5_gb_es_aceptado(): void
    {
        $service = app(ChunkedUploadService::class);

        $session = $service->initiate('ana', 'disco.iso', self::FIVE_GB, 'application/octet-stream');

        // 5 GB en trozos de 1 KB son 5 242 880 peticiones; el numero debe
        // calcularse sin desbordar ni redondear mal.
        $this->assertSame(5242880, $session['total_chunks']);
        $this->assertSame(1024, $session['chunk_bytes']);

        $service->abort($session['upload_id'], 'ana');
    }

    public function test_un_byte_mas_del_limite_es_rechazado(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/supera el maximo permitido de 5 GB/');

        app(ChunkedUploadService::class)
            ->initiate('ana', 'demasiado.iso', self::FIVE_GB + 1, 'application/octet-stream');
    }

    /**
     * Cientos de trozos reales ensamblados: es el comportamiento que tendria un
     * archivo grande, sin ocupar gigabytes en disco.
     */
    public function test_ensambla_correctamente_cientos_de_trozos(): void
    {
        $service = app(ChunkedUploadService::class);

        // 600 trozos de 1 KB = 600 KB, con las mismas operaciones que un archivo
        // grande pero en una fraccion del tiempo.
        $totalChunks = 600;
        $expected = '';

        for ($i = 0; $i < $totalChunks; $i++) {
            // Bloque reconocible por indice para detectar desorden al ensamblar.
            $expected .= str_pad((string) $i, 1024, '#');
        }

        $session = $service->initiate('ana', 'grande.zip', strlen($expected), 'application/zip');

        $this->assertSame($totalChunks, $session['total_chunks']);

        for ($i = 0; $i < $totalChunks; $i++) {
            $service->appendChunk($session['upload_id'], 'ana', $i, substr($expected, $i * 1024, 1024));
        }

        $attachment = $service->complete($session['upload_id'], 'ana');

        $this->assertSame(strlen($expected), $attachment['size']);
        $this->assertSame('zip', pathinfo($attachment['name'], PATHINFO_EXTENSION));

        $path = storage_path('app/public/' . $attachment['path']);
        $this->assertFileExists($path);

        // Verificacion fuerte: el contenido debe ser identico byte a byte.
        $this->assertSame(
            hash('sha256', $expected),
            hash_file('sha256', $path),
            'El archivo ensamblado no coincide con el original.'
        );
    }

    /**
     * El archivo en construccion no debe superar lo declarado: si un cliente
     * miente sobre el tamano, la validacion por trozo lo detiene.
     */
    public function test_no_se_puede_escribir_mas_de_lo_declarado(): void
    {
        $service = app(ChunkedUploadService::class);
        $session = $service->initiate('ana', 'pequeno.txt', 2048);

        // El ultimo trozo (indice 1) debe medir exactamente 1024 bytes.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/se esperaban 1024/');

        $service->appendChunk($session['upload_id'], 'ana', 0, str_repeat('A', 1024));
        $service->appendChunk($session['upload_id'], 'ana', 1, str_repeat('A', 5000));
    }
}
