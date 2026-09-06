<?php

namespace App\Http\Middleware;

use App\Support\RememberedDeviceManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestoreRememberedDevice
{
    public function __construct(private readonly RememberedDeviceManager $devices) {}

    public function handle(Request $request, Closure $next)
    {
        // Restore page visits after the short-lived session expires. Do not turn
        // background API calls or form submissions into an implicit sign-in.
        // The login controller handles explicit sign-in tokens and intended URLs.
        if (!Auth::check() && in_array($request->method(), ['GET', 'HEAD'], true)
            && !$request->expectsJson() && !$request->wantsJson()
            && !$request->routeIs('login')
            && ($user = $this->devices->resolveRememberedUser($request))) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return $next($request);
    }
}
