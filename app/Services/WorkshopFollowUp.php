<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkshopFollowUp
{
    public function __construct(private readonly WorkshopPickListService $pickLists) {}

    public function hasEnded(Workshop $workshop, ?CarbonInterface $at = null): bool
    {
        if (in_array((string) $workshop->status, ['draft', 'cancelled'], true)) {
            return false;
        }

        $end = $workshop->effectiveEndsAt() ?? $workshop->starts_at;

        return $end !== null && $end->lessThanOrEqualTo($at ?? now());
    }

    public function hasAttendance(Workshop $workshop): bool
    {
        return $workshop->attendance_no_attendees_confirmed_at !== null
            || $workshop->attendances()->exists()
            || Ticket::query()
                ->where('workshop_id', $workshop->getKey())
                ->whereNotNull('attended_at')
                ->exists()
            || DB::table('workshop_session_attendance')
                ->where('workshop_id', $workshop->getKey())
                ->exists();
    }

    public function needsAttendance(Workshop $workshop, ?CarbonInterface $at = null): bool
    {
        return $this->hasEnded($workshop, $at) && ! $this->hasAttendance($workshop);
    }

    public function needsStock(Workshop $workshop, ?CarbonInterface $at = null): bool
    {
        return $workshop->stock_reconciled_at === null
            && $this->hasEnded($workshop, $at)
            && $this->hasStockPlan($workshop);
    }

    public function hasStockPlan(Workshop $workshop): bool
    {
        return $this->pickLists->plannedStockForReconciliation($workshop)->isNotEmpty();
    }

    /** @return Collection<int, array{workshop: Workshop, ended_at: CarbonInterface, attendance: bool, stock: bool}> */
    public function pendingTasks(?CarbonInterface $at = null): Collection
    {
        $now = $at ?? now();
        $workshops = Workshop::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(fn (Builder $query): Builder => $query
                ->where('starts_at', '<=', $now)
                ->orWhere(fn (Builder $course): Builder => $course->where('format', 'course')->whereNotNull('course_sessions')))
            ->with('location')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Workshop $workshop): bool => $this->hasEnded($workshop, $now))
            ->values();

        if ($workshops->isEmpty()) {
            return collect();
        }

        $ids = $workshops->modelKeys();
        $attendedWorkshopIds = WorkshopAttendance::query()
            ->whereIn('workshop_id', $ids)
            ->distinct()
            ->pluck('workshop_id')
            ->merge(Ticket::query()
                ->whereIn('workshop_id', $ids)
                ->whereNotNull('attended_at')
                ->distinct()
                ->pluck('workshop_id'))
            ->merge(DB::table('workshop_session_attendance')
                ->whereIn('workshop_id', $ids)
                ->distinct()
                ->pluck('workshop_id'))
            ->merge(Workshop::query()
                ->whereIn('id', $ids)
                ->whereNotNull('attendance_no_attendees_confirmed_at')
                ->pluck('id'))
            ->mapWithKeys(fn ($id): array => [(string) $id => true]);

        return $workshops->flatMap(function (Workshop $workshop) use ($attendedWorkshopIds, $now): array {
            $endedAt = $workshop->effectiveEndsAt() ?? $workshop->starts_at;
            if ($endedAt === null) {
                return [];
            }
            $attendance = ! $attendedWorkshopIds->has((string) $workshop->getKey());
            $stock = $this->needsStock($workshop, $now);

            if (! $attendance && ! $stock) {
                return [];
            }

            return [[
                'workshop' => $workshop,
                'ended_at' => $endedAt,
                'attendance' => $attendance,
                'stock' => $stock,
            ]];
        })->values();
    }

    public function pendingTaskCount(?CarbonInterface $at = null): int
    {
        return $this->pendingTasks($at)->sum(fn (array $task): int => (int) $task['attendance'] + (int) $task['stock']);
    }
}
