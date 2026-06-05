<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopTypeNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_changing_workshop_to_online_clears_location_id(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1200,
            'user_id' => $owner->id,
        ]);

        $workshop = Workshop::query()->create([
            'title' => 'Physical Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(4),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), [
                'title' => 'Now Online Workshop',
                'content' => '<p>Updated content</p>',
                'summary' => 'Online workshop summary',
                'type' => 'online',
                'location_id' => $location->id,
                'starts_at' => $workshop->starts_at?->toDateTimeString(),
                'ends_at' => $workshop->ends_at?->toDateTimeString(),
                'publish_at' => $workshop->publish_at?->toDateTimeString(),
                'closes_at' => $workshop->closes_at?->toDateTimeString(),
                'status' => 'open',
                'registration' => 'none',
                'hero_media_name' => $heroName,
            ]);

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHasNoErrors();
        $this->assertNull($workshop->fresh()->location_id);
        $this->assertSame('Online workshop summary', (string) $workshop->fresh()->summary);
    }

    public function test_admin_workshop_edit_shows_summary_field_and_prefills_saved_value(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'none', 'Existing workshop summary');

        $response = $this->actingAs($admin)->get(route('admin.workshop.edit', $workshop));

        $response->assertOk();
        $response->assertSee('Summary');
        $response->assertSee('Existing workshop summary', false);
    }

    public function test_admin_workshop_edit_prefills_cancellation_message_with_generic_copy(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'tickets');

        $response = $this->actingAs($admin)->get(route('admin.workshop.edit', $workshop));

        $response->assertOk();
        $response->assertSee('Please see below for your refund or credit details.', false);
    }

    public function test_admin_workshop_edit_uses_the_current_registration_when_deciding_whether_to_show_the_cancel_flow(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = Workshop::query()->create([
            'title' => 'External Link Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(4),
            'status' => 'open',
            'registration' => 'link',
            'registration_data' => 'https://example.com/tickets',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.workshop.edit', $workshop));

        $response->assertOk();
        $response->assertSee("registration: 'link'", false);
        $response->assertSee("['tickets', 'classroom'].includes(String(this.registration || ''))", false);
    }

    public function test_cancelling_external_link_workshops_does_not_cancel_internal_tickets(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'link', null, null, 'https://example.com/tickets');
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, $heroName, [
                'registration' => 'link',
                'registration_data' => 'https://example.com/tickets',
                'status' => 'cancelled',
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHas('message-title', 'Workshop cancelled');

        $freshWorkshop = $workshop->fresh();
        $this->assertSame('cancelled', (string) $freshWorkshop->status);
        $this->assertSame(Ticket::STATUS_PAID, (int) $ticket->fresh()->status);
    }

    public function test_admin_workshop_edit_can_reset_a_custom_pick_list_back_to_the_template(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $template = PickListTemplate::query()->create([
            'name' => 'Minecraft (Laptops Only)',
            'description' => 'Template notes',
        ]);
        $templateItem = PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Laptop charger',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'none');
        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
            'pick_list_is_customized' => true,
            'pick_list_custom_items' => [[
                'id' => $templateItem->id,
                'item_name' => 'Laptop charger',
                'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                'quantity_value' => 1,
                'sort_order' => 10,
            ]],
            'pick_list_notes' => 'Workshop notes',
        ])->save();

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, $heroName, [
                'pick_list_template_id' => $template->id,
                'pick_list_notes' => '',
                'reset_pick_list_customization' => 1,
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHasNoErrors();

        $freshWorkshop = $workshop->fresh();
        $this->assertFalse((bool) $freshWorkshop->pick_list_is_customized);
        $this->assertNull($freshWorkshop->pick_list_custom_items);
        $this->assertSame($template->id, (int) $freshWorkshop->pick_list_template_id);
        $this->assertSame('Template notes', (string) $freshWorkshop->pick_list_notes);
    }

    public function test_changing_registration_away_from_tickets_is_blocked_when_active_tickets_exist(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'tickets');
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, $heroName, [
                'registration' => 'none',
            ]));

        $response->assertRedirect(route('admin.workshop.edit', $workshop));
        $response->assertSessionHas('message-type', 'danger');
        $this->assertSame('tickets', $workshop->fresh()->registration);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
        ]);
    }

    public function test_changing_registration_away_from_tickets_keeps_existing_inactive_ticket_records(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'tickets');
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, $heroName, [
                'registration' => 'none',
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame('none', $workshop->fresh()->registration);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_CANCELLED,
        ]);
    }

    public function test_deleting_workshop_is_blocked_when_active_tickets_exist(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = $this->createHeroMedia($owner);

        $workshop = $this->createWorkshop($owner, $location, $heroName, 'tickets');
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PENDING_XFER,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.workshop.destroy', $workshop));

        $response->assertRedirect(route('admin.workshop.edit', $workshop));
        $response->assertSessionHas('message-type', 'danger');
        $this->assertDatabaseHas('workshops', ['id' => $workshop->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    private function createHeroMedia(User $owner): string
    {
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1200,
            'user_id' => $owner->id,
        ]);

        return $heroName;
    }

    private function createWorkshop(
        User $owner,
        Location $location,
        string $heroName,
        string $registration = 'none',
        ?string $summary = null,
        ?string $content = null,
        ?string $registrationData = null
    ): Workshop
    {
        $payload = [
            'title' => 'Physical Workshop',
            'content' => $content ?? '<p>Workshop content</p>',
            'summary' => $summary,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(4),
            'status' => 'open',
            'registration' => $registration,
            'registration_data' => $registrationData,
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ];

        return Workshop::query()->create($payload);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function workshopUpdatePayload(Workshop $workshop, Location $location, string $heroName, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Updated Workshop',
            'content' => '<p>Updated content</p>',
            'type' => 'physical',
            'location_id' => $location->id,
            'starts_at' => $workshop->starts_at?->toDateTimeString(),
            'ends_at' => $workshop->ends_at?->toDateTimeString(),
            'publish_at' => $workshop->publish_at?->toDateTimeString(),
            'closes_at' => $workshop->closes_at?->toDateTimeString(),
            'status' => 'open',
            'registration' => 'none',
            'hero_media_name' => $heroName,
        ], $overrides);
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
}
