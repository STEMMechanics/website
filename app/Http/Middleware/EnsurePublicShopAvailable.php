<?php

namespace App\Http\Middleware;

use App\Support\ShopAvailability;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicShopAvailable
{
    public function __construct(
        private readonly ShopAvailability $shopAvailability,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shopAvailability->isPubliclyAvailable()) {
            return $next($request);
        }

        $reason = $this->shopAvailability->isPublicEnabled()
            ? 'There are no products currently available.'
            : 'The store has been temporarily disabled.';

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'The store is temporarily unavailable.',
                'reason' => $reason,
            ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return response()->view('shop.unavailable', [
                'reason' => $reason,
            ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return redirect()->route('shop.index');
    }
}
