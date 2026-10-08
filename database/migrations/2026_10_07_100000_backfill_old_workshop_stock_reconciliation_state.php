<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cutoff = now()->subDays(14);

        DB::table('workshops')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(fn ($query) => $query
                ->where('starts_at', '<', $cutoff)
                ->orWhere(fn ($course) => $course->where('format', 'course')->whereNotNull('course_sessions')))
            ->orderBy('id')
            ->chunkById(250, function ($workshops) use ($cutoff): void {
                $activeReservationIds = DB::table('stock_reservations')
                    ->where('source_type', 'App\\Models\\Workshop')
                    ->whereIn('source_id', $workshops->pluck('id')->map(fn ($id): string => (string) $id))
                    ->whereIn('status', ['active', 'partially_consumed'])
                    ->where('remaining_quantity', '>', 0)
                    ->distinct()
                    ->pluck('source_id')
                    ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                foreach ($workshops as $workshop) {
                    $sessions = json_decode((string) ($workshop->course_sessions ?? ''), true);
                    $session = is_array($sessions) && $sessions !== [] ? $sessions[array_key_last($sessions)] : null;
                    $endValue = is_array($session) && filled($session['ends_at'] ?? null)
                        ? $session['ends_at']
                        : ($workshop->ends_at ?? $workshop->starts_at);

                    if (! filled($endValue) || Carbon::parse($endValue)->gte($cutoff)) {
                        continue;
                    }

                    if (! $activeReservationIds->has((string) $workshop->id) && $workshop->stock_reconciled_at === null) {
                        DB::table('workshops')->where('id', $workshop->id)->update(['stock_reconciled_at' => Carbon::parse($endValue)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Historical reconciliation timestamps cannot be safely reconstructed.
    }
};
