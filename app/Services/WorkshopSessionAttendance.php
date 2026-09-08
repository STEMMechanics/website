<?php

namespace App\Services;

use App\Models\Workshop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkshopSessionAttendance
{
    public function selected(Workshop $workshop, ?string $id): ?array
    {
        $sessions = collect($workshop->effectiveScheduleEntries());

        return $sessions->firstWhere('id', $id)
            ?? $sessions->first(fn (array $session) => Carbon::parse($session['ends_at'])->isFuture())
            ?? $sessions->last();
    }

    /** Scope limits a payment edit to its selected tickets, leaving other participants untouched. */
    public function sync(Workshop $workshop, string $sessionId, array $selected, array $scope): void
    {
        DB::transaction(function () use ($workshop, $sessionId, $selected, $scope): void {
            DB::table('workshop_session_attendance')->where('workshop_id', $workshop->id)->where('session_id', $sessionId)
                ->whereIn('ticket_id', $scope)->whereNotIn('ticket_id', $selected)->delete();
            foreach (array_intersect($selected, $scope) as $ticketId) {
                DB::table('workshop_session_attendance')->insertOrIgnore([
                    'workshop_id' => $workshop->id, 'session_id' => $sessionId, 'ticket_id' => $ticketId, 'attended_at' => now(),
                ]);
            }
        });
    }
}
