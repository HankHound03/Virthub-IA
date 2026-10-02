<?php

namespace Tests\Feature;

use App\Http\Controllers\ForumController;
use App\Services\JsonUserStore;
use Tests\TestCase;

/**
 * El foro anunciaba "hasta 5 GB" mientras el limite real era de 2 MB, y no
 * mostraba ninguna senal al elegir archivos ni al enviar la publicacion.
 *
 * Estos tests fijan que el limite anunciado sea el real y que el formulario
 * incluya los elementos de retroalimentacion de la subida.
 */
class ForumUploadFeedbackTest extends TestCase
{
    private function loginAsUser(): void
    {
        $users = app(JsonUserStore::class);
        $users->createOrUpdateAdmin('admin', 'P@ssword123!');
    }

    public function test_el_limite_anunciado_coincide_con_el_real(): void
    {
        $this->loginAsUser();

        $response = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
        ])->get('/foro');

        $response->assertOk();

        $html = $response->getContent();

        // Limite real por archivo, alcanzable con subida por trozos.
        $maxBytes = (int) config('virthub.uploads.max_file_bytes');
        $this->assertStringContainsString('data-max-bytes="' . $maxBytes . '"', $html);

        // Camino de una sola peticion: los adjuntos del formulario clasico.
        $this->assertStringContainsString(
            'data-fast-path-bytes="' . ForumController::ATTACHMENT_MAX_BYTES . '"',
            $html
        );

        // Y el limite grande se anuncia en GB, no en MB ni con una cifra falsa.
        $this->assertStringContainsString('data-max-label="5 GB"', $html);
    }

    public function test_el_formulario_incluye_la_retroalimentacion_de_subida(): void
    {
        $this->loginAsUser();

        $response = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
        ])->get('/foro');

        $html = $response->getContent();

        // Sin estos elementos el usuario no ve si la subida empezo, avanzo o fallo.
        $this->assertStringContainsString('id="forumUploadProgress"', $html);
        $this->assertStringContainsString('id="forumUploadBarFill"', $html);
        $this->assertStringContainsString('id="forumUploadLabel"', $html);
        $this->assertStringContainsString('bindAttachmentFeedback', $html);
        $this->assertStringContainsString('XMLHttpRequest', $html);
    }

    public function test_el_formulario_permite_adjuntos(): void
    {
        $this->loginAsUser();

        $response = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
        ])->get('/foro');

        $html = $response->getContent();

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('name="photos[]"', $html);
        $this->assertStringContainsString('name="videos[]"', $html);
        $this->assertStringContainsString('name="files[]"', $html);
    }
}
