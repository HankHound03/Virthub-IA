<?php

namespace Tests\Feature;

use App\Services\ChunkedUploadService;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * Subidas por trozos para archivos grandes.
 *
 * El caso de uso es subir archivos de hasta 5 GB, que no caben en una sola
 * peticion de PHP. El servicio recibe trozos, los escribe a disco y ensambla
 * el archivo final.
 */
class ChunkedUploadTest extends TestCase
{
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
            'virthub.uploads.chunk_bytes' => 1024,          // trozos de 1 KB
            'virthub.uploads.max_file_bytes' => 1024 * 100, // 100 KB de maximo
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->chunkDir);
        File::deleteDirectory($this->uploadDir);

        parent::tearDown();
    }

    private function service(): ChunkedUploadService
    {
        return app(ChunkedUploadService::class);
    }

    /** Trocea y sube un contenido completo, como haria el cliente. */
    private function uploadAll(string $owner, string $name, string $content): array
    {
        $service = $this->service();
        $session = $service->initiate($owner, $name, strlen($content), 'text/plain');

        $chunkBytes = (int) $session['chunk_bytes'];
        $total = (int) $session['total_chunks'];

        for ($i = 0; $i < $total; $i++) {
            $service->appendChunk($session['upload_id'], $owner, $i, substr($content, $i * $chunkBytes, $chunkBytes));
        }

        return [$session['upload_id'], $service->complete($session['upload_id'], $owner)];
    }

    public function test_ensambla_un_archivo_a_partir_de_varios_trozos(): void
    {
        $content = str_repeat('A', 3500); // 4 trozos de 1 KB (el ultimo parcial)

        [$uploadId, $attachment] = $this->uploadAll('ana', 'notas.txt', $content);

        $this->assertSame('notas.txt', $attachment['name']);
        $this->assertSame(strlen($content), $attachment['size']);
        $this->assertSame('file', $attachment['type']);

        $fullPath = storage_path('app/public/' . $attachment['path']);
        $this->assertFileExists($fullPath);
        $this->assertSame($content, file_get_contents($fullPath));

        // El temporal se limpia al mover el archivo.
        $this->assertFileDoesNotExist($this->chunkDir . '/' . $uploadId . '/part.bin');
    }

    public function test_clasifica_imagenes_y_videos_en_sus_carpetas(): void
    {
        [, $imagen] = $this->uploadAll('ana', 'foto.png', str_repeat('I', 2048));
        [, $video] = $this->uploadAll('ana', 'clip.mp4', str_repeat('V', 2048));

        $this->assertSame('photo', $imagen['type']);
        $this->assertStringContainsString('uploads/forum/photos/', $imagen['path']);

        $this->assertSame('video', $video['type']);
        $this->assertStringContainsString('uploads/forum/videos/', $video['path']);
    }

    public function test_rechaza_archivos_que_superan_el_maximo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/supera el maximo/');

        $this->service()->initiate('ana', 'enorme.bin', 1024 * 1024);
    }

    public function test_rechaza_extensiones_no_permitidas(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no esta permitida/');

        $this->service()->initiate('ana', 'malware.exe', 2048);
    }

    public function test_exige_que_los_trozos_lleguen_en_orden(): void
    {
        $service = $this->service();
        $session = $service->initiate('ana', 'datos.txt', 3072);

        // Saltarse el trozo 0 y enviar el 1 debe fallar.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/fuera de orden/');

        $service->appendChunk($session['upload_id'], 'ana', 1, str_repeat('B', 1024));
    }

    public function test_rechaza_un_trozo_con_tamano_incorrecto(): void
    {
        $service = $this->service();
        $session = $service->initiate('ana', 'datos.txt', 3072);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/se esperaban 1024/');

        $service->appendChunk($session['upload_id'], 'ana', 0, str_repeat('B', 500));
    }

    public function test_no_permite_que_otro_usuario_continue_una_subida(): void
    {
        $service = $this->service();
        $session = $service->initiate('ana', 'datos.txt', 2048);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no te pertenece/');

        $service->appendChunk($session['upload_id'], 'intruso', 0, str_repeat('B', 1024));
    }

    public function test_no_se_puede_completar_si_faltan_trozos(): void
    {
        $service = $this->service();
        $session = $service->initiate('ana', 'datos.txt', 2048);
        $service->appendChunk($session['upload_id'], 'ana', 0, str_repeat('B', 1024));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Faltan trozos/');

        $service->complete($session['upload_id'], 'ana');
    }

    public function test_completar_dos_veces_devuelve_el_mismo_resultado(): void
    {
        [$uploadId, $primero] = $this->uploadAll('ana', 'datos.txt', str_repeat('C', 2048));
        $segundo = $this->service()->complete($uploadId, 'ana');

        // Un reintento del cliente no debe duplicar el archivo.
        $this->assertSame($primero['path'], $segundo['path']);
    }

    public function test_los_recibos_se_canjean_por_el_adjunto_real(): void
    {
        [$uploadId, $attachment] = $this->uploadAll('ana', 'datos.txt', str_repeat('D', 2048));

        $resolved = $this->service()->consumeReceipts([$uploadId], 'ana', 10);

        $this->assertCount(1, $resolved);
        $this->assertSame($attachment['path'], $resolved[0]['path']);
        $this->assertSame($attachment['size'], $resolved[0]['size']);
    }

    public function test_no_se_puede_canjear_el_recibo_de_otro_usuario(): void
    {
        [$uploadId] = $this->uploadAll('ana', 'datos.txt', str_repeat('E', 2048));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no te pertenece/');

        $this->service()->consumeReceipts([$uploadId], 'intruso', 10);
    }

    public function test_un_recibo_inexistente_es_rechazado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no existe o expiro/');

        $this->service()->consumeReceipts([str_repeat('a', 32)], 'ana', 10);
    }

    public function test_se_respeta_el_maximo_de_archivos_por_publicacion(): void
    {
        // Dos subidas distintas: el limite debe saltar al canjearlas juntas.
        [$uno] = $this->uploadAll('ana', 'uno.txt', str_repeat('F', 2048));
        [$dos] = $this->uploadAll('ana', 'dos.txt', str_repeat('F', 2048));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/como maximo 1 archivos/');

        $this->service()->consumeReceipts([$uno, $dos], 'ana', 1);
    }

    public function test_abortar_libera_el_espacio_de_una_subida_en_curso(): void
    {
        $service = $this->service();
        $session = $service->initiate('ana', 'datos.txt', 2048);
        $service->appendChunk($session['upload_id'], 'ana', 0, str_repeat('G', 1024));

        $dir = $this->chunkDir . '/' . $session['upload_id'];
        $this->assertDirectoryExists($dir);

        $service->abort($session['upload_id'], 'ana');

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function test_la_limpieza_solo_borra_subidas_incompletas_y_antiguas(): void
    {
        $service = $this->service();

        // Subida completada: debe sobrevivir como recibo.
        [$completada] = $this->uploadAll('ana', 'hecha.txt', str_repeat('H', 2048));

        // Subida abandonada y antigua: debe borrarse.
        $abandonada = $service->initiate('ana', 'abandonada.txt', 2048);
        $abandonadaDir = $this->chunkDir . '/' . $abandonada['upload_id'];
        $metaPath = $abandonadaDir . '/meta.json';
        $meta = json_decode((string) file_get_contents($metaPath), true);
        $meta['updated_at_ts'] = time() - (72 * 3600);
        file_put_contents($metaPath, json_encode($meta));

        $pruned = $service->pruneExpiredSessions();

        $this->assertSame(1, $pruned);
        $this->assertDirectoryDoesNotExist($abandonadaDir);
        $this->assertDirectoryExists($this->chunkDir . '/' . $completada);
    }
}
