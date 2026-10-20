<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de "debe cambiar la contrasena".
 *
 * Cuando un administrador crea una cuenta y deja que el sistema genere la
 * contrasena al azar, esa contrasena la conoce quien la creo. Para que el dueño
 * de la cuenta ponga una suya en el primer acceso, se guarda esta marca y la
 * aplicacion le pide el cambio antes de dejarlo trabajar.
 *
 * No se activa cuando la contrasena la escribe el propio administrador: en ese
 * caso ya es una decision suya.
 *
 * Es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
