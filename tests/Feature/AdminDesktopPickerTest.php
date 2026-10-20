<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Services\JsonUserStore;
use App\Services\WebtopAllocator;
use App\Support\DesktopPass;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Selector de escritorios para el administrador.
 *
 * El administrador puede abrir CUALQUIER escritorio desde la pagina de
 * contenedor, para probarlos todos sin crear una cuenta por cada uno.
 *
 * Lo importante de estos tests no es que el administrador pueda, sino que NADIE
 * MAS pueda: es una funcion de permisos, y el riesgo es dejar la puerta abierta.
 */
class AdminDesktopPickerTest extends TestCase
{
    private string $usersFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersFile = storage_path('app/data/users.json');

        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        parent::tearDown();
    }

    /**
     * @return array{username: string, id: int}
     */
    private function user(string $username, string $role = 'user'): array
    {
        app(JsonUserStore::class)->createUser($username, 'P@ssword123!', $role);

        $id = DB::table('users')->insertGetId([
            'name' => $username,
            'username' => $username,
            'email' => $username . '@users.virthub.local',
            'password' => bcrypt('P@ssword123!'),
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['username' => $username, 'id' => $id];
    }

    private function containers(): void
    {
        foreach (['ct0', 'ct2', 'ct3'] as $name) {
            WebtopContainer::create([
                'name' => $name,
                'url' => 'https://' . $name . '.virthub.dpdns.org/',
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => 1,
            ]);
        }
    }

    private function comoCaddy(string $container, ?string $pase = null): \Illuminate\Testing\TestResponse
    {
        $query = $pase === null ? [] : [DesktopPass::PARAM => $pase];

        return $this->withHeaders(['X-Container' => $container])
            ->get('/internal/proxy-auth?' . http_build_query($query));
    }

    public function test_el_administrador_puede_abrir_cualquier_escritorio(): void
    {
        $this->containers();

        $admin = $this->user('admin', 'admin');
        $asignado = app(WebtopAllocator::class)->containerFor($admin['id']);

        // El suyo funciona.
        $pase = app(DesktopPass::class)->para('admin', $asignado->name);
        $this->comoCaddy($asignado->name, $pase)->assertStatus(200);

        // Y tambien otro distinto, que es lo que permite el selector.
        $otro = $asignado->name === 'ct2' ? 'ct3' : 'ct2';
        $paseOtro = app(DesktopPass::class)->para('admin', $otro);

        $this->comoCaddy($otro, $paseOtro)->assertStatus(200);
    }

    public function test_un_usuario_normal_sigue_sin_poder_abrir_el_de_otro(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        // Pide otro escritorio con un pase emitido para el: aun asi se rechaza,
        // porque un usuario normal solo puede abrir el suyo.
        $otro = $asignado->name === 'ct2' ? 'ct3' : 'ct2';
        $pase = app(DesktopPass::class)->para('ana', $otro);

        $this->comoCaddy($otro, $pase)->assertStatus(403);
    }

    public function test_un_escritorio_inexistente_se_rechaza_aunque_sea_el_admin(): void
    {
        $this->containers();

        $admin = $this->user('admin', 'admin');
        app(WebtopAllocator::class)->containerFor($admin['id']);

        // El administrador puede abrir cualquiera, pero no uno que no existe.
        $pase = app(DesktopPass::class)->para('admin', 'ct99');

        $this->comoCaddy('ct99', $pase)->assertStatus(403);
    }

    public function test_sin_pase_no_se_abre_nada_ni_siendo_admin(): void
    {
        $this->containers();

        $admin = $this->user('admin', 'admin');
        app(WebtopAllocator::class)->containerFor($admin['id']);

        // Sin sesion y sin pase, el administrador tampoco entra.
        $this->comoCaddy('ct2')->assertStatus(403);
    }

    public function test_el_parametro_contenedor_se_ignora_para_un_usuario_normal(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        // Ana intenta lanzar el escritorio de otro pasando el parametro.
        $otro = $asignado->name === 'ct2' ? 'ct3' : 'ct2';

        $respuesta = $this->withSession(['auth_user' => ['username' => 'ana', 'role' => 'user']])
            ->get('/contenedor/launch?contenedor=' . $otro);

        $destino = (string) $respuesta->headers->get('Location');

        $this->assertStringContainsString(
            $asignado->name,
            $destino,
            'A un usuario normal se le debe entregar SU escritorio, no el pedido.'
        );
        $this->assertStringNotContainsString($otro . '.virthub', $destino);
    }

    public function test_la_pagina_no_expone_la_direccion_interna_de_los_escritorios(): void
    {
        // Los escritorios del segundo equipo declaran su IP en internal_host.
        // Esa direccion no debe llegar nunca al navegador: no aporta nada al
        // usuario y publica la red local.
        WebtopContainer::create([
            'name' => 'ct8',
            'url' => 'https://ct8.virthub.dpdns.org/',
            'internal_host' => '192.168.1.9',
            'internal_port' => 3000,
            'status' => WebtopContainer::STATUS_AVAILABLE,
            'capacity' => 1,
        ]);

        $this->containers();
        $this->user('admin', 'admin');

        $html = $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
            ->get('/contenedor')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ct8', $html, 'El boton del escritorio debe aparecer.');
        $this->assertStringNotContainsString('192.168.1.9', $html, 'La IP interna no debe salir al navegador.');
        $this->assertStringNotContainsString('internal_host', $html);
    }
}
