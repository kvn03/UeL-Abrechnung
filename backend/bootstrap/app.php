<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HIER HINZUFÜGEN:
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'gs' => \App\Http\Middleware\IsGeschaeftsstelle::class,
            'al' => \App\Http\Middleware\IsAbteilungsleiter::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
