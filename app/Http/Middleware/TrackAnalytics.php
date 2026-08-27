<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackAnalytics
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $session = $request->session();
        $sessionToken = (string) $session->get('analytics_session_token', '');
        $isSessionEntry = $sessionToken === '';
        if ($sessionToken === '') {
            $sessionToken = Str::lower(Str::random(40));
            $session->put('analytics_session_token', $sessionToken);
        }

        $acquisition = $session->get('analytics_acquisition');
        if (! is_array($acquisition)) {
            $acquisition = $this->resolveAcquisition($request);
            $session->put('analytics_acquisition', $acquisition);
        }

        $route = $request->route();
        $routeName = is_string($route?->getName()) ? $route->getName() : null;
        $workshopId = $this->resolveWorkshopId($request);
        $searchTerm = $this->resolveSearchTerm($request, $routeName);
        $eventType = match (true) {
            $routeName === 'workshop.registration.redirect' => AnalyticsEvent::TYPE_REGISTRATION_CLICK,
            $searchTerm !== null => AnalyticsEvent::TYPE_SEARCH,
            default => AnalyticsEvent::TYPE_PAGE_VIEW,
        };

        AnalyticsEvent::create([
            'event_type' => $eventType,
            'session_token' => $sessionToken,
            'is_session_entry' => $isSessionEntry,
            'visitor_hash' => $this->resolveVisitorHash($request),
            'path' => $request->getPathInfo() ?: '/',
            'landing_path' => $acquisition['landing_path'],
            'route_name' => $routeName,
            'workshop_id' => $workshopId,
            'search_term' => $searchTerm,
            'referrer_host' => $this->resolveReferrerHost($request),
            'acquisition_source' => $acquisition['source'],
            'utm_source' => $acquisition['utm_source'],
            'utm_medium' => $acquisition['utm_medium'],
            'utm_campaign' => $acquisition['utm_campaign'],
            'utm_term' => $acquisition['utm_term'],
            'utm_content' => $acquisition['utm_content'],
            'http_method' => $request->method(),
            'created_at' => now(),
        ]);

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! (bool) config('analytics.enabled', true)) {
            return false;
        }

        $user = $request->user();
        if ($user instanceof User && $user->isAdmin()) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        if ($this->isBotRequest($request)) {
            return false;
        }

        $path = $request->getPathInfo() ?: '/';
        foreach ((array) config('analytics.ignore_path_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return false;
            }
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === 'workshop.recommendation.click') {
            return false;
        }
        if ($routeName === 'workshop.registration.redirect') {
            $location = trim((string) $response->headers->get('Location', ''));
            $scheme = strtolower((string) parse_url($location, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return false;
            }
        }

        foreach ((array) config('analytics.ignore_route_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && str_starts_with($routeName, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function isBotRequest(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return false;
        }

        foreach ((array) config('analytics.ignore_bot_user_agents', []) as $needle) {
            $needle = strtolower(trim((string) $needle));
            if ($needle !== '' && str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function resolveWorkshopId(Request $request): ?string
    {
        $workshop = $request->route('workshop');

        if (is_object($workshop) && method_exists($workshop, 'getKey')) {
            $id = trim((string) $workshop->getKey());

            return $id !== '' ? $id : null;
        }

        if (is_scalar($workshop)) {
            $id = trim((string) $workshop);

            return $id !== '' ? $id : null;
        }

        return null;
    }

    private function resolveSearchTerm(Request $request, ?string $routeName): ?string
    {
        if ($routeName !== 'search.index') {
            return null;
        }

        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return null;
        }

        return mb_substr($search, 0, 255);
    }

    private function resolveReferrerHost(Request $request): ?string
    {
        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer === '') {
            return null;
        }

        $host = trim((string) parse_url($referer, PHP_URL_HOST));

        return $host !== '' ? mb_substr($host, 0, 255) : null;
    }

    /**
     * @return array{landing_path: string, source: string, utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string}
     */
    private function resolveAcquisition(Request $request): array
    {
        $referrerHost = $this->resolveReferrerHost($request);
        $externalReferrer = $referrerHost !== null && ! $this->isInternalReferrerHost($referrerHost, $request)
            ? $referrerHost
            : null;
        $utmSource = $this->campaignValue($request, 'utm_source');

        return [
            'landing_path' => mb_substr($request->getPathInfo() ?: '/', 0, 255),
            'source' => $utmSource ?? $externalReferrer ?? 'Direct / unknown',
            'utm_source' => $utmSource,
            'utm_medium' => $this->campaignValue($request, 'utm_medium'),
            'utm_campaign' => $this->campaignValue($request, 'utm_campaign'),
            'utm_term' => $this->campaignValue($request, 'utm_term'),
            'utm_content' => $this->campaignValue($request, 'utm_content'),
        ];
    }

    private function isInternalReferrerHost(string $host, Request $request): bool
    {
        $host = strtolower(trim($host, ". \t\n\r\0\x0B"));
        if (in_array('stemmechanics', explode('.', $host), true)) {
            return true;
        }

        $internalHosts = array_merge(
            [(string) $request->getHost()],
            (array) config('analytics.internal_referrer_hosts', [])
        );

        foreach ($internalHosts as $internalHost) {
            $internalHost = strtolower(trim((string) $internalHost, ". \t\n\r\0\x0B"));
            if ($internalHost !== '' && ($host === $internalHost || str_ends_with($host, '.'.$internalHost))) {
                return true;
            }
        }

        return false;
    }

    private function campaignValue(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value));

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function resolveVisitorHash(Request $request): ?string
    {
        $ip = trim((string) $request->ip());
        $ua = trim((string) $request->userAgent());

        if ($ip === '' && $ua === '') {
            return null;
        }

        $key = (string) (config('app.key') ?: 'analytics-fallback-key');

        return hash_hmac('sha256', $ip.'|'.$ua, $key);
    }
}
