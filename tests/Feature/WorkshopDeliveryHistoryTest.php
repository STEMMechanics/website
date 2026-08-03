<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use App\Models\WorkshopCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkshopDeliveryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    public function test_admin_can_manage_an_organisation_and_its_contacts(): void
    {
        $admin = $this->createAdminUser();
        $contact = User::factory()->create(['email_verified_at' => now()]);
        $parent = Organisation::factory()->create(['name' => 'Cairns Regional Council']);

        $response = $this->actingAs($admin)->post(route('admin.organisation.store'), [
            'name' => 'Cairns Libraries',
            'type' => 'library',
            'parent_id' => $parent->id,
            'contact_ids' => [$contact->id],
        ]);

        $organisation = Organisation::query()->where('name', 'Cairns Libraries')->firstOrFail();
        $response->assertRedirect(route('admin.organisation.edit', $organisation));
        $this->assertSame((string) $parent->id, (string) $organisation->parent_id);
        $this->assertTrue($organisation->contacts()->whereKey($contact->id)->exists());
    }

    public function test_deleting_an_organisation_returns_the_expected_redirect_for_ajax_and_standard_requests(): void
    {
        $admin = $this->createAdminUser();
        $ajaxOrganisation = Organisation::factory()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.organisation.destroy', $ajaxOrganisation), [], [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'redirect' => route('admin.organisation.index'),
            ]);
        $this->assertModelMissing($ajaxOrganisation);

        $standardOrganisation = Organisation::factory()->create();
        $this->actingAs($admin)
            ->delete(route('admin.organisation.destroy', $standardOrganisation))
            ->assertRedirect(route('admin.organisation.index'));
        $this->assertModelMissing($standardOrganisation);
    }

    public function test_renaming_an_organisation_is_reflected_for_primary_contacts(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'company'));

        $admin = $this->createAdminUser();
        $primaryContact = User::factory()->create();
        $secondaryContact = User::factory()->create();
        $organisation = Organisation::factory()->create(['name' => 'Old Library Name']);
        $primaryContact->update(['primary_organisation_id' => $organisation->id]);
        $organisation->contacts()->attach([$primaryContact->id, $secondaryContact->id]);

        $this->actingAs($admin)
            ->put(route('admin.organisation.update', $organisation), [
                'name' => 'New Library Name',
                'type' => $organisation->type,
                'contact_ids' => [$primaryContact->id, $secondaryContact->id],
            ])
            ->assertRedirect(route('admin.organisation.edit', $organisation));

        $this->assertSame('New Library Name', $primaryContact->fresh()->primaryOrganisation?->name);
        $this->assertNull($secondaryContact->fresh()->primary_organisation_id);
    }

    public function test_removing_a_primary_contact_clears_the_primary_organisation_relationship(): void
    {
        $admin = $this->createAdminUser();
        $organisation = Organisation::factory()->create(['name' => 'Cairns Libraries']);
        $contact = User::factory()->create([
            'primary_organisation_id' => $organisation->id,
        ]);
        $organisation->contacts()->attach($contact->id);

        $this->actingAs($admin)
            ->put(route('admin.organisation.update', $organisation), [
                'name' => $organisation->name,
                'type' => $organisation->type,
                'contact_ids' => [],
            ])
            ->assertRedirect(route('admin.organisation.edit', $organisation));

        $contact->refresh();
        $this->assertNull($contact->primary_organisation_id);
    }

    public function test_organisation_contact_search_returns_matching_users_without_loading_every_user(): void
    {
        $admin = $this->createAdminUser();
        $matching = User::factory()->create([
            'firstname' => 'Jemima',
            'surname' => 'Jones',
            'email_verified_at' => now(),
        ]);
        $organisation = Organisation::factory()->create(['name' => 'Cairns Libraries']);
        $matching->update(['primary_organisation_id' => $organisation->id]);
        $organisation->contacts()->attach($matching->id);
        User::factory()->create([
            'firstname' => 'Unrelated',
            'surname' => 'Customer',
            'email_verified_at' => now(),
        ]);
        $ghost = User::factory()->unverified()->create([
            'firstname' => 'Ghost',
            'surname' => 'Contact',
            'primary_organisation_id' => $organisation->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.organisation.contact-options', ['search' => 'Cairns']))
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', (string) $matching->id)
            ->assertJsonPath('users.0.name', 'Jemima Jones')
            ->assertJsonPath('users.0.organisation_id', (string) $organisation->id);

        $this->actingAs($admin)
            ->getJson(route('admin.organisation.contact-options', [
                'search' => 'Cairns',
                'include_ghost' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'users')
            ->assertJsonFragment(['id' => (string) $ghost->id]);
    }

    public function test_admin_can_select_or_create_an_organisation_from_the_user_editor(): void
    {
        $admin = $this->createAdminUser();
        $user = User::factory()->create();
        Organisation::factory()->create(['name' => 'Existing School']);

        $this->actingAs($admin)
            ->get(route('admin.user.edit', $user))
            ->assertOk()
            ->assertSee('Organisation (Optional)')
            ->assertSee('Existing School')
            ->assertSee(route('admin.workshop.history', [
                'requested_by_user_id' => $user->id,
                'include_cancelled' => 1,
            ]));

        $this->actingAs($admin)
            ->put(route('admin.user.update', $user), [
                'firstname' => $user->firstname,
                'surname' => $user->surname,
                'organisation_name' => 'New Community Group',
                'email' => $user->email,
                'phone' => $user->phone,
                'groups' => '',
                'account_terms_days' => 0,
            ])
            ->assertRedirect();

        $organisation = Organisation::query()->where('name', 'New Community Group')->firstOrFail();
        $this->assertDatabaseHas('organisation_user', [
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame((string) $organisation->id, (string) $user->fresh()->primary_organisation_id);
    }

    public function test_workshop_can_record_a_host_organisation_and_requesting_contact(): void
    {
        $admin = $this->createAdminUser();
        $contact = User::factory()->create(['email_verified_at' => now()]);
        $organisation = Organisation::factory()->create(['name' => 'Library families']);
        $contact->update(['primary_organisation_id' => $organisation->id]);
        $organisation->contacts()->attach($contact->id);
        $workshop = $this->createWorkshop($admin);

        $payload = $this->workshopPayload($workshop, [
            'requested_by_user_id' => $contact->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $payload)
            ->assertRedirect(route('admin.workshop.edit', $workshop));

        $workshop->refresh();
        $this->assertSame((string) $organisation->id, (string) $workshop->hosted_for_organisation_id);
        $this->assertSame((string) $contact->id, (string) $workshop->requested_by_user_id);
        $this->assertSame('Library families', $workshop->hostedFor?->name);

        $this->actingAs($admin)
            ->get(route('admin.workshop.edit', $workshop))
            ->assertOk()
            ->assertSee('id="requested_by_user_search"', false)
            ->assertSee('id="hosted_for_organisation_search"', false)
            ->assertSee("url.searchParams.set('include_ghost', '1');", false);

        $this->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertDontSee('Library families');
    }

    public function test_non_ticketed_workshop_can_record_anonymous_attendees(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop($admin);

        $this->actingAs($admin)
            ->post(route('admin.workshop.attendance.dropin.sync', $workshop), [
                'entries' => [
                    ['is_anonymous' => 1],
                    ['is_anonymous' => 1],
                    ['is_anonymous' => 1],
                ],
            ])
            ->assertRedirect(route('admin.workshop.attendance', $workshop));

        $this->assertSame(3, $workshop->reportedAttendeeCount());
        $this->assertSame(3, $workshop->attendances()->where('is_anonymous', true)->count());
    }

    public function test_workshop_with_no_attendance_records_reports_zero_attendees(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop($admin);

        $this->assertSame(0, $workshop->reportedAttendeeCount());
    }

    public function test_non_ticketed_workshop_counts_detailed_attendance_when_no_aggregate_is_recorded(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop($admin);

        WorkshopAttendance::factory()->count(3)->create([
            'workshop_id' => $workshop->id,
            'ticket_id' => null,
            'attended_at' => now(),
        ]);

        $this->assertSame(3, $workshop->reportedAttendeeCount());
    }

    public function test_ticketed_workshop_uses_live_attendance_and_ignores_manual_count(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop($admin, [
            'registration' => 'tickets',
            'max_tickets' => 20,
        ]);

        Ticket::factory()->count(2)->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'attended_at' => now(),
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'attended_at' => null,
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_CANCELLED,
            'attended_at' => now(),
        ]);
        WorkshopAttendance::factory()->create([
            'workshop_id' => $workshop->id,
            'ticket_id' => null,
            'attended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.edit', $workshop))
            ->assertOk()
            ->assertDontSee('Attendee Count');

        $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopPayload($workshop, [
                'max_tickets' => 20,
            ]))
            ->assertRedirect(route('admin.workshop.edit', $workshop));

        $workshop->refresh();
        $this->assertSame(3, $workshop->reportedAttendeeCount());
    }

    public function test_history_filters_by_a_parent_organisation_and_includes_children(): void
    {
        $admin = $this->createAdminUser();
        $parent = Organisation::factory()->create(['name' => 'Cairns Regional Council']);
        $child = Organisation::factory()->create([
            'name' => 'Cairns Libraries',
            'parent_id' => $parent->id,
        ]);
        $other = Organisation::factory()->create(['name' => 'Other Council']);

        $included = $this->createWorkshop($admin, [
            'title' => 'Library Robotics',
            'hosted_for_organisation_id' => $child->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addHours(2),
        ]);
        $this->createWorkshop($admin, [
            'title' => 'Other Workshop',
            'hosted_for_organisation_id' => $other->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addHours(2),
        ]);

        $this->actingAs($admin)->get(route('admin.workshop.history', [
            'organisation_id' => $parent->id,
            'include_children' => 1,
        ]))
            ->assertOk()
            ->assertSee($included->title)
            ->assertDontSee('Other Workshop');
    }

    public function test_history_searches_the_host_organisation_name(): void
    {
        $admin = $this->createAdminUser();
        $organisation = Organisation::factory()->create(['name' => 'Legacy School Group']);
        $this->createWorkshop($admin, [
            'title' => 'Private Creative Session',
            'hosted_for_organisation_id' => $organisation->id,
            'is_private' => true,
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHours(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.history', ['search' => 'Legacy School']))
            ->assertOk()
            ->assertSee('Private Creative Session')
            ->assertSee('Legacy School Group');
    }

    public function test_history_includes_future_workshops_by_default_and_can_be_limited_to_past(): void
    {
        $admin = $this->createAdminUser();
        $this->createWorkshop($admin, [
            'title' => 'Future Stop Motion',
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(2),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.history'))
            ->assertOk()
            ->assertSee('Future Stop Motion');

        $this->actingAs($admin)
            ->get(route('admin.workshop.history', ['past_only' => 1]))
            ->assertOk()
            ->assertDontSee('Future Stop Motion');
    }

    public function test_history_and_matrix_category_filters_match_any_selected_category(): void
    {
        $admin = $this->createAdminUser();
        $organisation = Organisation::factory()->create(['name' => 'Category Test Organisation']);
        $robotics = WorkshopCategory::query()->create(['name' => 'Robotics', 'slug' => 'robotics']);
        $coding = WorkshopCategory::query()->create(['name' => 'Coding', 'slug' => 'coding']);
        $craft = WorkshopCategory::query()->create(['name' => 'Craft', 'slug' => 'craft']);

        $roboticsWorkshop = $this->createWorkshop($admin, ['title' => 'Robot Lab', 'hosted_for_organisation_id' => $organisation->id]);
        $codingWorkshop = $this->createWorkshop($admin, ['title' => 'Code Club', 'hosted_for_organisation_id' => $organisation->id]);
        $craftWorkshop = $this->createWorkshop($admin, ['title' => 'Craft Club', 'hosted_for_organisation_id' => $organisation->id]);
        $roboticsWorkshop->categories()->attach($robotics->id);
        $codingWorkshop->categories()->attach($coding->id);
        $craftWorkshop->categories()->attach($craft->id);

        $filters = ['category_ids' => [$robotics->id, $coding->id]];

        $this->actingAs($admin)
            ->get(route('admin.workshop.history', $filters))
            ->assertOk()
            ->assertSee('Robot Lab')
            ->assertSee('Code Club')
            ->assertDontSee('Craft Club')
            ->assertSee('history_category_search', false)
            ->assertSee('x-on:focus="open = true"', false)
            ->assertDontSee('term.length &lt; 2', false);

        $this->actingAs($admin)
            ->get(route('admin.workshop.coverage', [
                ...$filters,
                'organisation_ids' => [$organisation->id],
            ]))
            ->assertOk()
            ->assertSee('Robot Lab')
            ->assertSee('Code Club')
            ->assertDontSee('Craft Club')
            ->assertSee('matrix_category_search', false);
    }

    public function test_coverage_matrix_and_report_exports_are_available(): void
    {
        $admin = $this->createAdminUser();
        $library = Organisation::factory()->create(['name' => 'Cairns Libraries']);
        $school = Organisation::factory()->create(['name' => 'Example School']);
        $cityLibrary = Location::factory()->create(['name' => 'City Library']);
        $smithfieldLibrary = Location::factory()->create(['name' => 'Smithfield Library']);
        $this->createWorkshop($admin, [
            'title' => 'Stop Motion',
            'hosted_for_organisation_id' => $library->id,
            'location_id' => $cityLibrary->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addHours(2),
        ]);
        $this->createWorkshop($admin, [
            'title' => 'Stop Motion',
            'hosted_for_organisation_id' => $library->id,
            'location_id' => $smithfieldLibrary->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addHours(2),
        ]);
        $this->createWorkshop($admin, [
            'title' => 'Stop Motion',
            'hosted_for_organisation_id' => $school->id,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(2),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.coverage'))
            ->assertOk()
            ->assertSee('Matrix')
            ->assertSee('Select organisations to build the matrix')
            ->assertDontSee('Stop Motion');

        $matrixFilters = ['organisation_ids' => [$library->id, $school->id]];

        $this->actingAs($admin)
            ->get(route('admin.workshop.coverage', $matrixFilters))
            ->assertOk()
            ->assertSee('Cairns Libraries')
            ->assertSee('Example School')
            ->assertSee('City Library')
            ->assertSee('Smithfield Library')
            ->assertSee('Stop Motion');

        $csvResponse = $this->actingAs($admin)
            ->get(route('admin.workshop.coverage.csv', $matrixFilters))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('Cairns Libraries — City Library', $csvContent);
        $this->assertStringContainsString('Cairns Libraries — Smithfield Library', $csvContent);

        $this->actingAs($admin)
            ->get(route('admin.workshop.history.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('admin.workshop.coverage.pdf', $matrixFilters))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function createWorkshop(User $admin, array $attributes = []): Workshop
    {
        $location = Location::factory()->create();
        $hero = Media::factory()->create([
            'mime_type' => 'image/jpeg',
            'user_id' => $admin->id,
        ]);

        return Workshop::factory()->create(array_merge([
            'title' => 'Test Workshop',
            'content' => '<p>Workshop content.</p>',
            'type' => Workshop::TYPE_PHYSICAL,
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $hero->name,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(2),
            'publish_at' => now()->subMonth(),
            'closes_at' => now()->subDays(2),
            'status' => 'closed',
            'registration' => 'none',
        ], $attributes));
    }

    private function workshopPayload(Workshop $workshop, array $attributes = []): array
    {
        return array_merge([
            'title' => $workshop->title,
            'content' => $workshop->content,
            'type' => $workshop->type,
            'location_id' => $workshop->location_id,
            'starts_at' => $workshop->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $workshop->ends_at->format('Y-m-d\TH:i'),
            'publish_at' => $workshop->publish_at->format('Y-m-d\TH:i'),
            'closes_at' => $workshop->closes_at->format('Y-m-d\TH:i'),
            'status' => $workshop->status,
            'registration' => $workshop->registration,
            'hero_media_name' => $workshop->hero_media_name,
        ], $attributes);
    }
}
