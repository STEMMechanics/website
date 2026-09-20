<?php

namespace App\Http\Controllers;

use App\Services\StoreCheckoutAnalytics;
use App\Services\StoreCheckoutReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StoreCheckoutAnalyticsController extends Controller
{
    public function record(Request $request, StoreCheckoutAnalytics $analytics): Response
    {
        $validated = $request->validate(['stage' => ['required', 'in:payment,payment_failed,payment_cancelled']]);
        if ($request->session()->has(StoreCheckoutAnalytics::SESSION_KEY)) {
            $analytics->record($request, $validated['stage']);
        }

        return response()->noContent();
    }

    public function index(Request $request, StoreCheckoutReport $report): View
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'product' => ['nullable', 'integer', 'min:1'],
        ]);
        $from = Carbon::parse($request->input('from') ?: today()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($request->input('to') ?: today()->toDateString())->endOfDay();
        $carts = $report->carts($from, $to);
        if ($request->filled('product')) {
            $carts->whereExists(fn ($query) => $query->selectRaw('1')->from('store_checkout_items as i')
                ->whereColumn('i.checkout_id', 'c.id')->where('i.product_id', $request->integer('product')));
        }
        $carts = $carts->orderByDesc('last_activity_at')->paginate(20, ['c.*'], 'carts_page')->withQueryString();
        $cartItems = DB::table('store_checkout_items')->whereIn('checkout_id', $carts->pluck('id'))->get()->groupBy('checkout_id');

        return view('admin.analytics.checkout', [
            'from' => $from, 'to' => $to, 'summary' => $report->summary($from, $to),
            'items' => $report->items($from, $to)->paginate(20, ['*'], 'items_page')->withQueryString(),
            'stages' => $report->stoppingPoints($from, $to), 'carts' => $carts, 'cartItems' => $cartItems,
        ]);
    }
}
