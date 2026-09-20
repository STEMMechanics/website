<?php

namespace App\Http\Middleware;

use App\Services\StoreCheckoutAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackStoreCheckout
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() >= 400 || ! $request->hasSession() || $request->session()->has('errors')) {
            return $response;
        }
        $route = (string) $request->route()?->getName();
        if (str_starts_with($route, 'shop.cart.') || $route === 'shop.checkout') {
            app(StoreCheckoutAnalytics::class)->record($request, match ($route) {
                'shop.checkout' => 'checkout',
                'shop.cart.preferences' => 'delivery',
                default => 'cart',
            });
        }

        return $response;
    }
}
