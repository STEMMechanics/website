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

class WorkshopPickListCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_custom_pick_list_items_stop_syncing_from_the_template(): void
    {
        $admin = $this->createAdminUser();
        $template = PickListTemplate::query()->create([
            'name' => 'Robotics Kit',
            'description' => 'Template notes',
        ]);

        $battery = PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Battery pack',
            'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);
        PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Toolkit',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 1,
            'sort_order' => 20,
        ]);

        $workshop = $this->createWorkshop();
        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
        ])->save();

        $saveResponse = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'pick_list_notes' => 'Workshop-specific notes',
                'pick_list_custom_items' => [
                    [
                        'id' => 1,
                        'item_name' => 'Custom cable pack',
                        'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                        'quantity_value' => 4,
                    ],
                    [
                        'id' => 2,
                        'item_name' => 'Backup battery',
                        'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
                        'quantity_value' => 2,
                    ],
                ],
                'checked_item_ids' => [2, 999999],
            ]);

        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('pick_list_is_customized', true);
        $saveResponse->assertJsonPath('pick_list_custom_items.0.item_name', 'Custom cable pack');
        $saveResponse->assertJsonPath('checked_item_ids.0', 2);

        $workshop->refresh();
        $this->assertTrue((bool) $workshop->pick_list_is_customized);
        $this->assertSame('Workshop-specific notes', (string) $workshop->pick_list_notes);
        $this->assertSame('Custom cable pack', (string) ($workshop->pick_list_custom_items[0]['item_name'] ?? ''));
        $this->assertSame([2], array_map('intval', $workshop->pick_list_checked_item_ids ?? []));

        $this->actingAs($admin)
            ->from(route('admin.pick-list-template.edit', $template))
            ->put(route('admin.pick-list-template.update', $template), [
                'name' => 'Robotics Kit',
                'description' => 'Updated template notes',
                'items' => [
                    [
                        'id' => $battery->id,
                        'item_name' => 'Battery pack updated',
                        'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
                        'quantity_value' => 3,
                        'sort_order' => 10,
                    ],
                    [
                        'item_name' => 'New template item',
                        'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                        'quantity_value' => 1,
                        'sort_order' => 30,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.pick-list-template.edit', $template));

        $pickListResponse = $this->actingAs($admin)
            ->get(route('admin.workshop.pick-list', $workshop));

        $pickListResponse->assertOk();
        $this->assertSame([2], array_map('intval', $pickListResponse->viewData('checkedItemIds') ?? []));

        $visibleItems = collect($pickListResponse->viewData('templateItems') ?? []);
        $this->assertSame('Custom cable pack', (string) ($visibleItems[0]['item_name'] ?? ''));
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
