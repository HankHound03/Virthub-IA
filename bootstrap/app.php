<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureRegisteredUser;
use App\Http\Middleware\ResolveActiveUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Confianza en el proxy.
         *
         * La aplicacion vive detras de Cloudflare Tunnel y, en produccion,
         * tambien de Caddy. Los dos hablan HTTP hacia el contenedor, pero el
         * navegador viene por HTTPS: sin esto, la aplicacion creeria que la visita
         * es por HTTP y generaria enlaces http:// dentro de una pagina https, que
         * el navegador bloquea como contenido mixto.
         *
         * Se aceptan las cabeceras X-Forwarded-* de cualquier origen porque el
         * contenedor solo es alcanzable desde la red interna de Docker (el tunel y
         * Caddy). Si algun dia la aplicacion quedara expuesta de forma directa,
         * esto habria que limitarlo a las IPs de Cloudflare.
         */
        $middleware->trustProxies(at: '*');

        // El usuario activo se resuelve una sola vez por peticion y queda
        // disponible para los controladores y el middleware de autorizacion.
        $middleware->web(append: [
            ResolveActiveUser::class,
        ]);

        $middleware->alias([
            'registered' => EnsureRegisteredUser::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
