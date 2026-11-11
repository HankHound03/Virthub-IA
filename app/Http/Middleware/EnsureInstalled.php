<?php

namespace App\Http\Middleware;

use App\Services\UserStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lleva al asistente de instalacion cuando la aplicacion esta recien instalada.
 *
 * Sin esto, una instalacion nueva se quedaba en la portada con un aviso de
 * invitado y habia que saberse la direccion /install.
 *
 * Solo redirige cuando el asistente esta realmente disponible para esta
 * peticion: en produccion, con INSTALL_KEY configurada, no se toca nada.
 */
class EnsureInstalled
{
    public function __construct(private readonly UserStore $users) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->users->hasAdminAccount()) {
            return $next($request);
        }

        // El propio asistente, sus formularios y los archivos no se tocan.
        if ($request->is('install', 'install/*', 'up', 'build/*', 'storage/*')
            || $request->ajax()
            || $request->expectsJson()) {
            return $next($request);
        }

        if (! $this->installerIsOpen($request)) {
            return $next($request);
        }

        return redirect('/install');
    }

    private function installerIsOpen(Request $request): bool
    {
        $clave = (string) config('installation.key', '');

        // Con clave configurada, el asistente se abre solo con esa clave.
        if ($clave !== '') {
            return false;
        }

        if (app()->environment('testing')) {
            return true;
        }

        return (bool) config('virthub.installation.allow_localhost_without_key')
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
    }
}
