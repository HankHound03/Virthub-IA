<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Models\WorkspaceAssignment;
use App\Services\JsonUserStore;
use App\Services\WebtopAllocator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Control de acceso del proxy inverso.
 *
 * El proxy pregunta a /internal/proxy-auth antes de entregar un escritorio. La
 * comprobacion que importa no es solo "hay sesion", sino "este escritorio es
 * suyo": sin eso, un usuario autenticado podria abrir el contenedor de otro
 * cambiando el subdominio.
 *
 * La ruta responde 200 o 403 y nunca redirige: el proxy solo mira el codigo.
 *
 * Nota: en el entorno de pruebas el almacen de usuarios es el JSON, pero la
 * asignacion de escritorios referencia users.id, asi que se crean las dos cosas:
 * la cuenta en el almacen (para la sesion) y la fila en la base de datos (para
 * la asignacion).
 */
class ProxyAuthTest extends TestCase
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
     * Crea la cuenta en el almacen activo y su fila en la base de datos.
     *
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
        // Formato actual: el identificador del servicio en docker-compose.
        // Los escritorios ya no viven en un subdominio propio.
        foreach (['ct0', 'ct2', 'ct3'] as $name) {
            WebtopContainer::create([
                'name' => $name,
                'url' => $name,
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => 1,
            ]);
        }
    }

    /**
     * Contenedores con el formato ANTIGUO (URL de subdominio).
     *
     * Se conserva la compatibilidad para no romper asignaciones que ya existian
     * en la base de datos antes de servir los escritorios por el dominio
     * principal.
     */
    private function containersConUrlAntigua(): void
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
     * @param  array{username: string, id: int}  $user
     */
    private function authAs(array $user, ?string $container): \Illuminate\Testing\TestResponse
    {
        $headers = $container === null ? [] : ['X-Container' => $container];

        return $this->withSession([
            'auth_user' => ['username' => $user['username'], 'role' => 'user'],
        ])->withHeaders($headers)->get('/internal/proxy-auth');
    }

    public function test_sin_sesion_deniega_el_acceso(): void
    {
        $this->withHeaders(['X-Container' => 'ct2'])->get('/internal/proxy-auth')->assertStatus(403);
    }

    public function test_un_invitado_no_puede_abrir_un_escritorio_privado(): void
    {
        $this->withSession([
            'auth_user' => ['username' => 'guest_abc123', 'role' => 'guest'],
            'guest_expires_at' => time() + 600,
        ])->withHeaders(['X-Container' => 'ct2'])->get('/internal/proxy-auth')->assertStatus(403);
    }

    public function test_un_usuario_puede_abrir_su_propio_escritorio(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        $this->authAs($ana, $asignado->name)->assertStatus(200);
    }

    public function test_un_usuario_no_puede_abrir_el_escritorio_de_otro(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $bea = $this->user('bea');

        $deAna = app(WebtopAllocator::class)->containerFor($ana['id']);
        $deBea = app(WebtopAllocator::class)->containerFor($bea['id']);

        // Ana intenta entrar al de Bea conociendo el nombre.
        $this->authAs($ana, $deBea->name)->assertStatus(403);

        // Y al reves tampoco.
        $this->authAs($bea, $deAna->name)->assertStatus(403);

        // Cada una con el suyo si entra.
        $this->authAs($ana, $deAna->name)->assertStatus(200);
        $this->authAs($bea, $deBea->name)->assertStatus(200);
    }

    public function test_un_contenedor_inexistente_es_rechazado(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        app(WebtopAllocator::class)->containerFor($ana['id']);

        $this->authAs($ana, 'ct99')->assertStatus(403);
    }

    public function test_sin_cabecera_de_destino_solo_se_exige_sesion(): void
    {
        $this->containers();

        $ana = $this->user('ana');

        $this->authAs($ana, null)->assertStatus(200);
    }

    public function test_una_cuenta_desactivada_pierde_el_acceso(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        // Se comprueba contra el almacen en cada peticion, no contra la sesion.
        app(JsonUserStore::class)->deactivateUser('ana');

        $this->authAs($ana, $asignado->name)->assertStatus(403);
    }

    public function test_la_asignacion_queda_registrada_en_la_base_de_datos(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        $this->assertDatabaseHas('workspace_assignments', [
            'user_id' => $ana['id'],
            'webtop_container_id' => $asignado->id,
            'status' => WorkspaceAssignment::STATUS_ASSIGNED,
        ]);
    }

    /**
     * Al pasar de subdominios a rutas del mismo dominio, la comparacion entre el
     * nombre esperado y el pedido era lo mas fragil del cambio: si no coinciden,
     * el usuario legitimo recibe 403 y no puede abrir su propio escritorio.
     */
    public function test_el_formato_de_identificador_no_produce_403_falsos(): void
    {
        $this->containers();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        // El usuario abre su escritorio sin token ni cabecera rara.
        $this->authAs($ana, $asignado->name)->assertStatus(200);
    }

    public function test_una_url_antigua_de_subdominio_sigue_resolviendo_el_nombre(): void
    {
        $this->containersConUrlAntigua();

        $ana = $this->user('ana');
        $asignado = app(WebtopAllocator::class)->containerFor($ana['id']);

        // La asignacion guarda la URL antigua; el proxy debe reconocer el nombre
        // y no devolver 403 a quien tiene derecho a ese escritorio.
        $this->authAs($ana, $asignado->name)->assertStatus(200);
    }

    public function test_cada_escritorio_tiene_su_propio_subdominio(): void
    {
        // Esquema en uso: un subdominio por escritorio, enrutado por el tunel de
        // Cloudflare hacia Caddy. La aplicacion redirige ahi y Caddy vuelve a
        // comprobar la sesion antes de entregar nada.
        foreach (config('virthub.containers.urls', []) as $index => $url) {
            $this->assertMatchesRegularExpression(
                '#^https://ct\d+\.#',
                (string) $url,
                "El contenedor [$index] no apunta a su subdominio publico."
            );
        }

        // 0 es el escritorio de administradores: no debe perderse al filtrar,
        // porque es falsy en PHP.
        $this->assertArrayHasKey(0, config('virthub.containers.urls'));
        $this->assertSame(0, app(\App\Support\ContainerResolver::class)->adminIndex());
    }
}