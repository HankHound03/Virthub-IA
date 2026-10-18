<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Services\JsonUserStore;
use App\Services\WebtopAllocator;
use App\Support\DesktopPass;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Flujo del selector del administrador, de punta a punta.
 *
 * Comprueba que al pedir un escritorio concreto la redireccion apunta a ESE
 * escritorio y no al del usuario. Si todos apuntaran al mismo, se veria el mismo
 * escritorio en todos los botones: que es justo lo que hay que descartar.
 */
class AdminDesktopFlowTest extends TestCase
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

    private function user(string $username, string $role): array
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
        foreach (['ct0', 'ct2', 'ct5', 'ct7'] as $name) {
            WebtopContainer::create([
                'name' => $name,
                'url' => 'https://' . $name . '.virthub.dpdns.org/',
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => 1,
            ]);
        }
    }

    public function test_cada_boton_redirige_a_su_propio_escritorio(): void
    {
        $this->containers();

        $admin = $this->user('admin', 'admin');
        $asignado = app(WebtopAllocator::class)->containerFor($admin['id']);

        $destinos = [];

        foreach (['ct2', 'ct5', 'ct7'] as $pedido) {
            $respuesta = $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
                ->get('/contenedor/launch?contenedor=' . $pedido);

            $respuesta->assertRedirect();

            $destino = (string) $respuesta->headers->get('Location');
            $destinos[$pedido] = $destino;

            $this->assertStringContainsString(
                $pedido . '.virthub.dpdns.org',
                $destino,
                "El boton {$pedido} debe abrir {$pedido}, no otro."
            );

            // Y lleva un pase, sin el cual el proxy responde 403.
            $this->assertStringContainsString(DesktopPass::PARAM . '=', $destino);
        }

        // Y cada uno apunta a un sitio distinto: si todos fueran iguales, en la
        // practica seria el mismo escritorio abierto desde todos los botones.
        $this->assertCount(3, array_unique($destinos), 'Cada boton debe tener un destino propio.');
    }

    public function test_el_pase_de_cada_boton_solo_sirve_para_su_escritorio(): void
    {
        $this->containers();

        $admin = $this->user('admin', 'admin');
        app(WebtopAllocator::class)->containerFor($admin['id']);

        // Pase emitido para ct5.
        $respuesta = $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
            ->get('/contenedor/launch?contenedor=ct5');

        $destino = (string) $respuesta->headers->get('Location');
        parse_str((string) parse_url($destino, PHP_URL_QUERY), $query);
        $pase = (string) ($query[DesktopPass::PARAM] ?? '');

        $this->assertNotSame('', $pase);

        // Se descarta la sesion: en el subdominio del escritorio la cookie no
        // siempre llega, y es justo el caso que el pase resuelve. Si se dejara la
        // sesion, el administrador entraria por ser administrador y no se estaria
        // probando el pase.
        $this->flushSession();

        // Ese pase abre ct5...
        $this->withHeaders(['X-Container' => 'ct5'])
            ->get('/internal/proxy-auth?' . http_build_query([DesktopPass::PARAM => $pase]))
            ->assertStatus(200);

        // ...pero NO ct7: el pase esta atado al escritorio.
        $this->withHeaders(['X-Container' => 'ct7'])
            ->get('/internal/proxy-auth?' . http_build_query([DesktopPass::PARAM => $pase]))
            ->assertStatus(403);
    }
}
