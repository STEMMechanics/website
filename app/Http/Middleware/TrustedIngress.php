<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedIngress
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only literal addresses/ranges are accepted, including when configuration is absent.
        // Avoid framework host-based cloud autodetection or REMOTE_ADDR wildcard aliases.
        $proxies = array_values(array_filter(config('security.trusted_proxies', []), static function (string $proxy): bool {
            $parts = explode('/', $proxy, 2);
            return filter_var($parts[0], FILTER_VALIDATE_IP) !== false
                && (! isset($parts[1]) || (ctype_digit($parts[1]) && (int) $parts[1] <= (str_contains($parts[0], ':') ? 128 : 32)));
        }));
        $request->setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

        return $next($request);
    }
}
