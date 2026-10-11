<?php

namespace App\Services;

use App\Models\PickListTemplate;
use App\Models\Workshop;
use App\Models\WorkshopRunSheetTask;
use App\Models\WorkshopTemplateTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkshopBlueprintService
{
    public function ensureWorkshopTasks(Workshop $workshop): void
    {
        if ($workshop->run_sheet_tasks_initialized) {
            return;
        }

        $this->copyTasksFromBlueprint($workshop, $workshop->pick_list_template_id ? (int) $workshop->pick_list_template_id : null);
    }

    public function copyTasksFromBlueprint(Workshop $workshop, ?int $blueprintId): void
    {
        DB::transaction(function () use ($workshop, $blueprintId): void {
            $blueprint = $blueprintId !== null
                ? PickListTemplate::query()->with('tasks')->find($blueprintId)
                : null;

            $workshop->runSheetTasks()->delete();
            foreach ($blueprint->tasks ?? [] as $task) {
                $workshop->runSheetTasks()->create([
                    'blueprint_task_id' => $task->id,
                    'name' => $task->name,
                    'notes' => $task->notes,
                    'subtasks' => $task->subtasks,
                    'reminder_enabled' => $task->reminder_enabled,
                    'reminder_offset_days' => $task->reminder_offset_days,
                    'reminder_time' => $task->reminder_time,
                    'sort_order' => $task->sort_order,
                ]);
            }

            $workshop->forceFill([
                'run_sheet_tasks_initialized' => true,
                'run_sheet_completed_task_ids' => null,
            ])->save();
        });
    }

    public function workshopTasksMatchBlueprint(Workshop $workshop): bool
    {
        $currentTasks = array_map(
            $this->taskDefinition(...),
            $this->taskDraftsForWorkshop($workshop),
        );
        $blueprintTasks = array_map($this->taskDefinition(...), $this->blueprintTaskDrafts($workshop));

        return $currentTasks === $blueprintTasks;
    }

    public function amendMissingBlueprintTasks(Workshop $workshop): int
    {
        return DB::transaction(function () use ($workshop): int {
            Workshop::query()->whereKey($workshop->id)->lockForUpdate()->firstOrFail();
            $existing = $workshop->runSheetTasks()->get();
            $existingIds = $existing->pluck('blueprint_task_id')->filter()->map(fn ($id): int => (int) $id)->all();
            $existingNames = $existing->pluck('name')->map(fn ($name): string => Str::lower(Str::squish((string) $name)))->all();
            $sortOrder = (int) ($existing->max('sort_order') ?? 0);
            $added = 0;

            foreach ($this->blueprintTaskDrafts($workshop) as $task) {
                $name = Str::lower(Str::squish((string) $task['name']));
                if (in_array((int) $task['blueprint_task_id'], $existingIds, true) || in_array($name, $existingNames, true)) {
                    continue;
                }

                $sortOrder += 10;
                $workshop->runSheetTasks()->create([...$task, 'sort_order' => $sortOrder]);
                $existingIds[] = (int) $task['blueprint_task_id'];
                $existingNames[] = $name;
                $added++;
            }

            $workshop->unsetRelation('runSheetTasks');

            return $added;
        });
    }

    public function revertWorkshopTasksToBlueprint(Workshop $workshop): void
    {
        DB::transaction(function () use ($workshop): void {
            Workshop::query()->whereKey($workshop->id)->lockForUpdate()->firstOrFail();
            $existing = $workshop->runSheetTasks()->get()->keyBy('blueprint_task_id');
            $tasks = array_map(function (array $task) use ($existing): array {
                // Keep task IDs so completed tasks and their reminder history are retained.
                $task['id'] = $existing->get($task['blueprint_task_id'])?->id;

                return $task;
            }, $this->blueprintTaskDrafts($workshop));

            $this->saveWorkshopTasks($workshop, $tasks);
            $workshop->unsetRelation('runSheetTasks');
        });
    }

    /** @return list<array<string, mixed>> */
    private function blueprintTaskDrafts(Workshop $workshop): array
    {
        $workshop->loadMissing('pickListTemplate.tasks');

        return $workshop->pickListTemplate?->tasks->map(fn (WorkshopTemplateTask $task): array => [
            'blueprint_task_id' => (int) $task->id,
            ...$task->only(['name', 'notes', 'subtasks', 'reminder_enabled', 'reminder_offset_days', 'reminder_time']),
        ])->values()->all() ?? [];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function taskDefinition(array $task): array
    {
        $reminderEnabled = (bool) ($task['reminder_enabled'] ?? false);

        return [
            'blueprint_task_id' => isset($task['blueprint_task_id']) ? (int) $task['blueprint_task_id'] : null,
            'name' => trim((string) ($task['name'] ?? '')),
            'notes' => trim((string) ($task['notes'] ?? '')),
            'subtasks' => collect($task['subtasks'] ?? [])->map(fn (array $subtask): array => [
                'title' => trim((string) ($subtask['title'] ?? '')),
                'content' => trim((string) ($subtask['content'] ?? '')),
            ])->values()->all(),
            'reminder_enabled' => $reminderEnabled,
            'reminder_offset_days' => $reminderEnabled && isset($task['reminder_offset_days']) ? (int) $task['reminder_offset_days'] : null,
            'reminder_time' => $reminderEnabled ? ($task['reminder_time'] ?? null) : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function taskDraftsForWorkshop(Workshop $workshop): array
    {
        $workshop->loadMissing('runSheetTasks.blueprintTask');

        return $workshop->runSheetTasks->map(function (WorkshopRunSheetTask $task): array {
            $blueprintTask = $task->blueprintTask;
            $notes = (string) ($task->notes ?? '');
            if ($blueprintTask instanceof WorkshopTemplateTask) {
                $notes = $this->restorePlaceholderTemplate($notes, (string) ($blueprintTask->notes ?? ''));
            }

            $blueprintSubtasks = collect($blueprintTask->subtasks ?? [])->keyBy(fn (array $subtask): string => (string) ($subtask['title'] ?? ''));
            $subtasks = collect($task->subtasks ?? [])->map(function (array $subtask) use ($blueprintSubtasks): array {
                $template = $blueprintSubtasks->get((string) ($subtask['title'] ?? ''));
                if (is_array($template)) {
                    $subtask['content'] = $this->restorePlaceholderTemplate(
                        (string) ($subtask['content'] ?? ''),
                        (string) ($template['content'] ?? ''),
                    );
                }

                return [
                    'title' => (string) ($subtask['title'] ?? ''),
                    'content' => (string) ($subtask['content'] ?? ''),
                ];
            })->values()->all();

            return [
                'id' => (int) $task->id,
                'blueprint_task_id' => $task->blueprint_task_id ? (int) $task->blueprint_task_id : null,
                'name' => (string) $task->name,
                'notes' => $notes,
                'subtasks' => $subtasks,
                'reminder_enabled' => (bool) $task->reminder_enabled,
                'reminder_offset_days' => $task->reminder_offset_days,
                'reminder_time' => (string) ($task->reminder_time ?? ''),
                'sort_order' => (int) ($task->sort_order ?? 0),
            ];
        })->values()->all();
    }

    private function restorePlaceholderTemplate(string $current, string $template): string
    {
        if ($current === '' || $template === '' || ! preg_match('/\{(?:date-[^{}]+|start-time|end-time|time-range|location|ages|cost|workshop-url)\}/', $template)) {
            return $current;
        }

        $parts = preg_split('/(\{(?:date-[^{}]+|start-time|end-time|time-range|location|ages|cost|workshop-url)\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($parts)) {
            return $current;
        }

        $pattern = '';
        foreach ($parts as $part) {
            $pattern .= preg_match('/^\{(?:date-[^{}]+|start-time|end-time|time-range|location|ages|cost|workshop-url)\}$/', $part)
                ? '[\s\S]*?'
                : preg_quote($part, '~');
        }

        return preg_match('~\A'.$pattern.'\z~u', $current) === 1 ? $template : $current;
    }

    /** @param list<array<string, mixed>> $tasks */
    public function saveWorkshopTasks(Workshop $workshop, array $tasks): void
    {
        DB::transaction(function () use ($workshop, $tasks): void {
            $existing = $workshop->runSheetTasks()->get()->keyBy(fn (WorkshopRunSheetTask $task): int => (int) $task->id);
            $keptIds = [];

            foreach (array_values($tasks) as $index => $row) {
                $taskId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                $task = $taskId !== null ? $existing->get($taskId) : null;
                $blueprintTaskId = isset($row['blueprint_task_id']) && is_numeric($row['blueprint_task_id'])
                    ? (int) $row['blueprint_task_id']
                    : null;
                if ($blueprintTaskId !== null && ! $workshop->pick_list_template_id) {
                    $blueprintTaskId = null;
                }
                if ($blueprintTaskId !== null && ! PickListTemplate::query()->find((int) $workshop->pick_list_template_id)?->tasks()->whereKey($blueprintTaskId)->exists()) {
                    $blueprintTaskId = null;
                }

                $attributes = [
                    'blueprint_task_id' => $blueprintTaskId,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                    'subtasks' => collect(Arr::wrap($row['subtasks'] ?? []))
                        ->filter(fn ($subtask): bool => is_array($subtask))
                        ->map(fn (array $subtask): array => [
                            'title' => trim((string) ($subtask['title'] ?? '')),
                            'content' => trim((string) ($subtask['content'] ?? '')),
                        ])
                        ->filter(fn (array $subtask): bool => $subtask['title'] !== '')
                        ->values()
                        ->all(),
                    'reminder_enabled' => filter_var($row['reminder_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'reminder_offset_days' => isset($row['reminder_offset_days']) && $row['reminder_offset_days'] !== '' ? (int) $row['reminder_offset_days'] : null,
                    'reminder_time' => in_array(($row['reminder_time'] ?? null), ['06:00', '12:00', '16:00'], true) ? $row['reminder_time'] : null,
                    'sort_order' => ($index + 1) * 10,
                ];

                if ($attributes['name'] === '') {
                    continue;
                }

                if ($task instanceof WorkshopRunSheetTask) {
                    $task->fill($attributes)->save();
                } else {
                    $task = $workshop->runSheetTasks()->create($attributes);
                }

                $keptIds[] = (int) $task->id;
            }

            $workshop->runSheetTasks()->when(
                $keptIds !== [],
                fn ($query) => $query->whereNotIn('id', $keptIds),
                fn ($query) => $query,
            )->delete();

            $completedIds = collect($workshop->run_sheet_completed_task_ids ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => in_array($id, $keptIds, true))
                ->values()
                ->all();

            $workshop->forceFill([
                'run_sheet_tasks_initialized' => true,
                'run_sheet_completed_task_ids' => $completedIds,
            ])->save();
        });
    }
}
