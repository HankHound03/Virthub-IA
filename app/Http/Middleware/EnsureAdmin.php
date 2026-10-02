<?php

namespace App\Http\Middleware;

use App\Support\ActiveUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige el rol admin.
 *
 * Responde 403 en JSON para que el cliente reciba el motivo; las vistas del
 * panel comprueban el rol antes de enlazar a estas rutas.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ActiveUser::isAdmin(ActiveUser::from($request))) {
            return response()->json(['error' => 'Solo el admin puede usar esta IA.'], 403);
        }

        return $next($request);
    }
}
