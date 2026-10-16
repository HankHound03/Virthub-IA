<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Models\WorkspaceAssignment;
use App\Services\WebtopAllocator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reparto de escritorios.
 *
 * Dos fallos reales que cubren estos tests:
 *
 *  1. El escritorio de administradores (ct0) entraba en el reparto comun, asi
 *     que un usuario normal podia acabar en el y, al reves, al administrador se
 *     le denegaba el suyo por estar ocupado.
 *
 *  2. Un usuario podia tener DOS asignaciones activas al mismo tiempo. La
 *     ocupacion contaba doble, asi que un contenedor de capacidad 1 figuraba
 *     como lleno con una sola persona dentro.
 */
class WebtopAllocationRulesTest extends TestCase
{
    private function escritorios(array $nombres = ['ct0', 'ct2', 'ct3', 'ct4']): void
    {
        foreach ($nombres as $nombre) {
            WebtopContainer::create([
                'name' => $nombre,
                'url' => 'https://' . $nombre . '.virthub.dpdns.org/',
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => 1,
            ]);
        }
    }

    private function usuario(string $username): int
    {
        return DB::table('users')->insertGetId([
            'name' => $username,
            'username' => $username,
            'email' => $username . '@users.virthub.local',
            'password' => bcrypt('x'),
            'role' => 'user',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_el_escritorio_de_administradores_queda_fuera_del_reparto(): void
    {
        $this->escritorios();

        $allocator = app(WebtopAllocator::class);

        $this->assertContains('ct0', $allocator->reservedNames());

        // Muchos usuarios: ninguno debe recibir ct0, por mucho que este libre.
        for ($i = 1; $i <= 3; $i++) {
            $id = $this->usuario('usuario' . $i);
            $asignado = $allocator->containerFor($id);

            $this->assertNotNull($asignado);
            $this->assertNotSame('ct0', $asignado->name, 'ct0 es exclusivo del administrador.');
        }
    }

    public function test_cada_usuario_recibe_un_escritorio_distinto(): void
    {
        $this->escritorios();

        $allocator = app(WebtopAllocator::class);

        $asignados = [];

        for ($i = 1; $i <= 3; $i++) {
            $id = $this->usuario('usuario' . $i);
            $asignados[] = $allocator->containerFor($id)->name;
        }

        $this->assertSame(
            count($asignados),
            count(array_unique($asignados)),
            'Dos usuarios no pueden compartir escritorio con capacidad 1.'
        );
    }

    public function test_pedir_el_escritorio_dos_veces_no_crea_dos_asignaciones(): void
    {
        $this->escritorios();

        $allocator = app(WebtopAllocator::class);
        $id = $this->usuario('ana');

        $primero = $allocator->containerFor($id);
        $segundo = $allocator->containerFor($id);

        $this->assertSame($primero->id, $segundo->id);

        $activas = WorkspaceAssignment::query()
            ->where('user_id', $id)
            ->whereNull('released_at')
            ->count();

        $this->assertSame(1, $activas, 'Un usuario no puede tener dos asignaciones activas.');
    }

    public function test_la_base_de_datos_impide_dos_asignaciones_activas(): void
    {
        // Cinturon ademas de tirantes: aunque el codigo fallara, el indice unico
        // lo impide. Se comprueba que el indice existe donde el motor lo soporta.
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            $this->markTestSkipped('El indice unico se comprueba en MySQL/MariaDB.');
        }

        $indices = collect(DB::select('SHOW INDEX FROM workspace_assignments'))
            ->pluck('Key_name')
            ->unique();

        $this->assertTrue(
            $indices->contains('workspace_assignments_user_activa_unique'),
            'Falta el indice que impide dos asignaciones activas por usuario.'
        );
    }

    public function test_la_ocupacion_cuenta_una_vez_por_persona(): void
    {
        $this->escritorios();

        $allocator = app(WebtopAllocator::class);
        $ana = $this->usuario('ana');

        $primero = $allocator->containerFor($ana);

        // Con capacidad 1, una persona llena el escritorio: es lo correcto.
        $this->assertSame(1, $primero->activeAssignments()->count());
        $this->assertFalse($allocator->hasCapacity($primero->fresh()));

        // El fallo que se corrige: pedirlo OTRA VEZ creaba una segunda fila
        // activa, y entonces la ocupacion contaba dos. Con el indice unico y la
        // comprobacion del allocator, sigue contando una.
        $allocator->containerFor($ana);

        $this->assertSame(
            1,
            $primero->activeAssignments()->count(),
            'Un duplicado hacia que la ocupacion contara doble.'
        );

        // Y otro usuario sigue recibiendo un escritorio distinto, no este.
        $bea = $this->usuario('bea');
        $deBea = $allocator->containerFor($bea);

        $this->assertNotSame($primero->id, $deBea->id);
    }
}
