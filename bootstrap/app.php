<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\AdminMiddleware;
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
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'active' => ActiveUser::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/pakasir',
            'webhooks/sms-virtual/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default exception renderer is used.
    })->create();
