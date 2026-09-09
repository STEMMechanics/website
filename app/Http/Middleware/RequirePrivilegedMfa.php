<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\RememberedDeviceManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequirePrivilegedMfa
{
    public static function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->password.'|'.$user->email.'|'.$user->tfa_secret, (string) config('app.key'));
    }

    public static function confirm(Request $request, User $user): void
    {
        $request->session()->put('privileged_mfa', [
            'fingerprint' => self::fingerprint($user),
            'expires' => now()->addHours(12)->timestamp,
        ]);
        app(RememberedDeviceManager::class)->refreshCurrentDeviceForUser($request, $user);
    }

    public static function hasSessionConfirmation(Request $request, User $user): bool
    {
        $confirmation = $request->session()->get('privileged_mfa', []);

        return $user->tfa_secret !== null && is_array($confirmation)
            && ($confirmation['expires'] ?? 0) > now()->timestamp
            && hash_equals(self::fingerprint($user), (string) ($confirmation['fingerprint'] ?? ''));
    }

    public static function confirmEnrolmentIdentity(Request $request, User $user): void
    {
        $request->session()->put('mfa_enrolment_identity', [
            'fingerprint' => self::enrolmentFingerprint($user),
            'expires' => now()->addMinutes(10)->timestamp,
        ]);
    }

    private static function enrolmentFingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->password.'|'.$user->email.'|'.$user->tfa_secret, (string) config('app.key'));
    }

    private static function canEnrol(Request $request, User $user): bool
    {
        $proof = $request->session()->get('mfa_enrolment_identity', []);

        return is_array($proof) && ($proof['expires'] ?? 0) > now()->timestamp
            && hash_equals(self::enrolmentFingerprint($user), (string) ($proof['fingerprint'] ?? ''));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! config('security.admin_mfa_required') || ! $user instanceof User || ! $user->isAdmin()) {
            return $next($request);
        }
        if ($user->tfa_secret === null && ! self::canEnrol($request, $user)
            && ! $request->routeIs('security.csp-report', 'logout', 'logout.show')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('auth.require_fresh_login', true);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Log in again before setting up two-factor authentication.'], 403);
            }

            return redirect()->route('login')
                ->with('message', 'Log in again with your password or an email link before setting up two-factor authentication.')
                ->with('message-type', 'info');
        }
        if (self::hasSessionConfirmation($request, $user)
            || app(RememberedDeviceManager::class)->hasPrivilegedVerification($request, $user)) {
            return $next($request);
        }
        if ($request->routeIs('security.mfa.*', 'security.csp-report', 'logout', 'logout.show')) {
            return $next($request);
        }
        // Only the account screen and initial enrolment endpoints remain available.
        if ($user->tfa_secret === null && ($request->routeIs('account.show', 'account.show.tfa', 'account.show.tfa.image', 'account.post.tfa'))) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Two-factor verification required.'], 403);
        }

        if ($user->tfa_secret === null) {
            return redirect()->route('account.show')
                ->with('message', 'Set up and verify two-factor authentication to continue as an administrator.')
                ->with('message-type', 'info');
        }

        return redirect()->route('security.mfa.show');
    }
}
