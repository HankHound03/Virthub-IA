<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Services\JsonUserStore;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ventana aparte con el escritorio dentro.
 *
 * La ventana vive en nuestro dominio para que la direccion del contenedor no
 * aparezca en la barra del navegador. Aqui se comprueba que carga el escritorio
 * correcto y que no queda abierta a cualquiera.
 */
class ContenedorVentanaTest extends TestCase
{
    private string $usersFile;

    protected function setUp(): void
    {
        parent::setUp();

        // El administrador tiene que existir de verdad: su sesion se revalida
        // contra el almacen. Un invitado no, porque su cuenta es solo de sesion.
        $this->usersFile = storage_path('app/data/users.json');

        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        app(JsonUserStore::class)->createUser('admin', 'P@ssword123!', 'admin');

        DB::table('users')->insert([
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@users.virthub.local',
            'password' => bcrypt('P@ssword123!'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['ct2', 'ct5'] as $nombre) {
            WebtopContainer::create([
                'name' => $nombre,
                'url' => 'https://' . $nombre . '.virthub.dpdns.org/',
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        parent::tearDown();
    }

    public function test_sin_sesion_no_se_abre(): void
    {
        $this->get('/contenedor/ventana')->assertRedirect('/');
    }

    public function test_el_invitado_abre_el_escritorio_del_invitado(): void
    {
        $html = $this->withSession([
            'auth_user' => ['username' => 'guest_482bc0', 'role' => 'guest'],
            'guest_expires_at' => time() + 1800,
        ])->get('/contenedor/ventana')->assertOk()->getContent();

        // El marco apunta al lanzador del escritorio de invitado.
        $this->assertStringContainsString('contenedor/launch?contenedor=ct7', $html);

        // Y no se filtra ninguna direccion de contenedor al HTML.
        $this->assertStringNotContainsString('ct7.virthub.dpdns.org', $html);
    }

    public function test_el_administrador_abre_el_escritorio_que_pide(): void
    {
        $html = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
        ])->get('/contenedor/ventana?contenedor=ct5')->assertOk()->getContent();

        $this->assertStringContainsString('contenedor/launch?contenedor=ct5', $html);
    }

    public function test_un_escritorio_inexistente_se_ignora(): void
    {
        $html = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
        ])->get('/contenedor/ventana?contenedor=ct99')->assertOk()->getContent();

        // Se cae al escritorio por defecto en vez de apuntar a uno fantasma.
        $this->assertStringNotContainsString('ct99', $html);
    }
}
