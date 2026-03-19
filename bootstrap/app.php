<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureFullAccount;
use App\Http\Middleware\EnsurePublicShopAvailable;
use App\Http\Middleware\NoCache;
use App\Http\Middleware\ProtectFormSubmission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackAnalytics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => Admin::class,
            'auth' => Authenticate::class,
            'form.guard' => ProtectFormSubmission::class,
            'full-account' => EnsureFullAccount::class,
            'nocache' => NoCache::class,
            'shop.public' => EnsurePublicShopAvailable::class,
        ]);
        $middleware->web(append: [
            SecurityHeaders::class,
            TrackAnalytics::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/square',
            'webhooks/stemcraft/server',
            'webhooks/minecraft/server',
            'unsubscribe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();
