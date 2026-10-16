<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un usuario no puede tener dos escritorios ACTIVOS a la vez.
 *
 * Por que: 'released_at' a NULL significa "activa". Un indice unico sobre
 * (user_id, released_at) lo garantiza, porque SQL trata cada NULL como distinto:
 * se permiten muchas filas liberadas (cada una con su fecha) pero solo UNA con
 * released_at NULL.
 *
 * Sin esta restriccion aparecieron asignaciones duplicadas: un usuario tenia dos
 * filas activas al mismo contenedor. Eso rompe el aislamiento por dos vias:
 *
 *   - la ocupacion cuenta doble, asi que un contenedor de capacidad 1 figura
 *     como lleno cuando solo lo usa una persona;
 *   - dos rutas de codigo pueden crear asignaciones a la vez (por ejemplo el
 *     usuario abriendo la pagina mientras se reasigna) y ninguna lo detecta.
 *
 * Es idempotente y repara los datos existentes antes de crear el indice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspace_assignments')) {
            return;
        }

        $this->liberarDuplicados();

        if ($this->hasIndex('workspace_assignments', 'workspace_assignments_user_activa_unique')) {
            return;
        }

        Schema::table('workspace_assignments', function (Blueprint $table): void {
            $table->unique(['user_id', 'released_at'], 'workspace_assignments_user_activa_unique');
        });
    }

    public function down(): void
    {
        if (! $this->hasIndex('workspace_assignments', 'workspace_assignments_user_activa_unique')) {
            return;
        }

        Schema::table('workspace_assignments', function (Blueprint $table): void {
            $table->dropUnique('workspace_assignments_user_activa_unique');
        });
    }

    /**
     * Deja una sola asignacion activa por usuario: la mas reciente.
     *
     * Las demas se marcan como liberadas, no se borran: el historial de quien
     * uso cada escritorio es informacion util.
     */
    private function liberarDuplicados(): void
    {
        $duplicados = DB::table('workspace_assignments')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereNull('released_at')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicados as $duplicado) {
            // Se conserva la ultima y se liberan las anteriores.
            $conservar = DB::table('workspace_assignments')
                ->where('user_id', $duplicado->user_id)
                ->whereNull('released_at')
                ->orderByDesc('id')
                ->value('id');

            $liberadas = DB::table('workspace_assignments')
                ->where('user_id', $duplicado->user_id)
                ->whereNull('released_at')
                ->where('id', '!=', $conservar)
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'updated_at' => now(),
                ]);

            printf(
                "  usuario %s: %d asignacion(es) duplicada(s) liberada(s)\n",
                $duplicado->user_id,
                $liberadas
            );
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = Schema::getConnection()->select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $index]
            );

            return $rows !== [];
        }

        $rows = Schema::getConnection()->select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        return $rows !== [];
    }
};
