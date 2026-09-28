<?php

namespace App\Jobs;

use App\Mail\WorkshopInterestReminder;
use App\Models\WorkshopInterest;
use App\Services\WorkshopInterestReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWorkshopInterestReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** @param 'two_days'|'two_hours' $type */
    public function __construct(public int $interestId, public string $type)
    {
        $this->onQueue('mail');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('workshop-interest-reminder-'.$this->interestId.'-'.$this->type))
            ->releaseAfter(30)
            ->expireAfter(300)];
    }

    public function handle(): void
    {
        $settings = WorkshopInterestReminderService::reminderSettings($this->type);
        $interest = WorkshopInterest::query()->with('workshop')->find($this->interestId);

        if (
            ! $interest
            || ! $interest->{$settings['queued']}
            || $interest->{$settings['sent']}
            || $interest->reminders_unsubscribed_at
        ) {
            return;
        }

        $workshop = $interest->workshop;
        $startsAt = $workshop?->starts_at;
        if (! $workshop || ! $startsAt || in_array($workshop->status, ['cancelled', 'draft'], true) || $startsAt->lte(now())) {
            $interest->update([$settings['queued'] => null]);

            return;
        }

        $scheduledAt = $settings['unit'] === 'days'
            ? $startsAt->copy()->subDays($settings['offset'])
            : $startsAt->copy()->subHours($settings['offset']);

        if (
            $scheduledAt->isFuture()
            || $scheduledAt->lt(now()->subMinutes($settings['grace_minutes']))
            || ($this->type === 'two_days' && $startsAt->lte(now()->addHours(2)))
        ) {
            $interest->update([$settings['queued'] => null]);

            return;
        }

        Mail::to(trim((string) $interest->email))->send(new WorkshopInterestReminder($workshop, $interest, $this->type));

        $interest->update([$settings['sent'] => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
