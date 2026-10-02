<?php

namespace Tests\Feature;

use App\Support\AttachmentStorage;
use Tests\TestCase;

/**
 * Subida de avatar y adjuntos directos a storage/app/public.
 *
 * El fallo real: los adjuntos se movieron al volumen persistente y el proceso
 * de Apache corre como www-data. Si los directorios los crea root, la subida
 * falla con "mkdir(): Permission denied" y la pagina devuelve 500. Se corrige
 * preparando los directorios con el propietario correcto al arrancar el
 * contenedor.
 *
 * Aqui se comprueba lo verificable sin permisos de escritura en el host: que el
 * contrato de rutas es correcto y que el Dockerfile prepara los directorios. La
 * comprobacion de permisos reales se hace dentro del contenedor.
 */
class AvatarUploadContractTest extends TestCase
{
    /**
     * Subdirectorios que la aplicacion necesita poder escribir. Si falta alguno,
     * la subida correspondiente falla con error 500.
     *
     * @return array<int, string>
     */
    private function requiredDirectories(): array
    {
        return [
            'uploads/profiles',
            'uploads/forum/photos',
            'uploads/forum/videos',
            'uploads/forum/files',
        ];
    }

    public function test_el_dockerfile_prepara_los_directorios_de_subida(): void
    {
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

        foreach ($this->requiredDirectories() as $dir) {
            $this->assertStringContainsString(
                'storage/app/public/' . $dir,
                $dockerfile,
                "El Dockerfile debe preparar storage/app/public/{$dir} con el propietario correcto."
            );
        }
    }

    public function test_el_arranque_del_contenedor_ajusta_el_propietario(): void
    {
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

        // Sin este chown en el arranque, un volumen nuevo deja los directorios
        // como root y www-data no puede escribir dentro.
        $this->assertStringContainsString('chown -R www-data:www-data storage', $dockerfile);
    }

    public function test_las_rutas_de_avatar_apuntan_al_almacenamiento_persistente(): void
    {
        // El controlador debe escribir en storage/app/public, no en public/,
        // que vive en la capa efimera del contenedor.
        $controller = (string) file_get_contents(
            base_path('app/Http/Controllers/ProfileController.php')
        );

        $this->assertStringContainsString("storage_path('app/public/uploads/profiles')", $controller);
        $this->assertStringNotContainsString("public_path('uploads/profiles')", $controller);
    }

    public function test_el_controlador_del_foro_usa_el_almacenamiento_persistente(): void
    {
        $controller = (string) file_get_contents(
            base_path('app/Http/Controllers/ForumController.php')
        );

        $this->assertStringContainsString("storage_path('app/public/uploads/forum/", $controller);
        $this->assertStringNotContainsString("public_path('uploads/forum/", $controller);
    }

    public function test_attachment_storage_resuelve_las_rutas_de_avatar(): void
    {
        // La ruta relativa guardada debe resolverse dentro de storage/app/public.
        $path = AttachmentStorage::path('uploads/profiles/avatar.jpg');

        $this->assertNotNull($path);
        $this->assertStringContainsString('storage', $path);
        $this->assertStringContainsString('app', $path);
        $this->assertStringContainsString('uploads', $path);
        $this->assertStringContainsString('avatar.jpg', $path);
    }

    public function test_un_avatar_inexistente_no_se_pinta_como_imagen(): void
    {
        // Es el caso que provocaba el texto alternativo roto en la interfaz.
        $this->assertFalse(AttachmentStorage::exists('uploads/profiles/no-existe.jpg'));
    }
}
