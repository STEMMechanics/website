<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardSnapshot
{
    public const PERIODS = ['overview', 'all', 'day', 'week', 'month', 'quarter', 'year'];

    public function get(string $period): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'overview';
        $ttl = max(0, (int) config('analytics.dashboard_snapshot_seconds', 300));
        if ($ttl === 0) {
            return app(AdminDashboardService::class)->build($period);
        }
        $key = 'dashboard-snapshot:v4:'.config('app.timezone').':'.now()->toDateString().':'.$period;
        try {
            $payload = Cache::get($key);
        } catch (\Throwable) {
            return app(AdminDashboardService::class)->build($period);
        }
        if (! is_string($payload)) {
            try {
                return Cache::lock($key.':lock', 60)->block(3, function () use ($key, $period) {
                    $cached = Cache::get($key);
                    return is_string($cached) ? $this->decode($cached) : $this->refresh($period);
                });
            } catch (\Throwable) {
                return app(AdminDashboardService::class)->build($period);
            }
        }

        try {
            return $this->decode($payload);
        } catch (\Throwable) {
            return $this->refresh($period);
        }
    }

    public function refresh(string $period): array
    {
        abort_unless(in_array($period, self::PERIODS, true), 422);
        $data = app(AdminDashboardService::class)->build($period);
        $data['snapshotAt'] = now()->toIso8601String();
        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        try {
            Cache::put('dashboard-snapshot:v4:'.config('app.timezone').':'.now()->toDateString().':'.$period, $payload, max(1, (int) config('analytics.dashboard_snapshot_seconds', 300)));
        } catch (\Throwable) {
            // A cache outage still permits live reporting.
        }

        return $data;
    }

    private function decode(string $payload): array
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $data['periodStart'] = Carbon::parse($data['periodStart'])->timezone(config('app.timezone'));
        $data['periodEnd'] = Carbon::parse($data['periodEnd'])->timezone(config('app.timezone'));
        foreach (['workshopSalesRows', 'storeSalesRows'] as $key) {
            $data[$key] = collect($data[$key]);
        }
        $data['trafficSourceRows'] = collect($data['trafficSourceRows'])->map(fn (array $row) => (object) $row);

        return $data;
    }
}
