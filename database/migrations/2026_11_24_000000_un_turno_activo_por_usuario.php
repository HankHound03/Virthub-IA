<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una sola asignacion activa por usuario.
 *
 * El indice anterior era unique(user_id, released_at), pero en MySQL y SQLite dos
 * NULL se consideran distintos: no impedia nada. Se anade una columna generada
 * que vale 1 cuando la asignacion esta activa y NULL cuando no lo esta; el indice
 * unique(user_id, activa) si impide dos activas y deja convivir todas las
 * liberadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspace_assignments')) {
            return;
        }

        if (Schema::hasColumn('workspace_assignments', 'activa')) {
            return;
        }

        Schema::table('workspace_assignments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('activa')
                ->nullable()
                ->storedAs('case when released_at is null then 1 else null end');
        });

        // El indice viejo no sirve; estorba mas de lo que ayuda.
        try {
            DB::statement('drop index if exists workspace_assignments_user_id_released_at_unique');
        } catch (\Throwable $e) {
            // Puede llamarse de otra forma segun el motor; el nuevo indice basta.
        }

        Schema::table('workspace_assignments', function (Blueprint $table): void {
            $table->unique(['user_id', 'activa'], 'workspace_assignments_un_turno_activo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspace_assignments') || ! Schema::hasColumn('workspace_assignments', 'activa')) {
            return;
        }

        Schema::table('workspace_assignments', function (Blueprint $table): void {
            $table->dropUnique('workspace_assignments_un_turno_activo');
            $table->dropColumn('activa');
        });
    }
};
