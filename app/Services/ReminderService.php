<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\PickListTemplate;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopTemplateTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        if (! $workshop->starts_at || ! $facilitator || ! $template instanceof PickListTemplate || trim((string) $facilitator->email) === '') {
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
                'subject' => 'Workshop task: '.trim((string) $task->name),
                'message' => trim((string) ($task->notes ?? '')) ?: null,
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
}
