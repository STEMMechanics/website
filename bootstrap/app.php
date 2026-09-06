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

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustedIngress::class);
        $middleware->trustHosts(at: fn () => array_map(
            fn (string $host): string => '^'.preg_quote($host, '/').'$',
            config('security.trusted_hosts', [])
        ), subdomains: false);
        $middleware->alias([
            'admin' => Admin::class,
            'auth' => Authenticate::class,
            'form.guard' => ProtectFormSubmission::class,
            'full-account' => EnsureFullAccount::class,
            'nocache' => NoCache::class,
            'shop.public' => EnsurePublicShopAvailable::class,
        ]);
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\RestoreRememberedDevice::class,
        );
        $middleware->web(append: [
            SecurityHeaders::class,
            NoCache::class,
            \App\Http\Middleware\RestoreRememberedDevice::class,
            LogoutAnonymizedUser::class,
            \App\Http\Middleware\RequirePrivilegedMfa::class,
            \App\Http\Middleware\CanonicalHost::class,
            TrackAnalytics::class,
            \App\Http\Middleware\ProfileRequests::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/square',
            'webhooks/stemcraft/server',
            'webhooks/minecraft/server',
            'webhooks/livekit',
            'webhooks/smsflow',
            'security/csp-reports',
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
