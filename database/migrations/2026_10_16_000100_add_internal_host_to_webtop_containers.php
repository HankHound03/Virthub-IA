<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host interno de cada escritorio.
 *
 * El panel de estado sondeaba cada escritorio por su NOMBRE de contenedor
 * (http://ct8:3000). Eso solo funciona para los que estan en la red de Docker de
 * este equipo: los escritorios que viven en otra maquina de la red local no
 * resuelven por nombre y aparecian como caidos aunque estuvieran funcionando.
 *
 * Con esta columna, un escritorio puede declarar su host real (por ejemplo
 * 192.168.1.9) y el sondeo usa ese en lugar del nombre.
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

        if (Schema::hasColumn('webtop_containers', 'internal_host')) {
            return;
        }

        Schema::table('webtop_containers', function (Blueprint $table): void {
            // null = usar el nombre del contenedor (red de Docker local).
            $table->string('internal_host')->nullable()->after('internal_port');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('webtop_containers')
            || ! Schema::hasColumn('webtop_containers', 'internal_host')) {
            return;
        }

        Schema::table('webtop_containers', function (Blueprint $table): void {
            $table->dropColumn('internal_host');
        });
    }
};
