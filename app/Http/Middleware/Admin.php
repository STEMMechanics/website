<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /* @var User $user */
        $user = Auth::user();

        if ($user) {
            if ($user->isAdmin()) {
                return $next($request);
            }

            abort(403, 'Forbidden');
        }

        if ($request->isMethod('GET') && ! $request->expectsJson() && ! $request->ajax()) {
            session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('login');
    }
}
