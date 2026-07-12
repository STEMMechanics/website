<?php

namespace App\Providers;

use App\Contracts\ContentFilter;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SiteOption;
use App\Models\Ticket;
use App\Models\User;
use App\Support\PassportKeyManager;
use App\Observers\AuditLogObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\TicketPolicy;
use App\Support\ShopAvailability;
use App\Support\SiteOptionContentFilter;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ContentFilter::class, SiteOptionContentFilter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensurePdfArtifactDirectoriesExist();
        app(PassportKeyManager::class)->ensureKeysExist();
        Passport::tokensCan(config('openid.passport.tokens_can'));
        Passport::authorizationView('auth.oauth.authorize');

        if ($this->app->environment('local')) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        } else {
            // Non-local environments must never fall back into Vite hot mode, even
            // if a stale hot file was left behind by a previous deploy.
            Vite::useHotFile(storage_path('framework/vite.disabled'));
            File::delete(public_path('hot'));
            File::delete(storage_path('framework/vite.hot'));
        }

        RateLimiter::for('login', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [Limit::perMinute(6)->by($request->ip().'|'.$email)];
        });

        RateLimiter::for('magic-link', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [Limit::perMinute(5)->by($request->ip().'|'.$email)];
        });

        RateLimiter::for('invoice-public', function (Request $request): array {
            return [Limit::perMinute(20)->by($request->ip())];
        });

        RateLimiter::for('public-form', function (Request $request): array {
            $routeKey = (string) ($request->route()?->getName() ?? $request->path());
            $maxAttempts = max(1, (int) config('security.form_protection.rate_limit_per_minute', 5));

            return [Limit::perMinute($maxAttempts)->by($request->ip().'|'.$routeKey)];
        });

        $appUrl = (string) config('app.url');
        $shouldForceHttps = str_starts_with($appUrl, 'https://') || $this->app->environment('production');

        if ($shouldForceHttps) {
            URL::forceScheme('https');
            // Keep web requests on the current host (avoids session loss from host flips,
            // e.g. www vs non-www). Force root URL only for console-generated links.
            if ($appUrl !== '' && $this->app->runningInConsole()) {
                URL::forceRootUrl($appUrl);
            }
        }

        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Event::listen(Login::class, function (Login $event): void {
            $request = app()->bound('request') ? request() : null;
            $actorUserId = (string) $event->user->getAuthIdentifier();
            if (! User::query()->whereKey($actorUserId)->exists()) {
                $actorUserId = null;
            }

            AuditLog::query()->create([
                'event' => 'login',
                'auditable_type' => User::class,
                'auditable_id' => (string) $event->user->getAuthIdentifier(),
                'actor_user_id' => $actorUserId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'old_values' => null,
                'new_values' => [
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ],
            ]);
        });

        User::observe(AuditLogObserver::class);
        Invoice::observe(AuditLogObserver::class);
        Payment::observe(AuditLogObserver::class);
        Ticket::observe(AuditLogObserver::class);

        Blade::directive('includeSVG', function ($arguments) {
            list($path, $styles) = array_pad(explode(',', str_replace(['(', ')', ' ', "'"], '', $arguments), 2), 2, '');
            $svgContent = file_get_contents(public_path($path));

            if ($svgContent === false) {
                return '';
            }

            // Inline SVGs must not include XML or DOCTYPE declarations because
            // they can be parsed as PHP when `short_open_tag` is enabled.
            $svgContent = preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $svgContent) ?? $svgContent;
            $svgContent = preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i', '', $svgContent) ?? $svgContent;

            if ($styles !== '') {
                $svgContent = str_replace('<svg ', '<svg style="'.$styles.'" ', $svgContent);
            }

            return $svgContent;
        });

        View::composer('components.layout', function ($view): void {
            $notice = (string) config('app.notice', '');

            try {
                if (Schema::hasTable('site_options')) {
                    $siteOptionNotice = trim((string) SiteOption::value('app.notice'));
                    if ($siteOptionNotice !== '') {
                        $notice = $siteOptionNotice;
                    }
                }
            } catch (Throwable) {
                // Keep env-based fallback if site options table/model is unavailable.
            }

            $view->with('appNotice', $notice);
        });

        View::composer(['components.layout', 'components.navbar', 'components.footer'], function ($view): void {
            $shopAvailability = app(ShopAvailability::class);

            $view->with('publicShopAvailable', $shopAvailability->isPubliclyAvailable());
        });
    }

    private function ensurePdfArtifactDirectoriesExist(): void
    {
        /** @var array<int, mixed> $paths */
        $paths = [
            config('dompdf.options.font_dir'),
            config('dompdf.options.font_cache'),
        ];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            File::ensureDirectoryExists($path);
        }
    }
}
