<?php

namespace Tests\Feature;

use App\Services\ForumStore;
use App\Services\JsonUserStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Borrado de publicaciones con adjuntos.
 *
 * Al eliminar una publicacion hay que borrar tambien sus archivos del disco. Antes
 * solo se borraba el campo antiguo 'image_path', que las publicaciones nuevas dejan
 * en null: los archivos pesados se quedaban en el servidor para siempre.
 */
class ForumAttachmentDeletionTest extends TestCase
{
    private string $uploadDir;

    private string $usersFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadDir = storage_path('app/public/uploads/forum');
        $this->usersFile = storage_path('app/data/users.json');

        File::deleteDirectory($this->uploadDir);

        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        app(JsonUserStore::class)->createOrUpdateAdmin('ana', 'P@ssword123!');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->uploadDir);
        @unlink($this->usersFile);

        parent::tearDown();
    }

    public function test_al_borrar_la_publicacion_se_borran_sus_archivos(): void
    {
        // Un archivo pesado (por ejemplo, subido por trozos) ya en su sitio.
        $ruta = $this->uploadDir . '/files/pesado.bin';
        File::ensureDirectoryExists(dirname($ruta));
        file_put_contents($ruta, str_repeat('Z', 2048));

        $post = app(ForumStore::class)->addPost('ana', 'Publicacion con archivo', null, null, [
            [
                'type' => 'file',
                'name' => 'pesado.bin',
                'mime' => 'application/octet-stream',
                'path' => 'uploads/forum/files/pesado.bin',
            ],
        ]);

        $this->assertTrue(is_file($ruta), 'El archivo debe existir antes de borrar.');

        $this->withSession(['auth_user' => ['username' => 'ana', 'role' => 'admin']])
            ->post('/foro/' . $post['id'] . '/delete')
            ->assertRedirect();

        $this->assertFalse(is_file($ruta), 'El archivo debe desaparecer del servidor.');
    }

    public function test_una_publicacion_sin_adjuntos_se_borra_igual(): void
    {
        $post = app(ForumStore::class)->addPost('ana', 'Solo texto');

        $this->withSession(['auth_user' => ['username' => 'ana', 'role' => 'admin']])
            ->post('/foro/' . $post['id'] . '/delete')
            ->assertRedirect();

        $this->assertNull(app(ForumStore::class)->findById($post['id']));
    }
}
