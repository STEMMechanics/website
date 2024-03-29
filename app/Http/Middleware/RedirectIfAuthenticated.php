<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Request     $request   Request.
     * @param  \Closure    $next      Closure.
     * @param  string|null ...$guards Guards.
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
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
