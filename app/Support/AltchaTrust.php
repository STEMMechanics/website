<?php

namespace App\Support;

use Illuminate\Http\Request;

class AltchaTrust
{
    private const SESSION_KEY = 'altcha_trusted_until';

    public static function trustMinutes(): int
    {
        return max(0, (int) config('security.altcha_trust_minutes', 5));
    }

    public static function shouldRequire(Request $request): bool
    {
        if (! (bool) config('security.altcha_enabled', true)) {
            return false;
        }

        return ! self::isTrusted($request);
    }

    public static function isTrusted(Request $request): bool
    {
        if ($request->user() !== null) {
            return true;
        }

        $until = (int) $request->session()->get(self::SESSION_KEY, 0);

        return $until > now()->getTimestamp();
    }

    public static function markVerified(Request $request): void
    {
        $minutes = self::trustMinutes();
        if ($minutes <= 0) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, now()->addMinutes($minutes)->getTimestamp());
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
