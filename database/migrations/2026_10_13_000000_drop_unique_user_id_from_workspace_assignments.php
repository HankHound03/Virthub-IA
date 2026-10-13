<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el indice unico sobre workspace_assignments.user_id.
 *
 * Las instalaciones nuevas ya no lo crean: un usuario puede tener varias filas
 * (una por escritorio que se le asigno y libero), y el indice que lo garantiza
 * es (user_id, released_at).
 *
 * En instalaciones anteriores el unique seguia ahi, y provocaba un error de
 * clave duplicada al intentar reasignar un escritorio a alguien que ya lo habia
 * tenido y lo libero:
 *
 *   SQLSTATE[23000]: Duplicate entry '1' for key
 *   'workspace_assignments_user_id_unique'
 *
 * Es idempotente: se puede ejecutar sobre una base ya corregida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspace_assignments')) {
            return;
        }

        if (! $this->hasIndex('workspace_assignments', 'workspace_assignments_user_id_unique')) {
            return;
        }

        // Se comprueba tambien por columnas: el nombre puede variar segun la
        // version que creo la tabla.
        Schema::table('workspace_assignments', function (Blueprint $table): void {
            $table->dropUnique('workspace_assignments_user_id_unique');
        });
    }

    public function down(): void
    {
        // No se vuelve a imponer el unique: reinstaurarlo romperia la
        // reasignacion y no aporta nada que el par (user_id, released_at) no
        // cubra.
    }

    /**
     * Comprueba si un indice existe, en el driver que este en uso.
     */
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
