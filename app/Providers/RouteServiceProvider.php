<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';


    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot(): void
    {
        // RateLimiter::for('api', function (Request $request) {
        //     return Limit::perMinute(60)->by($request->user()?->id !== null ?: $request->ip());
        // });

        $rateLimitEnabled = true;
        $user = auth()->user();

        if (app()->environment('testing')) {
            $rateLimitEnabled = false;
        } elseif ($user !== null && $user->hasPermission('admin/ratelimit') === true) {
            // Admin users with the "admin/ratelimit" permission are not rate limited
            $rateLimitEnabled = false;
        }

        if ($rateLimitEnabled === true) {
            RateLimiter::for('api', function (Request $request) {
                return Limit::perMinute(180)->by($request->user()?->id ?: $request->ip());
            });
        } else {
            RateLimiter::for('api', function () {
                return Limit::none();
            });
        }

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        Route::macro('apiAttachmentResource', function ($uri, $controller) {
            $singularUri = Str::singular($uri);

            Route::get("$uri/{{$singularUri}}/attachments", [$controller, 'getAttachments'])
                ->name("{{$singularUri}}.attachments.index");

            Route::post("$uri/{{$singularUri}}/attachments", [$controller, 'storeAttachment'])
                ->name("{{$singularUri}}.attachments.store");

            Route::match(['put', 'patch'], "$uri/{{$singularUri}}/attachments", [$controller, 'updateAttachments'])
                ->name("{{$singularUri}}.attachments.update");

            Route::delete("$uri/{{$singularUri}}/attachments/{medium}", [$controller, 'deleteAttachment'])
                ->name("{{$singularUri}}.attachments.destroy");
        });
    }
}
