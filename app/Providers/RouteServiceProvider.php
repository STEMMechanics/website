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

        Route::macro('apiAddendumResource', function ($addendum, $uri, $controller) {
            $singularUri = Str::singular($uri);
            $pluralAddendumLC = strtolower(Str::plural($addendum));
            $pluralAddendumTC = ucfirst($pluralAddendumLC);
            $singularAddendumTC = Str::singular($pluralAddendumTC);

            Route::get("$uri/{{$singularUri}}/{{$pluralAddendumLC}}", [$controller, "get{{$pluralAddendumTC}}"])
                ->name("{{$singularUri}}.{{$pluralAddendumLC}}.index");

            Route::post("$uri/{{$singularUri}}/{{$pluralAddendumLC}}", [$controller, "store{{$singularAddendumTC}}"])
                ->name("{{$singularUri}}.{{$pluralAddendumLC}}.store");

            Route::match(
                ['put', 'patch'],
                "$uri/{{$singularUri}}/{{$pluralAddendumLC}}",
                [$controller, "update{{$pluralAddendumTC}}"]
            )
                ->name("{{$singularUri}}.{{$pluralAddendumLC}}.update");

            Route::delete(
                "$uri/{{$singularUri}}/{{$pluralAddendumLC}}/{medium}",
                [$controller,"delete{{$singularAddendumTC}}"]
            )
                ->name("{{$singularUri}}.{{$pluralAddendumLC}}.destroy");
        });
    }
}
