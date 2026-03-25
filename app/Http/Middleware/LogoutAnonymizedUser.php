<?php

namespace App\Http\Middleware;

use App\Support\RememberedDeviceManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutAnonymizedUser
{
    public function __construct(
        private readonly RememberedDeviceManager $rememberedDeviceManager
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->isAnonymized()) {
            $this->rememberedDeviceManager->forgetCurrentDevice($request, $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
