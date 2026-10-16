<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Services\JsonUserStore;
use App\Services\WebtopAllocator;
use App\Support\DesktopPass;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pase de acceso a los escritorios.
 *
 * Existe porque la cookie de sesion no siempre viaja al subdominio del
 * escritorio, y entonces el usuario recibia 403 en su propio escritorio. El pase
 * viaja en la direccion, asi que no depende de cookies.
 *
 * Es importante que el pase NO sea un simple "dejar pasar": debe seguir atado al
 * usuario y al escritorio, y caducar. Si no, seria peor que no tener nada.
 */
class DesktopPassTest extends TestCase
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
    private function user(string $username): array
    {
        app(JsonUserStore::class)->createUser($username, 'P@ssword123!', 'user');

        $id = DB::table('users')->insertGetId([
            'name' => $username,
            'username' => $username,
            'email' => $username . '@users.virthub.local',
            'password' => bcrypt('P@ssword123!'),
            'role' => 'user',
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

    /**
     * Peticion al punto de control COMO LA HACE CADDY: con X-Container y el pase
     * en la direccion, sin sesion.
     *
     * @param  array<string, string>  $query
     */
    private function comoCaddy(string $container, array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Container' => $container])
            ->get('/internal/proxy-auth?' . http_build_query($query));
    }

    public function test_sin_pase_y_sin_sesion_se_deniega(): void
    {
        $this->containers();
        $this->user('ana');

        $this->comoCaddy('ct2')->assertStatus(403);
    }

    public function test_un_pase_valido_abre_el_escritorio_del_usuario(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        // El pase que emitiria la aplicacion al pulsar "Contenedor".
        $pase = app(DesktopPass::class)->para($ana['username'], $asignado->name);

        $this->assertNotSame('', $pase, 'La aplicacion debe poder emitir el pase.');

        $this->comoCaddy($asignado->name, [DesktopPass::PARAM => $pase])->assertStatus(200);
    }

    public function test_un_pase_manipulado_no_sirve(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        $pase = app(DesktopPass::class)->para($ana['username'], $asignado->name);

        // Se cambia la firma.
        $falsificado = preg_replace('/signature=[^&]+/', 'signature=falsa', $pase);

        $this->comoCaddy($asignado->name, [DesktopPass::PARAM => $falsificado])->assertStatus(403);

        // Y sin firma alguna.
        $sinFirma = preg_replace('/&signature=[^&]+/', '', $pase);
        $this->comoCaddy($asignado->name, [DesktopPass::PARAM => $sinFirma])->assertStatus(403);
    }

    public function test_el_pase_de_uno_no_abre_el_escritorio_de_otro(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $bea = $this->user('bea');

        $deAna = app(WebtopAllocator::class)->containerFor($ana['id']);
        $deBea = app(WebtopAllocator::class)->containerFor($bea['id']);

        // Pase de Ana, pero pidiendo el escritorio de Bea.
        $paseDeAna = app(DesktopPass::class)->para($ana['username'], $deAna->name);

        $this->comoCaddy($deBea->name, [DesktopPass::PARAM => $paseDeAna])->assertStatus(403);
    }

    public function test_un_pase_caducado_no_sirve(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        $pase = app(DesktopPass::class)->para($ana['username'], $asignado->name);

        // Se avanza el reloj mas alla de la caducidad.
        $this->travel(9)->hours();

        $this->comoCaddy($asignado->name, [DesktopPass::PARAM => $pase])->assertStatus(403);
    }

    public function test_la_sesion_sigue_sirviendo_sin_pase(): void
    {
        // El pase es un respaldo para cuando la cookie no llega, no un requisito
        // extra: quien ya tiene sesion no debe necesitarlo.
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        $this->withSession(['auth_user' => ['username' => 'ana', 'role' => 'user']])
            ->withHeaders(['X-Container' => $asignado->name])
            ->get('/internal/proxy-auth')
            ->assertStatus(200);
    }
}
