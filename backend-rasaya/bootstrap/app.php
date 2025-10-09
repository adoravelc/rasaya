<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'gurujenis' => \App\Http\Middleware\EnsureGuruJenis::class,
        ]);
    })
    ->withProviders([
        \App\Providers\AuthServiceProvider::class, // ← tambahkan ini
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
