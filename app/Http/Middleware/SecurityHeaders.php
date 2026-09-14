<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();

        /** @var Response $response */
        $response = $next($request);

        // Rocket Loader recreates script elements by copying attributes, which loses
        // browser-hidden CSP nonces. Opt out trusted scripts, including Vite/Livewire
        // output, before Cloudflare sees the HTML. Never grant a nonce to new scripts.
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html') && is_string($response->getContent())) {
            $html = $response->getContent();
            $updated = preg_replace_callback('/<script\b[^>]*>/i', static function (array $match) use ($nonce): string {
                if (! preg_match('/\snonce=([\'"])'.preg_quote($nonce, '/').'\1/', $match[0]) || preg_match('/\sdata-cfasync\s*=/i', $match[0])) {
                    return $match[0];
                }

                // Cloudflare requires this attribute to precede src.
                return preg_replace('/^<script\b/i', '<script data-cfasync="false"', $match[0]);
            }, $html);
            if ($updated !== $html) {
                $original = $response instanceof \Illuminate\Http\Response ? $response->getOriginalContent() : null;
                $response->setContent($updated);
                if ($response instanceof \Illuminate\Http\Response) {
                    $response->original = $original;
                }
                $response->headers->remove('Content-Length');
            }
        }

        if (! config('security.indexable', false) || $request->routeIs('admin.*', 'account.*', 'shop.cart*', 'shop.checkout*', 'shop.order*', 'shop.payment*', 'workshop.ticket.flow.*') || $request->is('admin', 'admin/*', 'account', 'account/*', 'login', 'register', 'tickets', 'tickets/*', 'invoices/*', 'quotes/*', 'cart', 'checkout', 'checkout/*') || $request->hasAny(['token', 'signature'])) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->hasAny(['token', 'signature'])) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        if (config('security.csp_report_only', true)) {
            $response->headers->set('Content-Security-Policy-Report-Only', "script-src 'self' 'nonce-{$nonce}'; object-src 'none'; base-uri 'self'; report-uri /security/csp-reports");
        }

        // Hide PHP runtime/version details from response headers.
        $response->headers->remove('X-Powered-By');

        // Prevent the site from being embedded in frames on other origins.
        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // CSP defense-in-depth without restricting existing CDN assets.
        $csp = trim((string) $response->headers->get('Content-Security-Policy', ''));
        $requiredDirectives = [
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];
        if ($csp === '') {
            $requiredDirectives[] = $this->formActionDirective();
            $response->headers->set('Content-Security-Policy', implode('; ', $requiredDirectives));
        } else {
            $cspLower = strtolower($csp);
            foreach ($requiredDirectives as $directive) {
                $name = strtolower(strtok($directive, ' '));
                if (! str_contains($cspLower, $name)) {
                    $csp .= '; '.$directive;
                }
            }
            $response->headers->set('Content-Security-Policy', $this->replaceFormActionDirective($csp));
        }

        // Stop MIME sniffing and enforce declared Content-Type.
        if (! $response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        // Limit referrer leakage while preserving basic analytics/navigation.
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // Disable browser features the app does not require.
        if (! $response->headers->has('Permissions-Policy')) {
            $response->headers->set(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=(self), usb=()'
            );
        }

        // Enforce HTTPS on subsequent requests when this request is secure.
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function formActionDirective(): string
    {
        return "form-action 'self'";
    }

    private function replaceFormActionDirective(string $csp): string
    {
        $directive = $this->formActionDirective();
        if (preg_match('/(?:^|;\s*)form-action\b[^;]*/i', $csp) === 1) {
            return (string) preg_replace('/(?:^|;\s*)form-action\b[^;]*/i', '; '.$directive, $csp, 1);
        }

        return trim($csp.'; '.$directive, '; ');
    }
}
