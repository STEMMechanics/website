<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PickListTemplateItemPreservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_updating_template_preserves_existing_item_ids_and_workshop_checked_state(): void
    {
        $admin = $this->createAdminUser();
        $template = PickListTemplate::query()->create([
            'name' => 'Robotics Kit',
            'description' => 'Original notes',
        ]);

        $battery = PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Battery pack',
            'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);
        $toolkit = PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Toolkit',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 1,
            'sort_order' => 20,
        ]);

        $workshop = $this->createWorkshop();
        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
            'pick_list_checked_item_ids' => [$toolkit->id],
        ])->save();

        $response = $this->actingAs($admin)
            ->from(route('admin.pick-list-template.edit', $template))
            ->put(route('admin.pick-list-template.update', $template), [
                'name' => 'Robotics Kit',
                'description' => 'Updated notes',
                'items' => [
                    [
                        'id' => $battery->id,
                        'item_name' => 'Battery pack',
                        'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
                        'quantity_value' => 2,
                        'sort_order' => 10,
                    ],
                    [
                        'id' => $toolkit->id,
                        'item_name' => 'Toolkit and labels',
                        'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                        'quantity_value' => 3,
                        'sort_order' => 20,
                    ],
                    [
                        'item_name' => 'Safety goggles',
                        'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                        'quantity_value' => 1,
                        'sort_order' => 30,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.pick-list-template.edit', $template));

        $this->assertDatabaseHas('pick_list_template_items', [
            'id' => $battery->id,
            'pick_list_template_id' => $template->id,
            'item_name' => 'Battery pack',
            'quantity_value' => 2,
        ]);
        $this->assertDatabaseHas('pick_list_template_items', [
            'id' => $toolkit->id,
            'pick_list_template_id' => $template->id,
            'item_name' => 'Toolkit and labels',
            'quantity_value' => 3,
        ]);
        $this->assertSame(3, PickListTemplateItem::query()->where('pick_list_template_id', $template->id)->count());

        $workshop->refresh();
        $this->assertSame([$toolkit->id], array_map('intval', $workshop->pick_list_checked_item_ids ?? []));

        $pickListResponse = $this->actingAs($admin)
            ->get(route('admin.workshop.pick-list', $workshop));

        $pickListResponse->assertOk();
        $this->assertSame([$toolkit->id], array_map('intval', $pickListResponse->viewData('checkedItemIds') ?? []));

        $templateItems = collect($pickListResponse->viewData('templateItems') ?? []);
        $toolkitRow = $templateItems->firstWhere('id', $toolkit->id);

        $this->assertNotNull($toolkitRow);
        $this->assertSame('Toolkit and labels', $toolkitRow['item_name']);
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

    private function createWorkshop(): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => 'Workshop Pick List',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(2),
            'status' => 'open',
            'registration' => 'tickets',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }
}
