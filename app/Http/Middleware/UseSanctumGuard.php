<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UseSanctumGuard
{
    /**
     * Handle an incoming request.
     *
     * @param Request  $request Request object.
     * @param  \Closure $next    Closure object.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');
        return $next($request);
    }
}
