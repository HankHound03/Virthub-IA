<?php

namespace Tests\Feature;

use App\Support\AttachmentStorage;
use Tests\TestCase;

/**
 * URLs publicas de los adjuntos.
 *
 * Los archivos viven en storage/app/public, que NO esta dentro del document root
 * de Apache: se sirven por el enlace public/storage -> storage/app/public. Si se
 * genera la URL sin el prefijo 'storage/', el navegador pide /uploads/... y el
 * servidor responde 404: es exactamente el fallo que dejaba las imagenes rotas.
 */
class AttachmentUrlTest extends TestCase
{
    public function test_la_url_lleva_el_prefijo_del_enlace_de_storage(): void
    {
        $url = AttachmentStorage::url('uploads/profiles/avatar.jpg');

        $this->assertStringContainsString('/storage/uploads/profiles/avatar.jpg', $url);
    }

    public function test_no_duplica_el_prefijo_si_la_ruta_ya_lo_trae(): void
    {
        // Rutas guardadas por versiones anteriores ya incluian 'storage/'.
        $url = AttachmentStorage::url('storage/uploads/profiles/avatar.jpg');

        $this->assertStringContainsString('/storage/uploads/profiles/avatar.jpg', $url);
        $this->assertStringNotContainsString('/storage/storage/', $url);
    }

    public function test_tolera_barras_iniciales_y_barras_invertidas(): void
    {
        foreach (['/uploads/x.png', '\\uploads\\x.png', 'uploads/x.png'] as $entrada) {
            $url = AttachmentStorage::url($entrada);
            $this->assertStringContainsString('/storage/uploads/x.png', $url, "Fallo con: {$entrada}");
            $this->assertStringNotContainsString('//storage', $url);
        }
    }

    public function test_una_ruta_vacia_no_genera_url(): void
    {
        $this->assertSame('', AttachmentStorage::url(''));
        $this->assertSame('', AttachmentStorage::url('   '));
    }

    public function test_la_url_apunta_al_archivo_que_path_resuelve(): void
    {
        // Coherencia entre lo que existe en disco y lo que se sirve: Apache sirve
        // storage/app/public mediante el enlace public/storage.
        $relativa = 'uploads/profiles/coherencia.jpg';

        $this->assertStringContainsString('storage/', AttachmentStorage::url($relativa));

        $path = (string) AttachmentStorage::path($relativa);

        // storage_path() usa barras normales incluso en Windows.
        $this->assertStringContainsString('app/public/uploads/profiles/coherencia.jpg', $path);
        $this->assertStringContainsString('storage', $path);
    }

    public function test_las_vistas_usan_el_helper_y_no_asset_directo(): void
    {
        // Si alguna vista vuelve a usar asset() sobre una ruta relativa, las
        // imagenes se rompen con un 404 sin ningun aviso.
        foreach (['forum', 'perfil', 'partials/header'] as $vista) {
            $contenido = (string) file_get_contents(resource_path("views/{$vista}.blade.php"));

            $this->assertStringNotContainsString(
                "asset(\$attachment['path'])",
                $contenido,
                "{$vista} debe usar AttachmentStorage::url() para los adjuntos."
            );
            $this->assertStringNotContainsString(
                "asset(\$post['image_path'])",
                $contenido,
                "{$vista} debe usar AttachmentStorage::url() para las imagenes de publicacion."
            );
        }

        $header = (string) file_get_contents(resource_path('views/partials/header.blade.php'));
        $this->assertStringNotContainsString('asset($profileImage)', $header);
    }
}
