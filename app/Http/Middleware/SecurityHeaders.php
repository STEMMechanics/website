<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Client as PassportClient;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $oauthOrigins = $this->oauthCallbackOrigins();

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
            "object-src 'none'",
        ];
        if ($csp === '') {
            $requiredDirectives[] = $this->formActionDirective($oauthOrigins);
            $response->headers->set('Content-Security-Policy', implode('; ', $requiredDirectives));
        } else {
            $cspLower = strtolower($csp);
            foreach ($requiredDirectives as $directive) {
                $name = strtolower(strtok($directive, ' '));
                if (!str_contains($cspLower, $name)) {
                    $csp .= '; '.$directive;
                }
            }
            $response->headers->set('Content-Security-Policy', $this->replaceFormActionDirective($csp, $oauthOrigins));
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

    private function normalizeOrigin(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    /**
     * @return list<string>
     */
    private function oauthCallbackOrigins(): array
    {
        return PassportClient::query()
            ->where('revoked', false)
            ->get()
            ->flatMap(function (PassportClient $client): array {
                return array_map(
                    fn (mixed $redirectUri): string => $this->normalizeOrigin((string) $redirectUri),
                    is_array($client->redirect_uris) ? $client->redirect_uris : []
                );
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $origins
     */
    private function formActionDirective(array $origins): string
    {
        $sources = array_merge(["'self'"], $origins);

        return 'form-action '.implode(' ', array_values(array_unique($sources)));
    }

    /**
     * @param  list<string>  $origins
     */
    private function replaceFormActionDirective(string $csp, array $origins): string
    {
        $directive = $this->formActionDirective($origins);
        if (preg_match('/(?:^|;\s*)form-action\b[^;]*/i', $csp) === 1) {
            return (string) preg_replace('/(?:^|;\s*)form-action\b[^;]*/i', '; '.$directive, $csp, 1);
        }

        return trim($csp.'; '.$directive, '; ');
    }
}
