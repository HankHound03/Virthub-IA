<?php

namespace App\Support;

use App\Services\DatabaseUserStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Politica de contrasenas: quien esta obligado a cambiarla.
 *
 * Vive aqui y no en el controlador para que el middleware y el login usen la
 * misma respuesta.
 */
class PasswordPolicy
{
    public static function debeCambiar(string $username): bool
    {
        $username = trim($username);

        if ($username === '') {
            return false;
        }

        try {
            if (! Schema::hasColumn('users', 'must_change_password')) {
                return false;
            }

            return (bool) DB::table('users')
                ->where('username', $username)
                ->value('must_change_password');
        } catch (\Throwable $e) {
            // Sin base de datos (o con el almacen JSON) no hay nada que exigir.
            return false;
        }
    }
}
