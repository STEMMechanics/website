<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePrivilegedMfa
{
    public static function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->tfa_secret, (string) config('app.key'));
    }

    public static function confirm(Request $request, User $user): void
    {
        $request->session()->put('privileged_mfa', [
            'fingerprint' => self::fingerprint($user),
            'expires' => now()->addHours(12)->timestamp,
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! config('security.admin_mfa_required') || ! $user instanceof User || ! $user->isAdmin()) {
            return $next($request);
        }
        $confirmation = $request->session()->get('privileged_mfa', []);
        if (is_array($confirmation) && ($confirmation['expires'] ?? 0) > time()
            && hash_equals(self::fingerprint($user), (string) ($confirmation['fingerprint'] ?? ''))) {
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

        return redirect()->route($user->tfa_secret === null ? 'account.show' : 'security.mfa.show')
            ->with('message', 'Set up and verify two-factor authentication to continue as an administrator.')
            ->with('message-type', 'info');
    }
}
