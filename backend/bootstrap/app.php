<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',   // ← IMPORTANT: branche les routes API
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middlewares de route
        $middleware->alias([
            'dev.token'     => \App\Http\Middleware\DevToken::class,
            'ephemeral.jwt' => \App\Http\Middleware\VerifyEphemeralJwt::class,
        ]);

        // (facultatif) middlewares globaux / groupes :
        // $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        // $middleware->group('api', [\Illuminate\Routing\Middleware\SubstituteBindings::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
