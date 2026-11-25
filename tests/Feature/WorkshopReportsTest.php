<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_requested_contact_can_view_workshop_report_table_and_download_a_photo(): void
    {
        Storage::fake('media');
        $contact = User::factory()->unverified()->create();
        [$workshop, $photo] = $this->workshopFor($contact);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk()
            ->assertSee('Workshop Reports')
            ->assertSee($workshop->title)
            ->assertSee('Report Photo')
            ->assertSee('Report Venue')
            ->assertSee('1 Report Street')
            ->assertSee('Actions')
            ->assertSee('fa-circle-chevron-left', false)
            ->assertSee('Download image')
            ->assertSee('Download All Photos')
            ->assertDontSee('Attendance is shown')
            ->assertSee('TBC');

        $this->actingAs($contact)
            ->get(route('account.workshop-report.photo', [$workshop, $photo]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $download = $this->actingAs($contact)
            ->get(route('account.workshop-report.photo.download', [$workshop, $photo]));

        $download->assertOk();
        $this->assertStringContainsString(
            '2026-11-01-report-workshop-report-photo.jpg',
            (string) $download->headers->get('Content-Disposition')
        );
    }

    public function test_requested_contact_cannot_view_another_contacts_workshop_or_photo(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        $otherContact = User::factory()->create();
        [$otherWorkshop, $otherPhoto] = $this->workshopFor($otherContact, [
            'title' => 'Another Contact Workshop',
        ]);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk()
            ->assertDontSee('Another Contact Workshop');

        $this->actingAs($contact)
            ->get(route('account.workshop-report.photo', [$otherWorkshop, $otherPhoto]))
            ->assertNotFound();
    }

    public function test_attendance_is_tbc_for_past_workshops_without_attendance_records(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        [$workshop] = $this->workshopFor($contact, ['registration' => 'tickets']);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'attended_at' => null,
        ]);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk()
            ->assertSee('TBC');

        $workshop->update(['attendance_no_attendees_confirmed_at' => now()]);

        $response = $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/data-label="Attendance"[^>]*>\s*0\s*<\/td>/s',
            $response->getContent(),
        );
    }

    public function test_attendance_is_not_applicable_for_future_workshops(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        $startsAt = now()->addDays(3)->setTime(10, 0);
        $this->workshopFor($contact, [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ]);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk()
            ->assertSee('N/A');
    }

    public function test_attendance_combines_attended_tickets_and_drop_ins(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        [$workshop] = $this->workshopFor($contact, ['registration' => 'tickets']);
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'attended_at' => now(),
        ]);
        WorkshopAttendance::factory()->create([
            'workshop_id' => $workshop->id,
            'ticket_id' => null,
            'user_id' => $contact->id,
            'created_by' => $contact->id,
        ]);

        $response = $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/data-label="Attendance"[^>]*>\s*2\s*<\/td>/s',
            $response->getContent(),
        );

        $this->assertNotNull($ticket->fresh()->attended_at);
    }

    public function test_private_protected_and_passworded_photos_are_not_shown_to_a_non_owner(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        [$workshop, $publicPhoto] = $this->workshopFor($contact);
        $privatePhoto = $this->media(User::factory()->create(), 'Private Photo', 'private');
        $protectedPhoto = $this->media(User::factory()->create(), 'Protected Photo', 'protected');
        $passwordedPhoto = $this->media(User::factory()->create(), 'Passworded Photo', 'public', 'secret');
        $workshop->photos()->attach([
            $privatePhoto->name => ['collection' => 'workshop_photos'],
            $protectedPhoto->name => ['collection' => 'workshop_photos'],
            $passwordedPhoto->name => ['collection' => 'workshop_photos'],
        ]);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index'))
            ->assertOk()
            ->assertSee($publicPhoto->title)
            ->assertDontSee($privatePhoto->title)
            ->assertDontSee($protectedPhoto->title)
            ->assertDontSee($passwordedPhoto->title);
    }

    public function test_csv_export_contains_the_same_report_fields(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        [$workshop] = $this->workshopFor($contact, [
            'title' => 'CSV Report Workshop',
            'registration' => 'none',
        ]);

        $response = $this->actingAs($contact)
            ->get(route('account.workshop-report.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CSV Report Workshop', $csv);
        $this->assertStringContainsString('TBC', $csv);
        $this->assertTrue($workshop->exists);
    }

    public function test_filters_apply_to_the_table_and_csv_export(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        $otherLocation = Location::factory()->create(['name' => 'Other Report Venue']);
        [$withPhoto] = $this->workshopFor($contact, ['title' => 'Filtered With Photo']);
        [$withoutPhoto] = $this->workshopFor($contact, [
            'title' => 'Filtered Without Photo',
            'location_id' => $otherLocation->id,
            'attach_photo' => false,
        ]);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index', [
                'search' => 'Without Photo',
                'location' => $otherLocation->id,
                'photos' => 'without',
            ]))
            ->assertOk()
            ->assertSee($withoutPhoto->title)
            ->assertDontSee($withPhoto->title);

        $csv = $this->actingAs($contact)
            ->get(route('account.workshop-report.export', [
                'search' => 'Without Photo',
                'location' => $otherLocation->id,
                'photos' => 'without',
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($withoutPhoto->title, $csv);
        $this->assertStringNotContainsString($withPhoto->title, $csv);

        $this->actingAs($contact)
            ->get(route('account.workshop-report.index', ['photos' => 'with']))
            ->assertOk()
            ->assertSee($withPhoto->title)
            ->assertDontSee($withoutPhoto->title);
    }

    public function test_all_photos_download_contains_only_filtered_accessible_photos(): void
    {
        Storage::fake('media');
        $contact = User::factory()->create();
        [$workshop] = $this->workshopFor($contact, ['title' => 'Archive Workshop']);
        $this->workshopFor($contact, ['title' => 'Other Workshop']);

        $response = $this->actingAs($contact)
            ->get(route('account.workshop-report.photos.download', [
                'search' => 'Archive Workshop',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        $zipPath = $response->baseResponse->getFile()?->getPathname();
        $this->assertIsString($zipPath);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringContainsString('archive-workshop-report-photo.jpg', $zip->getNameIndex(0));
        $zip->close();

        $this->assertTrue($workshop->exists);
    }

    /**
     * @return array{0: Workshop, 1: Media}
     */
    private function workshopFor(User $contact, array $attributes = []): array
    {
        $attachPhoto = (bool) ($attributes['attach_photo'] ?? true);
        unset($attributes['attach_photo']);

        $location = Location::factory()->create([
            'name' => 'Report Venue',
            'address' => '1 Report Street',
            'suburb' => 'Brisbane',
            'state' => 'QLD',
            'postcode' => '4000',
        ]);
        $owner = User::factory()->create();
        $hero = $this->media($owner, 'Report Hero');
        $photo = $this->media($owner, 'Report Photo');
        $startsAt = now()->setDate(2026, 11, 1)->setTime(10, 0);

        $workshop = Workshop::query()->create(array_merge([
            'title' => 'Report Workshop',
            'hero_media_name' => $hero->name,
            'content' => '<p>Workshop content</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHours(2),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'requested_by_user_id' => $contact->id,
        ], $attributes));
        if ($attachPhoto) {
            $workshop->photos()->attach($photo->name, ['collection' => 'workshop_photos']);
        }

        return [$workshop, $photo];
    }

    private function media(User $owner, string $title, string $visibility = 'public', ?string $password = null): Media
    {
        $name = Str::slug($title).'-'.Str::lower(Str::random(6)).'.jpg';
        $hash = hash('sha256', $name);
        Storage::disk('media')->put($hash, 'image-bytes');

        return Media::query()->create([
            'name' => $name,
            'title' => $title,
            'hash' => $hash,
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'user_id' => $owner->id,
            'visibility' => $visibility,
            'password' => $password,
        ]);
    }
}
