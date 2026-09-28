<?php

namespace App\Services;

use App\Jobs\SendWorkshopInterestReminder;
use App\Models\WorkshopInterest;
use Illuminate\Support\Facades\DB;
use Throwable;

class WorkshopInterestReminderService
{
    /** @var array<string, array{queued: string, sent: string, offset: int, unit: string, grace_minutes: int}> */
    private const REMINDER_TYPES = [
        'two_days' => [
            'queued' => 'two_day_reminder_queued_at',
            'sent' => 'two_day_reminder_sent_at',
            'offset' => 2,
            'unit' => 'days',
            'grace_minutes' => 60,
        ],
        'two_hours' => [
            'queued' => 'two_hour_reminder_queued_at',
            'sent' => 'two_hour_reminder_sent_at',
            'offset' => 2,
            'unit' => 'hours',
            'grace_minutes' => 30,
        ],
    ];

    public function queueDue(): int
    {
        $now = now();
        $queued = 0;

        $interests = WorkshopInterest::query()
            ->whereNull('reminders_unsubscribed_at')
            ->whereHas('workshop', fn ($query) => $query
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', $now)
                ->where('starts_at', '<=', $now->copy()->addDays(2))
                ->whereNotIn('status', ['cancelled', 'draft']))
            ->with('workshop')
            ->lazyById(100);

        foreach ($interests as $interest) {
            $startsAt = $interest->workshop?->starts_at;
            if (! $startsAt) {
                continue;
            }

            foreach (self::REMINDER_TYPES as $type => $settings) {
                $scheduledAt = $settings['unit'] === 'days'
                    ? $startsAt->copy()->subDays($settings['offset'])
                    : $startsAt->copy()->subHours($settings['offset']);

                if ($scheduledAt->isFuture()) {
                    continue;
                }

                if ($scheduledAt->lt($now->copy()->subMinutes($settings['grace_minutes']))) {
                    continue;
                }

                // If the scheduler missed the two-day send window, the two-hour
                // reminder is more useful than sending both messages together.
                if ($type === 'two_days' && $startsAt->lte($now->copy()->addHours(2))) {
                    continue;
                }

                $queued += $this->queueOne($interest, $type, $settings['queued'], $settings['sent']) ? 1 : 0;
            }
        }

        return $queued;
    }

    /** @param 'two_days'|'two_hours' $type */
    private function queueOne(WorkshopInterest $interest, string $type, string $queuedColumn, string $sentColumn): bool
    {
        $claimed = DB::transaction(function () use ($interest, $queuedColumn, $sentColumn): bool {
            $now = now();

            return DB::table('workshop_interests')
                ->where('id', $interest->id)
                ->whereNull($queuedColumn)
                ->whereNull($sentColumn)
                ->update([$queuedColumn => $now, 'updated_at' => $now]) === 1;
        });

        if (! $claimed) {
            return false;
        }

        try {
            SendWorkshopInterestReminder::dispatch((int) $interest->id, $type);
        } catch (Throwable $exception) {
            DB::table('workshop_interests')
                ->where('id', $interest->id)
                ->whereNull($sentColumn)
                ->update([$queuedColumn => null, 'updated_at' => now()]);
            report($exception);

            return false;
        }

        return true;
    }

    /** @return array{queued: string, sent: string, offset: int, unit: string, grace_minutes: int} */
    public static function reminderSettings(string $type): array
    {
        return self::REMINDER_TYPES[$type] ?? throw new \InvalidArgumentException('Unknown workshop interest reminder type.');
    }
}
