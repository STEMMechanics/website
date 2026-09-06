<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = rtrim((string) config('app.url'), '/');
        if (config('security.canonical_redirect') && $request->isMethodSafe()
            && ! $request->hasAny(['signature', 'token', 'webhook_secret'])
            && ! $request->is('webhooks/*', 'up')
            && $request->getSchemeAndHttpHost() !== $origin) {
            return redirect()->away($origin.$request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
