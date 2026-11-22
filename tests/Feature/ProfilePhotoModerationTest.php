<?php

namespace Tests\Feature;

use App\Services\JsonUserStore;
use Illuminate\Http\Testing\File as ArchivoPrueba;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Moderacion de la foto de perfil con NSFW Sherlock.
 *
 * La foto de perfil es el contenido mas visible del sitio (cabecera, chat, foro y
 * perfil), asi que pasa el mismo filtro que las imagenes del foro.
 */
class ProfilePhotoModerationTest extends TestCase
{
    private string $profileDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileDir = storage_path('app/public/uploads/profiles');

        File::deleteDirectory($this->profileDir);
        File::ensureDirectoryExists($this->profileDir);

        // El controlador busca al usuario en el almacen real.
        @unlink(storage_path('app/data/users.json'));
        app(JsonUserStore::class)->createOrUpdateAdmin('ana', 'P@ssword123!');

        config([
            'virthub.moderacion.enabled' => true,
            'virthub.moderacion.url' => 'http://nsfw-sherlock:4000',
            'virthub.moderacion.umbral' => 0.75,
            'virthub.antivirus.enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->profileDir);
        @unlink(storage_path('app/data/users.json'));

        parent::tearDown();
    }

    /** Respuesta de Sherlock para una imagen apta. */
    private function respuestaApta(): array
    {
        return [
            'verdict' => 'sfw',
            'nsfw' => 0.0003,
            'labels' => ['porn' => 0.00001, 'neutral' => 0.99],
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
            'labels' => ['porn' => 0.94, 'neutral' => 0.04],
            'detections' => [['label' => 'exposed', 'score' => 0.91]],
            'status' => 'ok',
        ];
    }


    /**
     * PNG real de 1x1. Se construye desde base64 porque el PHP de este equipo no
     * tiene GD, y UploadedFile::fake()->image() lo necesita.
     */
    private function avatarDePrueba(string $nombre = 'avatar.png'): ArchivoPrueba
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );

        return ArchivoPrueba::createWithContent($nombre, $png);
    }

    private function sesion(): array
    {
        return ['auth_user' => ['username' => 'ana', 'role' => 'admin']];
    }

    public function test_un_avatar_apto_se_guarda(): void
    {
        Http::fake([
            '*/pic/analyze' => Http::response($this->respuestaApta(), 200),
        ]);

        $respuesta = $this->withSession($this->sesion())
            ->post('/profile/appearance', [
                'frame_color' => '#6ea8ff',
                'profile_image' => $this->avatarDePrueba(),
            ]);

        $respuesta->assertRedirect('/configuracion');
        $respuesta->assertSessionHas('success');

        $this->assertNotSame([], File::glob($this->profileDir . '/*') ?: [], 'El avatar debe quedar guardado.');
    }

    public function test_un_avatar_prohibido_se_rechaza_y_no_se_guarda(): void
    {
        Http::fake([
            '*/pic/analyze' => Http::response($this->respuestaProhibida(), 200),
        ]);

        $respuesta = $this->withSession($this->sesion())
            ->post('/profile/appearance', [
                'frame_color' => '#6ea8ff',
                'profile_image' => $this->avatarDePrueba(),
            ]);

        $respuesta->assertRedirect('/configuracion');
        $respuesta->assertSessionHas('error');

        $this->assertSame(
            [],
            File::glob($this->profileDir . '/*') ?: [],
            'Una foto rechazada no debe quedar en disco.'
        );
    }

    public function test_si_el_servicio_no_responde_el_avatar_se_acepta(): void
    {
        Http::fake([
            '*/pic/analyze' => Http::response('', 500),
        ]);

        $respuesta = $this->withSession($this->sesion())
            ->post('/profile/appearance', [
                'frame_color' => '#6ea8ff',
                'profile_image' => $this->avatarDePrueba(),
            ]);

        // Mismo criterio que el foro: una caida del servicio no bloquea todo.
        $respuesta->assertRedirect('/configuracion');
        $respuesta->assertSessionHas('success');
    }
}
