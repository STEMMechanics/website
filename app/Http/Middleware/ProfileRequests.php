<?php

namespace App\Http\Middleware;

use App\Support\QueryMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProfileRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('analytics.profile_requests')) {
            $request->attributes->set('profile_started', microtime(true));
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $start = $request->attributes->get('profile_started');
        if (! is_float($start) || ! $request->routeIs('admin.*')) {
            return;
        }
        $metrics = app(QueryMetrics::class);
        Log::info('Request performance', [
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'queries' => $metrics->count,
            'database_ms' => round($metrics->milliseconds, 2),
            'total_ms' => round((microtime(true) - $start) * 1000, 2),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
    }
}
