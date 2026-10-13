<?php

namespace App\Console\Commands;

use App\Models\WebtopContainer;
use App\Services\WebtopAllocator;
use Illuminate\Console\Command;

/**
 * Sincroniza los contenedores Webtop declarados en config/virthub.php con la
 * base de datos.
 *
 * El .env sigue siendo la fuente de verdad de la infraestructura (que maquinas
 * existen y en que URL), y la base de datos guarda el estado de las
 * asignaciones. Hay que ejecutarlo una vez tras el despliegue y cada vez que se
 * anada o cambie un contenedor.
 */
class SyncWebtopContainers extends Command
{
    protected $signature = 'virthub:sync-containers
                            {--limit= : Plazas por contenedor (por defecto, lo que diga la configuracion)}
                            {--release-all : Libera todas las asignaciones antes de sincronizar}
                            {--prune : Elimina los contenedores que ya no estan declarados en la configuracion}';

    protected $description = 'Registra en la base de datos los contenedores Webtop de la configuracion';

    public function handle(WebtopAllocator $allocator): int
    {
        $definitions = $allocator->definitionsFromConfig();

        if ($definitions === []) {
            $this->error('No hay contenedores declarados en config/virthub.php (containers.urls).');

            return self::FAILURE;
        }

        $limit = $this->option('limit');

        if ($limit !== null) {
            $limit = max(1, (int) $limit);

            foreach ($definitions as $index => $definition) {
                $definitions[$index]['capacity'] = $limit;
            }
        }

        if ($this->option('release-all')) {
            $released = \App\Models\WorkspaceAssignment::query()
                ->whereNull('released_at')
                ->update([
                    'status' => \App\Models\WorkspaceAssignment::STATUS_RELEASED,
                    'released_at' => now(),
                ]);

            $this->warn("Asignaciones liberadas: {$released}");
        }

        $synced = $allocator->syncDefinitions($definitions);

        $this->info("Contenedores sincronizados: {$synced}");

        // Los contenedores que ya no se declaran se quedan registrados y el
        // reparto por hash podria mandar a alguien a un escritorio que no existe
        // (un 404 en el iframe). Con --prune se retiran.
        if ($this->option('prune')) {
            $declarados = array_column($definitions, 'name');

            $sobrantes = WebtopContainer::query()
                ->whereNotIn('name', $declarados)
                ->get();

            if ($sobrantes->isEmpty()) {
                $this->line('Nada que eliminar: no hay contenedores sobrantes.');
            }

            foreach ($sobrantes as $sobrante) {
                $activas = $sobrante->activeAssignments()->count();

                if ($activas > 0) {
                    $this->warn("Se conserva {$sobrante->name}: tiene {$activas} asignacion(es) activa(s).");

                    continue;
                }

                $sobrante->delete();
                $this->warn("Eliminado {$sobrante->name}: ya no esta en la configuracion.");
            }
        }

        $this->newLine();
        $this->line(sprintf('%-8s %-42s %-12s %s', 'NOMBRE', 'URL', 'CAPACIDAD', 'ASIGNADOS'));

        foreach (WebtopContainer::query()->withCount('activeAssignments')->orderBy('id')->get() as $container) {
            $this->line(sprintf(
                '%-8s %-42s %-12s %d',
                $container->name,
                $container->url,
                $container->capacity === null ? 'sin limite' : (string) $container->capacity,
                $container->active_assignments_count
            ));
        }

        $containers = WebtopContainer::query()->withCount('activeAssignments')->get();

        // Solo se puede avisar de saturacion si todas las plazas son conocidas.
        $sinLimite = $containers->contains(fn (WebtopContainer $c): bool => $c->capacity === null);
        $total = $containers->sum(fn (WebtopContainer $c): int => $c->capacity ?? 0);

        $assigned = \App\Models\WorkspaceAssignment::query()->whereNull('released_at')->count();

        $this->newLine();
        $this->line("Escritorios asignados: {$assigned}" . ($sinLimite ? '' : " de {$total} plazas"));

        if (! $sinLimite && $assigned >= $total) {
            $this->warn('No quedan plazas libres. Anade contenedores o sube la capacidad antes de crear mas usuarios.');
        }

        return self::SUCCESS;
    }
}
