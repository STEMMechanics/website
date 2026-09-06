<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordAnalyticsEvent implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 15;
    public array $backoff = [10, 60];

    public function __construct(public array $event)
    {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        // A retry after a worker crash must not count the same page view twice.
        AnalyticsEvent::query()->firstOrCreate(['event_uuid' => $this->event['event_uuid']], $this->event);
    }
}
