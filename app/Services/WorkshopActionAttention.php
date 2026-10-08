<?php

namespace App\Services;

use App\Models\Workshop;
use App\Services\Finance\WorkshopAllocation;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;

/** Collect the workshop-specific items that need an admin action. */
class WorkshopActionAttention
{
    /** @return array<string, array<string, bool>> */
    public function summary(): array
    {
        return app(RequestMemo::class)->remember('workshop-action-attention', function (): array {
            $attention = [];
            $add = function (mixed $workshopId, string $kind) use (&$attention): void {
                $workshopId = trim((string) $workshopId);
                if ($workshopId !== '') {
                    $attention[$workshopId][$kind] = true;
                }
            };

            foreach (app(WorkshopAllocation::class)->attention() as $row) {
                $add($row['workshop']->getKey() ?? null, 'allocation_review');
            }

            foreach (app(WorkshopFollowUp::class)->pendingTasks() as $task) {
                $workshopId = $task['workshop']->getKey();
                if ($task['attendance']) {
                    $add($workshopId, 'attendance');
                }
                if ($task['stock']) {
                    $add($workshopId, 'stock_reconciliation');
                }
            }

            foreach (app(StockInventoryService::class)->workshopReservationShortagesByWorkshop() as $workshopId => $shortages) {
                if ($shortages !== []) {
                    $add($workshopId, 'stock_shortage');
                }
            }

            return $attention;
        });
    }

    /** @return array{workshops: int, allocation_review: int, stock_shortage: int, attendance: int, stock_reconciliation: int} */
    public function counts(): array
    {
        $counts = [
            'workshops' => 0,
            'allocation_review' => 0,
            'stock_shortage' => 0,
            'attendance' => 0,
            'stock_reconciliation' => 0,
        ];

        foreach ($this->summary() as $issues) {
            $counts['workshops']++;
            foreach (array_keys($counts) as $kind) {
                if ($kind !== 'workshops' && ($issues[$kind] ?? false)) {
                    $counts[$kind]++;
                }
            }
        }

        return $counts;
    }

    public function count(): int
    {
        return $this->counts()['workshops'];
    }

    /** @return list<string> */
    public function workshopIdsFor(string $kind): array
    {
        $summary = $this->summary();
        if ($kind === 'needs_attention') {
            return array_map('strval', array_keys($summary));
        }

        return collect($summary)
            ->filter(fn (array $issues): bool => (bool) ($issues[$kind] ?? false))
            ->keys()
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /** @param Collection<int, Workshop> $workshops
     *  @return array<string, array<string, bool>>
     */
    public function forWorkshops(Collection $workshops): array
    {
        $summary = $this->summary();

        return $workshops
            ->filter(fn ($workshop): bool => $workshop instanceof Workshop)
            ->mapWithKeys(fn (Workshop $workshop): array => [
                (string) $workshop->getKey() => $summary[(string) $workshop->getKey()] ?? [],
            ])
            ->all();
    }
}
