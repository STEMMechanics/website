<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorkshopTaskPresenter
{
    /**
     * @param  iterable<int, object>  $tasks
     * @return array<int, array{heading: string|null, tasks: array<int, array{task: object, label: string}>}>
     */
    public static function grouped(iterable $tasks): array
    {
        $tasks = Collection::make($tasks)->values();
        $parsed = $tasks->map(fn (object $task): array => self::parse((string) $task->name));
        $categoryCounts = $parsed
            ->filter(fn (array $task): bool => $task['category_key'] !== null)
            ->countBy('category_key');
        $groups = [];
        $groupIndexes = [];

        foreach ($tasks as $index => $task) {
            $taskName = $parsed[$index];
            $isCategory = $taskName['category_key'] !== null
                && ($categoryCounts[$taskName['category_key']] ?? 0) >= 2;
            $groupKey = $isCategory ? 'category:'.$taskName['category_key'] : 'uncategorized';

            if (! array_key_exists($groupKey, $groupIndexes)) {
                $groupIndexes[$groupKey] = count($groups);
                $groups[] = [
                    'heading' => $isCategory ? $taskName['category'] : null,
                    'tasks' => [],
                ];
            }

            $groups[$groupIndexes[$groupKey]]['tasks'][] = [
                'task' => $task,
                'label' => $isCategory ? $taskName['label'] : (string) $task->name,
            ];
        }

        return $groups;
    }

    /**
     * @return array{category: string|null, category_key: string|null, label: string}
     */
    private static function parse(string $name): array
    {
        [$category, $label] = array_pad(explode(':', $name, 2), 2, null);
        $category = trim($category);
        $label = trim((string) $label);

        if ($category === '' || $label === '') {
            return ['category' => null, 'category_key' => null, 'label' => $name];
        }

        return [
            'category' => $category,
            'category_key' => Str::lower($category),
            'label' => $label,
        ];
    }
}
