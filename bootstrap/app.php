<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\EnsureFullAccount;
use App\Http\Middleware\EnsurePublicShopAvailable;
use App\Http\Middleware\LogoutAnonymizedUser;
use App\Http\Middleware\NoCache;
use App\Http\Middleware\ProfileRequests;
use App\Http\Middleware\ProtectFormSubmission;
use App\Http\Middleware\RenderFragment;
use App\Http\Middleware\RequirePrivilegedMfa;
use App\Http\Middleware\RestoreRememberedDevice;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackAnalytics;
use App\Http\Middleware\TrackStoreCheckout;
use App\Http\Middleware\TrustedIngress;
use App\Services\SiteErrorNotificationService;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trimStrings(except: ['verification_text']);
        $middleware->replace(TrustProxies::class, TrustedIngress::class);
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
            AuthenticatesRequests::class,
            RestoreRememberedDevice::class,
        );
        $middleware->web(append: [
            SecurityHeaders::class,
            NoCache::class,
            RestoreRememberedDevice::class,
            LogoutAnonymizedUser::class,
            RequirePrivilegedMfa::class,
            CanonicalHost::class,
            TrackAnalytics::class,
            TrackStoreCheckout::class,
            ProfileRequests::class,
            RenderFragment::class,
        ]);
        $middleware->preventRequestForgery(except: [
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
        $exceptions->report(function (Throwable $exception): void {
            app(SiteErrorNotificationService::class)->notify(
                $exception,
                app()->bound('request') ? request() : null
            );
        });
    })->create();
