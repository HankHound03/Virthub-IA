<?php

namespace Tests\Feature;

use App\Services\JsonUserStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Endpoints HTTP de la subida por trozos.
 *
 * Complementa a ChunkedUploadTest, que cubre el servicio: aqui se verifica el
 * recorrido completo por las rutas y el controlador, incluido el control de
 * acceso, que es donde un fallo permitiria a un usuario tocar archivos ajenos.
 */
class ChunkedUploadEndpointTest extends TestCase
{
    private string $chunkDir;
    private string $uploadDir;
    private string $usersFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chunkDir = storage_path('app/chunked');
        $this->uploadDir = storage_path('app/public/uploads/forum');
        $this->usersFile = storage_path('app/data/users.json');

        File::deleteDirectory($this->chunkDir);
        File::deleteDirectory($this->uploadDir);

        // RefreshDatabase no limpia el almacen JSON de usuarios, asi que cada
        // test parte de un fichero vacio para no arrastrar cuentas previas.
        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        config(['virthub.uploads.chunk_bytes' => 1024]);

        $users = app(JsonUserStore::class);
        $users->createOrUpdateAdmin('ana', 'P@ssword123!');
        $users->createUser('intruso', 'P@ssword123!', 'user');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->chunkDir);
        File::deleteDirectory($this->uploadDir);

        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        parent::tearDown();
    }

    /** @return array<string, string> */
    private function authSession(string $username = 'ana', string $role = 'admin'): array
    {
        return ['auth_user' => ['username' => $username, 'role' => $role]];
    }

    private function initiate(string $name, int $size, string $username = 'ana', string $role = 'admin'): array
    {
        return $this->withSession($this->authSession($username, $role))
            ->postJson('/attachments/initiate', ['filename' => $name, 'size' => $size, 'mime' => 'text/plain'])
            ->assertCreated()
            ->json();
    }

    public function test_el_recorrido_completo_crea_el_adjunto(): void
    {
        $content = str_repeat('Z', 3072);
        $session = $this->initiate('notas.txt', strlen($content));

        $this->assertArrayHasKey('upload_id', $session);
        $this->assertSame(1024, $session['chunk_bytes']);
        $this->assertSame(3, $session['total_chunks']);

        for ($i = 0; $i < 3; $i++) {
            $this->withSession($this->authSession())
                ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/' . $i, [], [], [], [
                    'CONTENT_TYPE' => 'application/octet-stream',
                ], substr($content, $i * 1024, 1024))
                ->assertOk();
        }

        $completed = $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertCreated()
            ->json();

        $this->assertSame($session['upload_id'], $completed['upload_id']);
        $this->assertSame('notas.txt', $completed['attachment']['name']);
        $this->assertSame(3072, $completed['attachment']['size']);
        $this->assertFileExists(storage_path('app/public/' . $completed['attachment']['path']));
    }

    public function test_las_rutas_de_adjuntos_exigen_sesion(): void
    {
        $this->postJson('/attachments/initiate', ['filename' => 'x.txt', 'size' => 10])
            ->assertStatus(403);

        $this->call('POST', '/attachments/' . str_repeat('a', 32) . '/chunk/0', [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'datos')->assertStatus(403);

        $this->postJson('/attachments/' . str_repeat('a', 32) . '/complete')->assertStatus(403);
    }

    public function test_un_invitado_no_puede_iniciar_una_subida(): void
    {
        $this->withSession(['auth_user' => ['username' => 'guest_abc123', 'role' => 'guest']])
            ->postJson('/attachments/initiate', ['filename' => 'x.txt', 'size' => 10])
            ->assertStatus(403);
    }

    public function test_rechaza_un_archivo_que_supera_el_maximo_anunciado(): void
    {
        $max = (int) config('virthub.uploads.max_file_bytes');

        $this->withSession($this->authSession())
            ->postJson('/attachments/initiate', [
                'filename' => 'enorme.zip',
                'size' => $max + 1,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'El archivo supera el maximo permitido de 5 GB.']);
    }

    public function test_rechaza_una_extension_no_permitida(): void
    {
        $this->withSession($this->authSession())
            ->postJson('/attachments/initiate', [
                'filename' => 'malware.exe',
                'size' => 2048,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'La extension .exe no esta permitida.');
    }

    public function test_otro_usuario_no_puede_subir_trozos_de_una_sesion_ajena(): void
    {
        $session = $this->initiate('notas.txt', 2048);

        $this->withSession($this->authSession('intruso', 'user'))
            ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/0', [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], str_repeat('X', 1024))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'La subida no existe o no te pertenece.']);
    }

    public function test_un_trozo_fuera_de_orden_devuelve_error_claro(): void
    {
        $session = $this->initiate('notas.txt', 3072);

        $this->withSession($this->authSession())
            ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/2', [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], str_repeat('X', 1024))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Trozo fuera de orden: se esperaba el 0 y llego el 2.');
    }

    public function test_completar_sin_todos_los_trozos_es_rechazado(): void
    {
        $session = $this->initiate('notas.txt', 2048);

        $this->withSession($this->authSession())
            ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/0', [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], str_repeat('X', 1024))
            ->assertOk();

        $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Faltan trozos: 1 de 2.']);
    }

    public function test_abortar_una_subida_la_elimina(): void
    {
        $session = $this->initiate('notas.txt', 2048);
        $dir = $this->chunkDir . '/' . $session['upload_id'];

        $this->assertDirectoryExists($dir);

        $this->withSession($this->authSession())
            ->deleteJson('/attachments/' . $session['upload_id'])
            ->assertOk()
            ->assertJson(['aborted' => true]);

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function test_publicar_en_el_foro_con_un_recibo_adjunta_el_archivo(): void
    {
        $content = str_repeat('Q', 2048);
        $session = $this->initiate('informe.txt', strlen($content));

        for ($i = 0; $i < 2; $i++) {
            $this->withSession($this->authSession())
                ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/' . $i, [], [], [], [
                    'CONTENT_TYPE' => 'application/octet-stream',
                ], substr($content, $i * 1024, 1024))
                ->assertOk();
        }

        $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertCreated();

        // Publicar presentando el recibo, no una ruta de archivo.
        $this->withSession($this->authSession())
            ->post('/foro', [
                'content' => 'Publicacion con adjunto grande',
                'upload_ids' => [$session['upload_id']],
            ])
            ->assertRedirect('/foro');

        $posts = app(\App\Services\ForumStore::class)->latestPosts(5);
        $this->assertNotEmpty($posts);

        $attachments = $posts[0]['attachments'] ?? [];
        $this->assertCount(1, $attachments);
        $this->assertSame('informe.txt', $attachments[0]['name']);
        $this->assertSame(2048, $attachments[0]['size']);
    }

    public function test_no_se_puede_publicar_con_el_recibo_de_otro_usuario(): void
    {
        $session = $this->initiate('ajeno.txt', 2048);

        $this->withSession($this->authSession())
            ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/0', [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], str_repeat('X', 1024))
            ->assertOk();

        $this->withSession($this->authSession())
            ->call('POST', '/attachments/' . $session['upload_id'] . '/chunk/1', [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], str_repeat('X', 1024))
            ->assertOk();

        $this->withSession($this->authSession())
            ->postJson('/attachments/' . $session['upload_id'] . '/complete')
            ->assertCreated();

        $this->withSession($this->authSession('intruso', 'user'))
            ->post('/foro', [
                'content' => 'Intento de robo de adjunto',
                'upload_ids' => [$session['upload_id']],
            ])
            ->assertRedirect('/foro')
            ->assertSessionHas('error');
    }
}
