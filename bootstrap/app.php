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
