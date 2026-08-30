<?php

namespace App\Services;

use App\Models\PickListTemplate;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopTemplateTask;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReminderService
{
    public const WORKSHOP_TASK_KIND = 'workshop_task';

    public function syncWorkshop(Workshop $workshop): void
    {
        $workshop->loadMissing(['pickListTemplate.tasks', 'facilitator']);

        $activeReminders = $workshop->reminders()
            ->where('kind', self::WORKSHOP_TASK_KIND)
            ->whereIn('status', [Reminder::STATUS_PENDING, Reminder::STATUS_QUEUED])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Reminder $reminder): string => $this->sourceKey($reminder->source_type, $reminder->source_id));

        $facilitator = $workshop->facilitator;
        $template = $workshop->pickListTemplate;
        if ((string) $workshop->status === 'cancelled' || ! $workshop->starts_at || ! $facilitator || ! $template instanceof PickListTemplate || trim((string) $facilitator->email) === '') {
            $activeReminders->flatten()->each->update(['status' => Reminder::STATUS_CANCELLED]);

            return;
        }

        foreach ($template->tasks as $task) {
            if (! $task->reminder_enabled || $task->reminder_offset_days === null || ! in_array($task->reminder_time, ['06:00', '12:00', '16:00'], true)) {
                continue;
            }

            $scheduledAt = Carbon::parse($workshop->starts_at)
                ->startOfDay()
                ->addDays((int) $task->reminder_offset_days)
                ->setTimeFromTimeString((string) $task->reminder_time);

            $key = $this->sourceKey($task->getMorphClass(), $task->getKey());
            $matching = $activeReminders->pull($key, collect());
            $existing = $matching->shift();

            if ($scheduledAt->lt(now()->subMinutes(5))) {
                if ($existing instanceof Reminder) {
                    $existing->update(['status' => Reminder::STATUS_CANCELLED]);
                }
                $matching->each->update(['status' => Reminder::STATUS_CANCELLED]);

                continue;
            }

            $attributes = [
                'recipient_user_id' => $facilitator->id,
                'recipient_email' => trim((string) $facilitator->email),
                'subject' => Str::limit('Workshop task: '.trim((string) $task->name).' — '.trim((string) $workshop->title), 255, ''),
                'message' => $this->workshopTaskMessage($task, $workshop),
                'action_url' => route('admin.workshop.run-sheet', $workshop).'#task-'.$task->id,
                'status' => Reminder::STATUS_PENDING,
                'scheduled_at' => $scheduledAt,
                'queued_at' => null,
                'failed_at' => null,
                'failure_message' => null,
            ];

            if ($existing instanceof Reminder) {
                $existing->update($attributes);
            } else {
                $this->schedule(
                    kind: self::WORKSHOP_TASK_KIND,
                    remindable: $workshop,
                    source: $task,
                    recipient: $facilitator,
                    subject: $attributes['subject'],
                    message: $attributes['message'],
                    actionUrl: $attributes['action_url'],
                    scheduledAt: $scheduledAt,
                );
            }

            $matching->each->update(['status' => Reminder::STATUS_CANCELLED]);
        }

        $activeReminders->flatten()->each->update(['status' => Reminder::STATUS_CANCELLED]);
    }

    public function schedule(
        string $kind,
        Model $remindable,
        ?Model $source,
        User $recipient,
        string $subject,
        ?string $message,
        ?string $actionUrl,
        Carbon $scheduledAt,
    ): Reminder {
        return Reminder::query()->create([
            'kind' => $kind,
            'remindable_type' => $remindable->getMorphClass(),
            'remindable_id' => $remindable->getKey(),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'recipient_user_id' => $recipient->id,
            'recipient_email' => trim((string) $recipient->email),
            'subject' => $subject,
            'message' => $message,
            'action_url' => $actionUrl,
            'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function syncTemplateWorkshops(int $templateId): void
    {
        Workshop::query()
            ->where('pick_list_template_id', $templateId)
            ->with(['pickListTemplate.tasks', 'facilitator'])
            ->chunkById(100, fn ($workshops) => $workshops->each(fn (Workshop $workshop) => $this->syncWorkshop($workshop)));
    }

    private function sourceKey(mixed $type, mixed $id): string
    {
        return (string) $type.':'.(string) $id;
    }

    private function workshopTaskMessage(WorkshopTemplateTask $task, Workshop $workshop): ?string
    {
        return $this->renderWorkshopPlaceholders($task->notes, $workshop);
    }

    public function renderWorkshopPlaceholders(?string $content, Workshop $workshop): ?string
    {
        $message = trim((string) $content);
        if ($message === '') {
            return null;
        }

        $startsAt = $workshop->starts_at;
        $endsAt = $workshop->ends_at;
        $message = strtr($message, [
            '{date-short}' => $startsAt?->format('d/m/Y') ?? 'Not specified',
            '{date-long}' => $startsAt?->format('l j F') ?? 'Not specified',
            '{start-time}' => $startsAt?->format('g:ia') ?? 'Not specified',
            '{end-time}' => $endsAt?->format('g:ia') ?? 'Not specified',
            '{time-range}' => $this->workshopTimeRange($startsAt, $endsAt),
            '{location}' => $workshop->getLocationName() ?: 'Not specified',
            '{ages}' => trim((string) ($workshop->ages ?? '')) ?: 'Not specified',
            '{cost}' => $this->workshopCost($workshop),
        ]);

        if ($startsAt !== null) {
            $message = (string) preg_replace_callback(
                '/\{date-([dmy\s\/.,-]+)\}/',
                fn (array $matches): string => $startsAt->format($this->phpDateFormat((string) $matches[1])),
                $message,
            );
        }

        return $message;
    }

    private function workshopTimeRange(?Carbon $startsAt, ?Carbon $endsAt): string
    {
        if ($startsAt === null || $endsAt === null) {
            return 'Not specified';
        }

        $start = $startsAt->format('g:ia');
        $end = $endsAt->format('g:ia');
        if ($startsAt->format('a') === $endsAt->format('a')) {
            $start = preg_replace('/(am|pm)$/', '', $start) ?? $start;
        }

        return $start.'-'.$end;
    }

    private function workshopCost(Workshop $workshop): string
    {
        $cost = trim((string) ($workshop->price ?? ''));
        if ($cost === '' || preg_match('/^\$?0(?:\.0+)?$/', $cost) === 1 || strcasecmp($cost, 'free') === 0) {
            return 'Free';
        }

        if (preg_match('/^\$?\s*([0-9]+(?:\.[0-9]+)?)$/', $cost, $matches) === 1) {
            return '$'.number_format((float) $matches[1], 2);
        }

        return $cost;
    }

    private function phpDateFormat(string $format): string
    {
        return (string) preg_replace_callback(
            '/yyyy|mmmm|dddd|mmm|ddd|yy|mm|dd|m|d/',
            fn (array $matches): string => match ($matches[0]) {
                'yyyy' => 'Y',
                'yy' => 'y',
                'mmmm' => 'F',
                'mmm' => 'M',
                'mm' => 'm',
                'm' => 'n',
                'dddd' => 'l',
                'ddd' => 'D',
                'dd' => 'd',
                'd' => 'j',
                default => $matches[0],
            },
            $format,
        );
    }
}
