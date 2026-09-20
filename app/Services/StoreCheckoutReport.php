<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StoreCheckoutReport
{
    public function carts(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return DB::table('store_checkout_sessions as c')->whereBetween('c.created_at', [$from, $to]);
    }

    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = $this->carts($from, $to);
        $inactive = (clone $base)->whereNull('outcome')->where('last_activity_at', '<=', now()->subDay());

        return [
            'Carts started' => (clone $base)->count(),
            'Checkout started' => (clone $base)->whereNotNull('checkout_started_at')->count(),
            'Orders completed' => (clone $base)->where('outcome', 'completed')->count(),
            'Active carts' => (clone $base)->whereNull('outcome')->where('last_activity_at', '>', now()->subDay())->count(),
            'Inactive carts' => (clone $inactive)->count(),
            'Shipping quote requests' => (clone $base)->where('outcome', 'quote_requested')->count(),
            'Emptied carts' => (clone $base)->where('outcome', 'cleared')->count(),
            'Carts with payment failures' => (clone $base)->where('payment_failed', true)->count(),
            'Carts with payment cancellations' => (clone $base)->where('payment_cancelled', true)->count(),
            'Value in inactive carts' => '$'.number_format((float) (clone $inactive)->sum('subtotal'), 2),
        ];
    }

    public function values(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = $this->carts($from, $to);

        return [
            'completed' => $this->valueStats((clone $base)->where('outcome', 'completed')),
            'inactive' => $this->valueStats((clone $base)->whereNull('outcome')->where('last_activity_at', '<=', now()->subDay())),
        ];
    }

    private function valueStats(Builder $query): array
    {
        // Use item subtotals consistently, including carts without a delivery quote.
        $stats = (clone $query)->selectRaw('COUNT(*) as count, MIN(subtotal) as lowest, MAX(subtotal) as highest')->first();
        $count = (int) $stats->count;
        if ($count === 0) {
            return ['lowest' => null, 'highest' => null, 'median' => null];
        }
        $middle = (clone $query)->orderBy('subtotal')->orderBy('c.id')
            ->offset(intdiv($count - 1, 2))->limit($count % 2 === 0 ? 2 : 1)->pluck('subtotal');

        return [
            'lowest' => (float) $stats->lowest,
            'highest' => (float) $stats->highest,
            'median' => round((float) $middle->avg(), 2),
        ];
    }

    public function items(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->carts($from, $to)
            ->join('store_checkout_items as i', 'i.checkout_id', '=', 'c.id')
            ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
            ->select('i.product_id')->selectRaw('COALESCE(MAX(p.title), MAX(i.title)) as title, COUNT(DISTINCT c.id) as carts')
            ->selectRaw("COUNT(DISTINCT CASE WHEN c.outcome = 'completed' THEN c.id END) as completed")
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.outcome IS NULL AND c.last_activity_at <= ? THEN c.id END) as inactive', [now()->subDay()])
            ->selectRaw('SUM(CASE WHEN c.outcome IS NULL AND c.last_activity_at <= ? THEN i.quantity ELSE 0 END) as inactive_quantity', [now()->subDay()])
            ->selectRaw('SUM(CASE WHEN c.outcome IS NULL AND c.last_activity_at <= ? THEN i.quantity * i.unit_price ELSE 0 END) as inactive_value', [now()->subDay()])
            ->groupBy('i.product_id')->orderByDesc('inactive')->orderByDesc('carts')->orderBy('i.product_id');
    }

    public function stoppingPoints(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->carts($from, $to)->join('store_checkout_items as i', 'i.checkout_id', '=', 'c.id')
            ->whereNull('c.outcome')->where('c.last_activity_at', '<=', now()->subDay())
            ->select('i.product_id', 'c.stage')->selectRaw('COUNT(DISTINCT c.id) as carts')
            ->groupBy('i.product_id', 'c.stage')->orderByDesc('carts')->orderBy('c.stage')->get();
        $stages = [];
        foreach ($rows as $row) {
            $stages[$row->product_id] ??= ucfirst($row->stage);
        }

        return $stages;
    }
}
