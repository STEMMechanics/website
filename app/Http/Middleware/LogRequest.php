<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Closure;
use Illuminate\Http\Request;
use App\Models\Analytics;

class LogRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request                                                                          $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Make it an after middleware
        $response = $next($request);

        try {
            Analytics::createWithSession([
                'type' => 'apirequest',
                'attribute' => $request->path(),
                'useragent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            return $response;
        } catch (\Error $e) {
            report($e);
            return $response;
        }
    }
}
