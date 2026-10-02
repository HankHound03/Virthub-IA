<?php

namespace App\Services;

use App\Models\WebtopContainer;
use App\Models\WorkspaceAssignment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Asigna contenedores Webtop a los usuarios de forma exclusiva.
 *
 * Antes el contenedor se calculaba con crc32($username) % 5 sobre una lista
 * fija, asi que varios usuarios acababan en el mismo escritorio y podian verse
 * la sesion unos a otros. Ahora la asignacion se guarda en
 * workspace_assignments, que tiene un indice unico sobre user_id: un usuario no
 * puede tener dos escritorios y dos usuarios no pueden compartir el mismo.
 */
class WebtopAllocator
{
    /**
     * Devuelve el contenedor del usuario, asignandole uno si aun no tiene.
     */
    public function containerFor(int $userId): ?WebtopContainer
    {
        $existing = $this->currentContainer($userId);

        if ($existing !== null) {
            return $existing;
        }

        return $this->assign($userId);
    }

    /**
     * Contenedor asignado actualmente, sin crear una asignacion nueva.
     */
    public function currentContainer(int $userId): ?WebtopContainer
    {
        $assignment = WorkspaceAssignment::query()
            ->with('container')
            ->where('user_id', $userId)
            ->whereNull('released_at')
            ->first();

        return $assignment?->container;
    }

    /**
     * Asigna un contenedor libre al usuario.
     *
     * Se elige el contenedor con menos asignaciones activas para repartir la
     * carga, y se guarda la asignacion para que el usuario conserve siempre el
     * mismo escritorio.
     */
    public function assign(int $userId): ?WebtopContainer
    {
        return DB::transaction(function () use ($userId): ?WebtopContainer {
            // Si otra peticion simultanea ya le asigno uno, se reutiliza.
            $existing = $this->currentContainer($userId);

            if ($existing !== null) {
                return $existing;
            }

            $container = $this->pickAvailableContainer();

            if ($container === null) {
                return null;
            }

            WorkspaceAssignment::create([
                'user_id' => $userId,
                'webtop_container_id' => $container->id,
                'status' => WorkspaceAssignment::STATUS_ASSIGNED,
                'assigned_at' => now(),
            ]);

            return $container;
        });
    }

    /**
     * Libera el escritorio del usuario para que otro pueda usarlo.
     */
    public function release(int $userId): bool
    {
        $updated = WorkspaceAssignment::query()
            ->where('user_id', $userId)
            ->whereNull('released_at')
            ->update([
                'status' => WorkspaceAssignment::STATUS_RELEASED,
                'released_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Contenedor con menos carga y capacidad disponible.
     *
     * El reparto es equitativo a proposito: si al primero se le asigna siempre
     * al mismo usuario, los demas contenedores quedan vacios y el aislamiento no
     * sirve de nada. Se ordena por carga y, a igualdad de carga, por la
     * asignacion mas antigua, de modo que los contenedores sin usar entren
     * primero y el reparto se mantenga equilibrado.
     */
    public function pickAvailableContainer(): ?WebtopContainer
    {
        $candidates = WebtopContainer::query()
            ->where('status', '!=', WebtopContainer::STATUS_OFFLINE)
            ->withCount('activeAssignments')
            ->withMax('activeAssignments as last_assigned_at', 'assigned_at')
            ->orderBy('active_assignments_count')
            ->orderByRaw('last_assigned_at IS NOT NULL')
            ->orderBy('last_assigned_at')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $container) {
            if ($this->hasCapacity($container)) {
                return $container;
            }
        }

        return null;
    }

    /**
     * Indica si al contenedor le queda capacidad.
     *
     * 'capacity' es cuantos escritorios puede atender. null significa sin
     * limite declarado: se reparte sin tope, que es el comportamiento util
     * mientras se migra una instalacion existente.
     */
    public function hasCapacity(WebtopContainer $container): bool
    {
        $capacity = $container->capacity;

        if ($capacity === null || $capacity <= 0) {
            return true;
        }

        return $container->activeAssignments()->count() < $capacity;
    }

    /**
     * Crea o actualiza los contenedores declarados en config/virthub.php.
     *
     * Sirve para que el .env siga siendo la fuente de verdad de la
     * infraestructura y la base de datos solo guarde el estado de las
     * asignaciones.
     *
     * @param  array<int, array{name: string, url: string, capacity?: int|null, cpu_limit?: int|null, memory_limit_mb?: int|null}>  $definitions
     * @return int numero de contenedores sincronizados
     */
    public function syncDefinitions(array $definitions): int
    {
        $synced = 0;

        foreach ($definitions as $definition) {
            $name = trim((string) ($definition['name'] ?? ''));
            $url = trim((string) ($definition['url'] ?? ''));

            if ($name === '' || $url === '') {
                throw new RuntimeException('Cada contenedor necesita nombre y URL.');
            }

            WebtopContainer::updateOrCreate(
                ['name' => $name],
                [
                    'url' => $url,
                    'capacity' => $definition['capacity'] ?? null,
                    'cpu_limit' => $definition['cpu_limit'] ?? null,
                    'memory_limit_mb' => $definition['memory_limit_mb'] ?? null,
                    'status' => WebtopContainer::STATUS_AVAILABLE,
                ]
            );

            $synced++;
        }

        return $synced;
    }

    /**
     * Contenedores declarados en la configuracion, listos para sincronizar.
     *
     * @return array<int, array{name: string, url: string, capacity: int|null, cpu_limit: int|null, memory_limit_mb: int|null}>
     */
    public function definitionsFromConfig(): array
    {
        $urls = (array) config('virthub.containers.urls', []);
        $instances = (int) config('virthub.containers.instances_per_container', 1);
        $cpuLimit = config('virthub.containers.cpu_limit');
        $memoryLimit = config('virthub.containers.memory_limit_mb');

        $definitions = [];

        foreach ($urls as $index => $url) {
            $index = (int) $index;
            $url = trim((string) $url);

            if ($url === '') {
                continue;
            }

            $definitions[] = [
                'name' => 'ct' . $index,
                'url' => $url,
                // Cuantos escritorios caben en esta maquina.
                'capacity' => $instances > 0 ? $instances : null,
                // Cuota de recursos de la maquina, no plazas.
                'cpu_limit' => $cpuLimit === null ? null : (int) $cpuLimit,
                'memory_limit_mb' => $memoryLimit === null ? null : (int) $memoryLimit,
            ];
        }

        return $definitions;
    }
}
