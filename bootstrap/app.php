<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\NoCache;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => Admin::class,
            'nocache' => NoCache::class,
        ]);
        $middleware->web(append: [
            SecurityHeaders::class,
            TrackAnalytics::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/square',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

    })->create();
