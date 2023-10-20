<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsItemRequest;
use Symfony\Component\HttpFoundation\Response;
use Closure;
use Illuminate\Http\Request;

class LogRequest
{
    /**
     * Handle an incoming request.
     *
     * @param Illuminate\Http\Request $request HTTP Request.
     * @param  \Closure                $next    Closure.
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Make it an after middleware
        $response = $next($request);

        try {
            AnalyticsItemRequest::create([
                'type' => 'apirequest',
                'path' => $request->path(),
            ]);

            return $response;
        } catch (\Error $e) {
            report($e);
            return $response;
        }
    }
}
