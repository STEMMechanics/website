<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Request                                       $request   Request.
     * @param  Closure(Request): (Response|RedirectResponse) $next      Next.
     * @param  string|null                                   ...$guards Guards.
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) === true ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check() === true) {
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
