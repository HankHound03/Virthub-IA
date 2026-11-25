<?php

namespace App\Http\Middleware;

use App\Support\ActiveUser;
use App\Support\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte el "cambia tu contrasena" en una obligacion.
 *
 * Antes solo era un redirect al entrar: quien tuviera la contrasena temporal
 * podia irse directo a /foro, /chat o /contenedor sin cambiarla nunca. Ahora,
 * mientras la cuenta tenga must_change_password, cualquier ruta que no sea el
 * propio cambio de contrasena o la salida devuelve a /cambiar-password.
 */
class EnsurePasswordChanged
{
    /**
     * Rutas que siguen funcionando con la contrasena temporal puesta.
     */
    private const PERMITIDAS = [
        'cambiar-password',
        'cambiar-password/*',
        'logout',
        'up',
        'storage/*',
        'build/*',
        'install',
        'install/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = ActiveUser::from($request);

        if ($usuario === null || ! ActiveUser::isRegistered($usuario)) {
            return $next($request);
        }

        if (! PasswordPolicy::debeCambiar(ActiveUser::username($usuario))) {
            return $next($request);
        }

        if ($request->is(...self::PERMITIDAS)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tienes que cambiar la contrasena antes de seguir.',
                'must_change_password' => true,
            ], 423);
        }

        return redirect('/cambiar-password')
            ->with('error', 'Antes de seguir tienes que cambiar la contrasena que te dieron.');
    }
}
