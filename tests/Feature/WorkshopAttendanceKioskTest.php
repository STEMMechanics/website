<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopAttendanceKioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_non_ticketed_workshop_can_use_kiosk_sign_in_and_redirects_back_to_blank_form(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]))
            ->assertOk()
            ->assertSee('Sign-In Sheet')
            ->assertSee('Parent/Guardian Name');

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.attendance.dropin.store', $workshop), [
                'kiosk' => 1,
                'child_name' => 'Ada Lovelace',
                'guardian_name' => 'Mary Lovelace',
                'email' => 'mary@example.com',
                'phone' => '0400000000',
                'media_consent' => 1,
            ]);

        $response->assertRedirect(route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]));

        $this->assertDatabaseHas('workshop_attendances', [
            'workshop_id' => $workshop->id,
            'child_name' => 'Ada Lovelace',
            'guardian_name' => 'Mary Lovelace',
            'email' => 'mary@example.com',
            'phone' => '0400000000',
            'media_consent' => 1,
            'source' => 'dropin',
        ]);
    }

    public function test_admin_can_bulk_update_attendance_entries_from_table_editor(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        $entry = \App\Models\WorkshopAttendance::query()->create([
            'workshop_id' => $workshop->id,
            'source' => 'dropin',
            'child_name' => 'Old Name',
            'guardian_name' => 'Old Guardian',
            'email' => 'old@example.com',
            'phone' => '0411111111',
            'media_consent' => false,
            'attended_at' => now(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', ['workshop' => $workshop]))
            ->assertOk()
            ->assertSee('Save Attendance Records');

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.attendance.dropin.sync', ['workshop' => $workshop]), [
                'entries' => [
                    [
                        'id' => $entry->id,
                        'child_name' => 'New Name',
                        'guardian_name' => 'New Guardian',
                        'email' => 'new@example.com',
                        'phone' => '0422222222',
                        'media_consent' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));

        $this->assertDatabaseHas('workshop_attendances', [
            'id' => $entry->id,
            'child_name' => 'New Name',
            'guardian_name' => 'New Guardian',
            'email' => 'new@example.com',
            'phone' => '0422222222',
            'media_consent' => 1,
        ]);
    }

    public function test_admin_can_export_attendance_as_csv(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        \App\Models\WorkshopAttendance::query()->create([
            'workshop_id' => $workshop->id,
            'source' => 'dropin',
            'child_name' => 'Taylor Example',
            'guardian_name' => 'Jordan Example',
            'email' => 'jordan@example.com',
            'phone' => '0400999888',
            'media_consent' => true,
            'attended_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.workshop.attendance.csv', $workshop));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Taylor Example', $content);
        $this->assertStringContainsString('Jordan Example', $content);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    private function createWorkshop(string $registration): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-kiosk.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => 'Kiosk Workshop',
            'content' => '<p>Attendance</p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => $registration,
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }
}
