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
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // The mobile app authorizes private channels with its Sanctum token.
        attributes: ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->redirectGuestsTo('/signin');
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            '2fa' => \App\Http\Middleware\TwoFactorMiddleware::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'demo.routes' => \App\Http\Middleware\RestrictDemoRoutes::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\ApplyGlobalSettings::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
