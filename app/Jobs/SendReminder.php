<?php

namespace App\Jobs;

use App\Mail\ReminderNotification;
use App\Models\Reminder;
use App\Models\Workshop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $reminderId)
    {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $reminder = Reminder::query()->find($this->reminderId);
        if (! $reminder || $reminder->status !== Reminder::STATUS_QUEUED) {
            return;
        }

        $reminder->loadMissing(['remindable', 'source']);
        if ($reminder->remindable instanceof Workshop) {
            $reminder->remindable->loadMissing('location');
        }

        if ($reminder->isCompletedWorkshopTask()) {
            $reminder->update(['status' => Reminder::STATUS_CANCELLED]);

            return;
        }

        Mail::to($reminder->recipient_email)->send(new ReminderNotification($reminder));

        $reminder->update([
            'status' => Reminder::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'failure_message' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Reminder::query()
            ->whereKey($this->reminderId)
            ->where('status', Reminder::STATUS_QUEUED)
            ->update([
                'status' => Reminder::STATUS_FAILED,
                'failed_at' => now(),
                'failure_message' => mb_substr($exception?->getMessage() ?? 'Reminder delivery failed.', 0, 2000),
            ]);
    }
}
