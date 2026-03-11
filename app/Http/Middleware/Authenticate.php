<?php

namespace App\Http\Middleware;

use App\Support\RememberedDeviceManager;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function __construct(
        AuthFactory $auth,
        private readonly RememberedDeviceManager $rememberedDeviceManager
    ) {
        parent::__construct($auth);
    }

    public function handle($request, Closure $next, ...$guards)
    {
        if (
            ! Auth::check()
            && $this->shouldAttemptRestore($request)
            && ($user = $this->rememberedDeviceManager->resolveRememberedUser($request))
        ) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return parent::handle($request, $next, ...$guards);
    }

    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }

    private function shouldAttemptRestore(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        return ! ($request->expectsJson() || $request->wantsJson());
    }
}
