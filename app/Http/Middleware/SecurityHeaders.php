<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Hide PHP runtime/version details from response headers.
        $response->headers->remove('X-Powered-By');

        // Prevent the site from being embedded in frames on other origins.
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // CSP defense-in-depth without restricting existing CDN assets.
        $csp = trim((string) $response->headers->get('Content-Security-Policy', ''));
        $requiredDirectives = [
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];
        if ($csp === '') {
            $response->headers->set('Content-Security-Policy', implode('; ', $requiredDirectives));
        } else {
            $cspLower = strtolower($csp);
            foreach ($requiredDirectives as $directive) {
                $name = strtolower(strtok($directive, ' '));
                if (!str_contains($cspLower, $name)) {
                    $csp .= '; '.$directive;
                }
            }
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Stop MIME sniffing and enforce declared Content-Type.
        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        // Limit referrer leakage while preserving basic analytics/navigation.
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // Disable browser features the app does not require.
        if (!$response->headers->has('Permissions-Policy')) {
            $response->headers->set(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=(self), usb=()'
            );
        }

        // Enforce HTTPS on subsequent requests when this request is secure.
        if ($request->isSecure() && !$response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
