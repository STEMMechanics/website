<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreCheckoutAnalytics
{
    public const SESSION_KEY = 'store.checkout_analytics_id';

    public function record(Request $request, string $stage = 'cart', ?string $outcome = null, ?int $orderId = null, ?array $summary = null): void
    {
        if (! config('analytics.enabled', true) || ! $request->hasSession() || $request->user()?->isAdmin()) {
            return;
        }
        foreach ((array) config('analytics.ignore_bot_user_agents', []) as $bot) {
            if ($bot !== '' && str_contains(strtolower((string) $request->userAgent()), strtolower($bot))) {
                return;
            }
        }
        try {
            $cart = app(StoreCartService::class);
            $lines = $cart->lines();
            $id = $request->session()->get(self::SESSION_KEY);
            if ($lines->isEmpty()) {
                if ($id) {
                    DB::table('store_checkout_sessions')->where('id', $id)->whereNull('outcome')->update(['outcome' => 'cleared', 'last_activity_at' => now(), 'updated_at' => now()]);
                    $request->session()->forget(self::SESSION_KEY);
                }

                return;
            }
            if ($id && DB::table('store_checkout_sessions')->where('id', $id)->whereNotNull('outcome')->exists()) {
                $id = null;
            }
            $id = $id ?: (string) Str::uuid();
            $summary ??= $cart->summary(['user' => $request->user(), 'shipping_country' => 'Australia']);
            DB::transaction(function () use ($id, $lines, $summary, $stage, $outcome, $orderId): void {
                $existing = DB::table('store_checkout_sessions')->where('id', $id)->first();
                $data = [
                    'last_activity_at' => now(), 'updated_at' => now(),
                    'subtotal' => $summary['subtotal'], 'shipping' => $summary['shipping'] ?? null,
                    'total' => $summary['total'] ?? null,
                    'manual_quote' => (bool) ($summary['shipping_quote']['requires_manual_quote'] ?? false),
                ];
                // Cart refreshes must not hide the furthest checkout step reached.
                $stages = ['cart' => 0, 'checkout' => 1, 'delivery' => 2, 'payment' => 3];
                if (($stages[$stage] ?? 3) >= ($stages[$existing->stage ?? 'cart'] ?? 0)) {
                    $data['stage'] = in_array($stage, ['payment_failed', 'payment_cancelled']) ? 'payment' : $stage;
                }
                if (in_array($stage, ['checkout', 'payment', 'payment_failed', 'payment_cancelled']) && ! ($existing->checkout_started_at ?? null)) {
                    $data['checkout_started_at'] = now();
                }
                if (in_array($stage, ['payment_failed', 'payment_cancelled'])) {
                    $data[$stage] = true;
                }
                if ($outcome !== null) {
                    $data['outcome'] = $outcome;
                    $data['order_id'] = $orderId;
                }
                if ($existing) {
                    DB::table('store_checkout_sessions')->where('id', $id)->update($data);
                } else {
                    DB::table('store_checkout_sessions')->insert(['id' => $id, 'created_at' => now()] + $data);
                }
                DB::table('store_checkout_items')->where('checkout_id', $id)->delete();
                DB::table('store_checkout_items')->insert($lines->map(fn ($line) => [
                    'checkout_id' => $id, 'product_id' => $line->product->id,
                    'line_key' => $line->key, 'title' => mb_substr($line->display_title, 0, 255),
                    'quantity' => $line->quantity, 'unit_price' => $line->unit_price,
                ])->all());
            });
            if ($outcome !== null) {
                $request->session()->forget(self::SESSION_KEY);
            } else {
                $request->session()->put(self::SESSION_KEY, $id);
            }
        } catch (\Throwable $e) {
            // Analytics must never prevent adding items or paying for an order.
            report($e);
        }
    }
}
