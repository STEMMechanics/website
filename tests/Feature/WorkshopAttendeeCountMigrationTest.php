<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkshopAttendeeCountMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_aggregate_count_is_converted_to_only_the_required_anonymous_rows(): void
    {
        $admin = User::factory()->create();
        Location::factory()->create();
        Media::factory()->create([
            'name' => 'stemmechanics-logo.png',
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create(['user_id' => $admin->id]);
        $migration = require database_path('migrations/2026_08_03_000000_replace_workshop_attendee_counts_with_anonymous_attendance.php');
        $migration->down();

        DB::table('workshops')->where('id', $workshop->id)->update(['attendee_count' => 5]);
        DB::table('workshop_attendances')->insert([
            [
                'workshop_id' => $workshop->id,
                'source' => 'dropin',
                'child_name' => 'Named attendee one',
                'attended_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workshop_id' => $workshop->id,
                'source' => 'dropin',
                'child_name' => 'Named attendee two',
                'attended_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('workshops', 'attendee_count'));
        $this->assertSame(5, DB::table('workshop_attendances')->where('workshop_id', $workshop->id)->count());
        $this->assertSame(3, DB::table('workshop_attendances')
            ->where('workshop_id', $workshop->id)
            ->where('is_anonymous', true)
            ->count());
    }
}
