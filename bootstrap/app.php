<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Varios sistemas Laravel en el mismo dominio: el .env de cada uno no se publica en el entorno del
// proceso (putenv), así ningún sistema puede leer las variables de otro (base de datos, llaves, nombre…).
\Illuminate\Support\Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // ConvertirEntradaAMayusculas se retiró en RIC Condominios: el texto se guarda tal como se captura.
        ]);

        // Módulos heredados de RIC que se pueden ocultar (config/condominios.php).
        $middleware->alias(['modulo' => \App\Http\Middleware\ModuloActivo::class]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
