<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adapta una base de datos que YA tenia las tablas creadas con el esquema
     * anterior.
     *
     * La migracion original (0001_01_01_000002) ya se corrigio para las
     * instalaciones nuevas, pero las existentes conservan:
     *   - webtop_containers sin la columna capacity;
     *   - workspace_assignments con un indice unico sobre user_id que impide
     *     volver a asignar un escritorio tras liberarlo.
     *
     * Es idempotente: cada paso comprueba el estado real antes de actuar, de
     * modo que se puede reintentar sin dejar el esquema a medias.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('webtop_containers', 'capacity')) {
            Schema::table('webtop_containers', function (Blueprint $table) {
                $table->unsignedInteger('capacity')->nullable()->after('url');
            });
        }

        $this->dropUniqueOnUserId();

        if (! $this->hasIndex('workspace_assignments', 'workspace_assignments_user_id_released_at_index')) {
            Schema::table('workspace_assignments', function (Blueprint $table) {
                $table->index(['user_id', 'released_at']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('workspace_assignments', 'workspace_assignments_user_id_released_at_index')) {
            Schema::table('workspace_assignments', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'released_at']);
            });
        }

        if (Schema::hasColumn('webtop_containers', 'capacity')) {
            Schema::table('webtop_containers', function (Blueprint $table) {
                $table->dropColumn('capacity');
            });
        }
    }

    /**
     * Suelta el indice unico sobre user_id si existe.
     *
     * En MariaDB puede estar gestionado junto a la clave foranea, asi que se
     * comprueba por information_schema y se usa SQL directo. En SQLite la
     * correccion se aplica desde la migracion original, porque SQLite no permite
     * soltar un indice que respalda una restriccion unica.
     */
    private function dropUniqueOnUserId(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! $this->hasIndex('workspace_assignments', 'workspace_assignments_user_id_unique')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE workspace_assignments DROP INDEX workspace_assignments_user_id_unique');
        } catch (\Throwable $e) {
            // Ya no existe: nada que hacer.
        }
    }

    /**
     * Comprueba si un indice existe, segun el motor de base de datos.
     *
     * No se puede usar information_schema en SQLite (no existe), y en las
     * pruebas eso hacia creer que el indice faltaba cuando la migracion original
     * ya lo habia creado.
     */
    private function hasIndex(string $table, string $index): bool
    {
        try {
            $connection = DB::connection();

            if ($connection->getDriverName() === 'sqlite') {
                $rows = DB::select(
                    "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                    [$table, $index]
                );

                return $rows !== [];
            }

            $rows = DB::select(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$connection->getDatabaseName(), $table, $index]
            );

            return $rows !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }
};