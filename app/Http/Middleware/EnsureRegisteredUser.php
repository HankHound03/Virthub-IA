<?php

namespace App\Http\Middleware;

use App\Support\ActiveUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige una cuenta registrada y activa. Los invitados no pasan.
 *
 * Responde siempre 403 en JSON: estas rutas son de la API interna de la
 * aplicacion y una redireccion ocultaria el motivo real al cliente.
 */
class EnsureRegisteredUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ActiveUser::isRegistered(ActiveUser::from($request))) {
            return response()->json(['error' => 'Debes iniciar sesion con usuario registrado.'], 403);
        }

        return $next($request);
    }
}
