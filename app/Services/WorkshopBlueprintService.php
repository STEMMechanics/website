<?php

namespace App\Services;

use App\Models\PickListTemplate;
use App\Models\Workshop;
use App\Models\WorkshopRunSheetTask;
use App\Models\WorkshopTemplateTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
