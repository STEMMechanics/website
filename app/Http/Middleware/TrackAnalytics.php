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
        if ($sessionToken === '') {
            $sessionToken = Str::lower(Str::random(40));
            $session->put('analytics_session_token', $sessionToken);
        }

        $route = $request->route();
        $routeName = is_string($route?->getName()) ? $route->getName() : null;
        $workshopId = $this->resolveWorkshopId($request);
        $searchTerm = $this->resolveSearchTerm($request, $routeName);
        $eventType = $searchTerm !== null ? AnalyticsEvent::TYPE_SEARCH : AnalyticsEvent::TYPE_PAGE_VIEW;

        AnalyticsEvent::create([
            'event_type' => $eventType,
            'session_token' => $sessionToken,
            'visitor_hash' => $this->resolveVisitorHash($request),
            'path' => $request->getPathInfo() ?: '/',
            'route_name' => $routeName,
            'workshop_id' => $workshopId,
            'search_term' => $searchTerm,
            'referrer_host' => $this->resolveReferrerHost($request),
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
