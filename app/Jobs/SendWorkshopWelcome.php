<?php

namespace App\Jobs;

use App\Mail\WorkshopWelcome;
use App\Models\Workshop;
use App\Services\WorkshopWelcomeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWorkshopWelcome implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('mail');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('workshop-welcome-'.$this->deliveryId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(WorkshopWelcomeService $service): void
    {
        $delivery = DB::table('workshop_welcome_deliveries')->where('id', $this->deliveryId)->first();
        if (! $delivery || $delivery->status !== 'queued') {
            return;
        }
        $workshop = Workshop::find($delivery->workshop_id);
        if (! $workshop || ! $service->eligible($workshop)
            || ! $workshop->welcome_send_at || $workshop->welcome_send_at->isFuture()
            || $workshop->welcome_generation !== (int) $delivery->generation
            || ! $service->isRecipient($workshop, $delivery->email)) {
            DB::table('workshop_welcome_deliveries')->where('id', $this->deliveryId)->update(['status' => 'cancelled', 'updated_at' => now()]);

            return;
        }
        Mail::to($delivery->email)->send(new WorkshopWelcome($workshop));
        DB::table('workshop_welcome_deliveries')->where('id', $this->deliveryId)->update([
            'status' => 'sent', 'sent_at' => now(), 'error' => null, 'updated_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('workshop_welcome_deliveries')->where('id', $this->deliveryId)->where('status', 'queued')->update([
            'status' => 'failed', 'error' => mb_substr($exception?->getMessage() ?? 'Delivery failed.', 0, 2000), 'updated_at' => now(),
        ]);
    }
}
