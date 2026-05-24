<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureFullAccount;
use App\Http\Middleware\EnsurePublicShopAvailable;
use App\Http\Middleware\LogoutAnonymizedUser;
use App\Http\Middleware\NoCache;
use App\Http\Middleware\ProtectFormSubmission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackAnalytics;
use App\Services\SiteErrorNotificationService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->alias([
            'admin' => Admin::class,
            'auth' => Authenticate::class,
            'form.guard' => ProtectFormSubmission::class,
            'full-account' => EnsureFullAccount::class,
            'nocache' => NoCache::class,
            'shop.public' => EnsurePublicShopAvailable::class,
        ]);
        $middleware->web(append: [
            LogoutAnonymizedUser::class,
            SecurityHeaders::class,
            TrackAnalytics::class,
            NoCache::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/square',
            'webhooks/stemcraft/server',
            'webhooks/minecraft/server',
            'webhooks/livekit',
            'unsubscribe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $exception): void {
            app(SiteErrorNotificationService::class)->notify(
                $exception,
                app()->bound('request') ? request() : null
            );
        });
    })->create();
