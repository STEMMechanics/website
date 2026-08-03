<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_attendances', function (Blueprint $table): void {
            $table->boolean('is_anonymous')->default(false)->after('source');
        });

        DB::table('workshops')
            ->whereNotNull('attendee_count')
            ->orderBy('id')
            ->each(function (object $workshop): void {
                $existingCount = DB::table('workshop_attendances')
                    ->where('workshop_id', $workshop->id)
                    ->whereNull('ticket_id')
                    ->count();
                $anonymousCount = max(0, (int) $workshop->attendee_count - $existingCount);

                if ($anonymousCount === 0) {
                    return;
                }

                $timestamp = now();
                $rows = array_fill(0, $anonymousCount, [
                    'workshop_id' => $workshop->id,
                    'ticket_id' => null,
                    'source' => 'anonymous',
                    'is_anonymous' => true,
                    'attended_at' => $workshop->ends_at ?? $workshop->starts_at ?? $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('workshop_attendances')->insert($chunk);
                }
            });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('attendee_count');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->unsignedInteger('attendee_count')->nullable()->after('max_tickets');
        });

        DB::table('workshops')->orderBy('id')->each(function (object $workshop): void {
            $attendanceCount = DB::table('workshop_attendances')
                ->where('workshop_id', $workshop->id)
                ->whereNull('ticket_id')
                ->count();

            if ($attendanceCount > 0) {
                DB::table('workshops')->where('id', $workshop->id)->update([
                    'attendee_count' => $attendanceCount,
                ]);
            }
        });

        DB::table('workshop_attendances')->where('is_anonymous', true)->delete();

        Schema::table('workshop_attendances', function (Blueprint $table): void {
            $table->dropColumn('is_anonymous');
        });
    }
};
