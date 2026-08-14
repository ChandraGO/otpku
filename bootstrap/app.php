<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\AdminMiddleware;
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
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'active' => ActiveUser::class,
            'api.key' => AuthenticateApiKey::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/paykita',
            'webhooks/sms-virtual/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default exception renderer is used.
    })->create();
