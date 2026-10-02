<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebtopContainer;
use App\Services\WebtopAllocator;
use App\Support\ContainerResolver;
use Tests\TestCase;

/**
 * Asignacion exclusiva de escritorios Webtop.
 *
 * Antes el contenedor se calculaba con crc32($username) % 5 sobre una lista
 * fija, asi que varios usuarios acababan en el mismo escritorio y podian verse
 * la sesion. Ahora la asignacion se guarda en workspace_assignments, con un
 * indice unico sobre user_id.
 */
class WebtopAllocationTest extends TestCase
{
    private function allocator(): WebtopAllocator
    {
        return app(WebtopAllocator::class);
    }

    private function user(string $username, string $role = 'user'): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username . '@users.virthub.local',
            'password' => 'P@ssword123!',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $names
     */
    /**
     * @param  array<int, string>  $names
     */
    private function containers(array $names, ?int $capacity = null): void
    {
        foreach ($names as $name) {
            WebtopContainer::create([
                'name' => $name,
                'url' => 'https://' . $name . '.test/',
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => $capacity,
            ]);
        }
    }

    public function test_dos_usuarios_reciben_contenedores_distintos(): void
    {
        $this->containers(['ct2', 'ct3']);

        $ana = $this->user('ana');
        $bea = $this->user('bea');

        $paraAna = $this->allocator()->containerFor($ana->id);
        $paraBea = $this->allocator()->containerFor($bea->id);

        $this->assertNotNull($paraAna);
        $this->assertNotNull($paraBea);

        // Esta es la comprobacion que importa: no comparten escritorio.
        $this->assertNotSame(
            $paraAna->id,
            $paraBea->id,
            'Dos usuarios no pueden compartir el mismo escritorio.'
        );
    }

    public function test_un_usuario_conserva_su_contenedor_entre_llamadas(): void
    {
        $this->containers(['ct2', 'ct3']);

        $ana = $this->user('ana');

        $primera = $this->allocator()->containerFor($ana->id);
        $segunda = $this->allocator()->containerFor($ana->id);

        $this->assertSame($primera->id, $segunda->id, 'El escritorio debe persistir entre sesiones.');
    }

    public function test_un_usuario_solo_acumula_una_asignacion_activa(): void
    {
        $this->containers(['ct2', 'ct3']);

        $ana = $this->user('ana');

        // Varias llamadas no deben crear asignaciones duplicadas.
        $this->allocator()->containerFor($ana->id);
        $this->allocator()->containerFor($ana->id);
        $this->allocator()->containerFor($ana->id);

        $activas = \App\Models\WorkspaceAssignment::query()
            ->where('user_id', $ana->id)
            ->whereNull('released_at')
            ->count();

        $this->assertSame(1, $activas, 'Un usuario no puede tener dos escritorios activos.');
    }

    public function test_tras_liberar_se_puede_asignar_un_escritorio_de_nuevo(): void
    {
        $this->containers(['ct2', 'ct3']);

        $ana = $this->user('ana');

        $primera = $this->allocator()->containerFor($ana->id);
        $this->allocator()->release($ana->id);

        $segunda = $this->allocator()->containerFor($ana->id);

        $this->assertNotNull($segunda);

        // Vuelve a recibir un escritorio libre: el reparto equitativo tiende a
        // reutilizar el que quedo sin carga, y eso es lo deseable.
        $this->assertContains($segunda->name, ['ct2', 'ct3']);
        $this->assertNotNull($primera->id);

        // Queda una sola activa y la anterior registrada como historial.
        $this->assertSame(
            1,
            \App\Models\WorkspaceAssignment::query()->where('user_id', $ana->id)->whereNull('released_at')->count()
        );
        $this->assertSame(
            1,
            \App\Models\WorkspaceAssignment::query()->where('user_id', $ana->id)->whereNotNull('released_at')->count()
        );
    }

    public function test_respeta_el_limite_de_plazas_por_contenedor(): void
    {
        // Un solo contenedor con una plaza: el segundo usuario no cabe.
        $this->containers(['ct2'], 1);

        $ana = $this->user('ana');
        $bea = $this->user('bea');

        $this->assertNotNull($this->allocator()->containerFor($ana->id));
        $this->assertNull(
            $this->allocator()->containerFor($bea->id),
            'Sin plazas libres no debe asignarse un escritorio.'
        );
    }

    public function test_reparte_la_carga_entre_contenedores_libres(): void
    {
        $this->containers(['ct2', 'ct3'], 5);

        $usuarios = collect(['a', 'b', 'c', 'd'])->map(fn (string $n): User => $this->user('u' . $n));

        $asignados = $usuarios->map(fn (User $u) => $this->allocator()->containerFor($u->id));

        // Con dos contenedores y cuatro usuarios, ninguno debe quedar sin sitio.
        $this->assertTrue($asignados->every(fn ($c) => $c !== null));

        // Y el reparto no debe apilar a todos en el primero.
        $this->assertGreaterThan(1, $asignados->pluck('id')->unique()->count());
    }

    public function test_liberar_un_escritorio_lo_deja_disponible(): void
    {
        $this->containers(['ct2'], 1);

        $ana = $this->user('ana');
        $bea = $this->user('bea');

        $this->assertNotNull($this->allocator()->containerFor($ana->id));
        $this->assertNull($this->allocator()->containerFor($bea->id));

        $this->assertTrue($this->allocator()->release($ana->id));

        // Ahora bea si cabe.
        $this->assertNotNull($this->allocator()->containerFor($bea->id));
    }

    public function test_el_resolver_devuelve_el_contenedor_asignado(): void
    {
        $this->containers(['ct2']);

        $ana = $this->user('ana');
        $this->allocator()->containerFor($ana->id);

        $url = app(ContainerResolver::class)->urlFor([
            'id' => (string) $ana->id,
            'username' => 'ana',
            'role' => 'user',
        ]);

        $this->assertSame('https://ct2.test/', $url);
    }

    public function test_sin_contenedores_registrados_se_usa_el_reparto_de_respaldo(): void
    {
        // Una instalacion que aun no ha sincronizado su infraestructura debe
        // seguir funcionando con el reparto anterior.
        $resolver = app(ContainerResolver::class);

        $url = $resolver->urlFor(['username' => 'ana', 'role' => 'user']);

        $this->assertNotSame('', $url);
        $this->assertStringContainsString('ct', $url);
    }

    public function test_el_invitado_va_al_contenedor_de_invitados(): void
    {
        config(['virthub.containers.guest_index' => 7]);
        config(['virthub.containers.urls' => [7 => 'https://ct7.test/']]);

        $this->assertSame(
            'https://ct7.test/',
            app(ContainerResolver::class)->urlFor(['username' => 'guest_x', 'role' => 'guest'])
        );
    }

    public function test_sincroniza_los_contenedores_de_la_configuracion(): void
    {
        config([
            'virthub.containers.urls' => [
                0 => 'https://ct0.test/',
                2 => 'https://ct2.test/',
            ],
            'virthub.containers.instances_per_container' => 1,
            'virthub.containers.cpu_limit' => null,
            'virthub.containers.memory_limit_mb' => null,
        ]);

        $synced = $this->allocator()->syncDefinitions($this->allocator()->definitionsFromConfig());

        $this->assertSame(2, $synced);
        $this->assertSame(2, WebtopContainer::count());
        $this->assertDatabaseHas('webtop_containers', ['name' => 'ct0', 'url' => 'https://ct0.test/']);
    }
}
