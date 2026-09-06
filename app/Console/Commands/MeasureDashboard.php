<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Services\AdminDashboardService;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class MeasureDashboard extends Command
{
    protected $signature = 'performance:dashboard {--period=overview} {--runs=3}';
    protected $description = 'Measure live dashboard queries without logging SQL or record contents';

    public function handle(): int
    {
        $count = 0;
        $milliseconds = 0.0;
        DB::listen(function (QueryExecuted $event) use (&$count, &$milliseconds) {
            $count++;
            $milliseconds += $event->time;
        });
        $this->line('Analytics rows: '.AnalyticsEvent::query()->count());
        for ($run = 1; $run <= min(10, max(1, (int) $this->option('runs'))); $run++) {
            $count = 0;
            $milliseconds = 0;
            $start = microtime(true);
            app(AdminDashboardService::class)->build((string) $this->option('period'));
            $this->line(sprintf('Run %d: %d queries; %.2f database ms; %.2f total ms; %d peak bytes', $run, $count, $milliseconds, (microtime(true) - $start) * 1000, memory_get_peak_usage(true)));
        }

        return self::SUCCESS;
    }
}
