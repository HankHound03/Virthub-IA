<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puerto interno de cada escritorio.
 *
 * El panel de estado sondea cada contenedor por la red de Docker. Hasta ahora
 * daba por hecho el puerto 3000, que es el de los Webtop, pero no el de todos:
 * la maquina virtual con Windows (dockurr/windows) sirve su interfaz en el 8006.
 *
 * Con esta columna el puerto es un dato de cada contenedor y no una constante del
 * codigo: anadir uno que use otro puerto no requiere tocar nada.
 *
 * Es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webtop_containers')) {
            return;
        }

        if (Schema::hasColumn('webtop_containers', 'internal_port')) {
            return;
        }

        Schema::table('webtop_containers', function (Blueprint $table): void {
            // null = usar el puerto por defecto de la configuracion.
            $table->unsignedSmallInteger('internal_port')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('webtop_containers')
            || ! Schema::hasColumn('webtop_containers', 'internal_port')) {
            return;
        }

        Schema::table('webtop_containers', function (Blueprint $table): void {
            $table->dropColumn('internal_port');
        });
    }
};
