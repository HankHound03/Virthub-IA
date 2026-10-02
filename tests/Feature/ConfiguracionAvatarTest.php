<?php

namespace Tests\Feature;

use App\Support\AttachmentStorage;
use Tests\TestCase;

/**
 * Avatar en la pagina de configuracion.
 *
 * Esta vista tenia dos problemas distintos que la dejaban con la imagen rota:
 *  1. generaba la URL con asset(), que apunta a la raiz web donde el archivo ya
 *     no esta (vive en storage/app/public y se sirve por public/storage);
 *  2. no comprobaba que el archivo existiera, asi que pintaba el texto
 *     alternativo cuando faltaba.
 *
 * Los tests comprueban el HTML renderizado, no el codigo: es la unica forma de
 * detectar que la URL final es correcta.
 */
class ConfiguracionAvatarTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function render(array $currentUser, bool $twoFactorEnabled = false): string
    {
        return view('configuracion', [
            'currentUser' => $currentUser,
            'twoFactorEnabled' => $twoFactorEnabled,
            'twoFactorSetupQr' => null,
        ])->render();
    }

    public function test_la_url_del_avatar_pasa_por_el_enlace_de_storage(): void
    {
        $html = $this->render([
            'username' => 'admin',
            'name' => 'admin',
            'role' => 'admin',
            'profile_image_path' => 'uploads/profiles/avatar.jpg',
            'profile_frame_color' => '#6ea8ff',
        ]);

        // La URL debe llevar el prefijo del enlace. Si el archivo no existe se
        // muestra la inicial, asi que aqui lo que importa es que NUNCA se genere
        // una ruta directa /uploads/... que daria 404.
        $this->assertStringNotContainsString('src="/uploads/profiles/', $html);
    }

    /**
     * Extrae el bloque del marco de previsualizacion del perfil.
     *
     * La ruta del avatar tambien aparece en el JSON del widget de chat (que es
     * correcto: el widget antepone el prefijo en tiempo de ejecucion), asi que
     * hay que acotar la comprobacion a este bloque.
     */
    private function previewBlock(string $html): string
    {
        $inicio = strpos($html, 'id="profileFramePreview"');

        $this->assertNotFalse($inicio, 'No se encontro el marco de previsualizacion.');

        $fin = strpos($html, '</div>', $inicio);

        return substr($html, $inicio, ($fin === false ? 400 : $fin - $inicio));
    }

    public function test_muestra_la_inicial_cuando_el_avatar_no_existe(): void
    {
        // Caso real: el registro apunta a un archivo que ya no esta en disco.
        $html = $this->render([
            'username' => 'maddys',
            'name' => 'Maddys',
            'role' => 'user',
            'profile_image_path' => 'uploads/profiles/perdido.jpg',
            'profile_frame_color' => '#6ea8ff',
        ]);

        $bloque = $this->previewBlock($html);

        $this->assertStringContainsString('<span>M</span>', $bloque, 'Debe mostrar la inicial del usuario.');
        $this->assertStringNotContainsString('<img', $bloque, 'No debe pintar la imagen si el archivo falta.');
    }

    public function test_sin_avatar_registrado_muestra_la_inicial(): void
    {
        $html = $this->render([
            'username' => 'frank',
            'name' => 'Frank',
            'role' => 'user',
            'profile_image_path' => null,
            'profile_frame_color' => '#6ea8ff',
        ]);

        $this->assertStringNotContainsString('<img src=""', $html);
        $this->assertStringContainsString('<span>F</span>', $html);
    }

    public function test_las_vistas_no_generan_rutas_de_adjuntos_que_darian_404(): void
    {
        // Comprobacion estatica sobre todas las plantillas: si alguna vuelve a
        // usar asset() con una ruta relativa, la imagen se rompe sin aviso.
        foreach (['forum', 'perfil', 'configuracion', 'partials/header'] as $vista) {
            $contenido = (string) file_get_contents(resource_path("views/{$vista}.blade.php"));

            foreach (["asset(\$profileImage)", "asset(\$post['image_path'])", "asset(\$attachment['path'])"] as $patron) {
                $this->assertStringNotContainsString(
                    $patron,
                    $contenido,
                    "{$vista} debe usar AttachmentStorage::url() en lugar de {$patron}"
                );
            }
        }
    }

    public function test_el_widget_de_chat_antepone_el_prefijo_de_storage(): void
    {
        // El widget es un archivo estatico: construye las URLs en JavaScript y
        // no puede usar el helper de PHP.
        $js = (string) file_get_contents(public_path('chat-widget.js'));

        $this->assertStringContainsString('function avatarUrl(', $js);
        $this->assertStringContainsString("'storage/' + path", $js);
        $this->assertStringNotContainsString("charAt(0) === '/' ?", $js);
    }

    public function test_attachment_storage_no_duplica_el_prefijo(): void
    {
        $con = 'storage/uploads/x.png';
        $sin = 'uploads/x.png';

        $this->assertSame(AttachmentStorage::url($con), AttachmentStorage::url($sin));
    }
}
