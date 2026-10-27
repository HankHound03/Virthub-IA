<?php

namespace Tests\Feature;

use App\Services\AvisoStore;
use App\Services\JsonUserStore;
use App\Services\ModerationService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Moderacion de las fotos del foro con NSFW Sherlock.
 *
 * Se cubren las tres decisiones que importan: una foto apta pasa, una no apta se
 * retira y avisa a las dos partes, y si el servicio no responde la subida no se
 * rompe (se deja pasar y se anota el fallo).
 */
class ModerationTest extends TestCase
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

        // El endpoint exige un usuario real registrado: se siembra uno.
        $usuarios = storage_path('app/data/users.json');

        if (is_file($usuarios)) {
            unlink($usuarios);
        }

        app(JsonUserStore::class)->createOrUpdateAdmin('ana', 'P@ssword123!');

        config([
            'virthub.uploads.chunk_bytes' => 1024,
            'virthub.uploads.max_file_bytes' => 1024 * 100,
            'virthub.moderacion.enabled' => true,
            'virthub.moderacion.url' => 'http://nsfw-sherlock:4000',
            'virthub.moderacion.umbral' => 0.75,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->chunkDir);
        File::deleteDirectory($this->uploadDir);

        // Los avisos y los usuarios son JSON reales: se limpian entre tests.
        @unlink(storage_path('app/data/avisos.json'));
        @unlink(storage_path('app/data/users.json'));

        parent::tearDown();
    }

    /** Respuesta de Sherlock para una imagen apta. */
    private function respuestaApta(): array
    {
        return [
            'verdict' => 'sfw',
            'nsfw' => 0.0003,
            'labels' => ['porn' => 0.00001, 'hentai' => 0.0003, 'neutral' => 0.99],
            'detections' => [],
            'status' => 'ok',
        ];
    }

    /** Respuesta de Sherlock para una imagen no apta. */
    private function respuestaProhibida(): array
    {
        return [
            'verdict' => 'nsfw',
            'nsfw' => 0.94,
            'labels' => ['porn' => 0.94, 'hentai' => 0.02, 'neutral' => 0.04],
            'detections' => [['label' => 'exposed', 'score' => 0.91]],
            'status' => 'ok',
        ];
    }

    public function test_una_foto_apta_se_acepta(): void
    {
        Http::fake([
            '*/pic/analyze' => Http::response($this->respuestaApta(), 200),
        ]);
        $session = $this->initiate('foto.jpg', 1024);
        $this->subirTrozo($session['upload_id'], 0, str_repeat('A', 1024));

        $r = $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete');

        $r->assertCreated()
            ->assertJsonPath('attachment.type', 'photo');
    }

    public function test_una_foto_prohibida_se_retira_y_avisa_al_usuario_y_al_admin(): void
    {
        Http::fake([
            '*/pic/analyze' => Http::response($this->respuestaProhibida(), 200),
        ]);
        $session = $this->initiate('foto.jpg', 1024);
        $this->subirTrozo($session['upload_id'], 0, str_repeat('A', 1024));

        $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertStatus(422)
            ->assertJsonPath('moderacion.retirada', true);

        // El archivo no debe quedar en disco.
        $this->assertSame([], File::glob($this->uploadDir . '/photos/*') ?: []);

        $avisos = app(AvisoStore::class);

        $delUsuario = $avisos->para('ana');
        $delAdmin = $avisos->para('admin');

        $this->assertCount(1, $delUsuario, 'El usuario debe recibir un aviso.');
        $this->assertStringContainsString('no permitido', $delUsuario[0]['mensaje']);

        $this->assertCount(1, $delAdmin, 'El administrador debe recibir un aviso.');
        $this->assertStringContainsString('ana', $delAdmin[0]['mensaje']);
    }

    public function test_un_documento_no_se_revisa(): void
    {
        Http::fake();

        $session = $this->initiate('notas.txt', 1024);
        $this->subirTrozo($session['upload_id'], 0, str_repeat('Z', 1024));

        $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertCreated();

        Http::assertNothingSent();
    }

    public function test_si_el_servicio_no_responde_la_subida_sigue(): void
    {
        Http::fake([
            '*/pic/analyze' => Http::response('', 500),
        ]);
        $session = $this->initiate('foto.jpg', 1024);
        $this->subirTrozo($session['upload_id'], 0, str_repeat('A', 1024));

        // Se deja pasar: es preferible a bloquear todas las subidas por una caida.
        $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertCreated();
    }

    public function test_el_veredicto_manda_sobre_la_puntuacion(): void
    {
        $servicio = app(ModerationService::class);

        Http::fake([
            '*/pic/analyze' => Http::response([
                'verdict' => 'nsfw',
                'nsfw' => 0.1,
                'detections' => [],
            ], 200),
        ]);

        $ruta = storage_path('app/public/uploads/_prueba.jpg');
        @mkdir(dirname($ruta), 0755, true);
        file_put_contents($ruta, 'contenido');

        $resultado = $servicio->revisarImagen($ruta, 'prueba.jpg');

        $this->assertTrue($resultado['prohibida'], 'El veredicto nsfw debe pesar mas que la puntuacion.');

        @unlink($ruta);
    }

    private function authSession(string $username = 'ana', string $role = 'admin'): array
    {
        return ['auth_user' => ['username' => $username, 'role' => $role]];
    }

    private function initiate(string $name, int $size): array
    {
        return $this->withSession($this->authSession())
            ->postJson('/attachments/initiate', ['filename' => $name, 'size' => $size, 'mime' => 'text/plain'])
            ->assertCreated()
            ->json();
    }

    private function subirTrozo(string $uploadId, int $index, string $contenido): void
    {
        $this->withSession($this->authSession())
            ->call('POST', '/attachments/' . $uploadId . '/chunk/' . $index, [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], $contenido)
            ->assertOk();
    }
}