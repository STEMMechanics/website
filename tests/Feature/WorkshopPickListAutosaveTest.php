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

class WorkshopPickListAutosaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_pick_list_autosave_returns_json_and_persists_participants_and_checked_items(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop();

        $template = PickListTemplate::query()->create([
            'name' => 'Robotics Kit',
            'description' => 'Template notes',
        ]);

        $allowedItem = PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Battery pack',
            'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 1,
            'sort_order' => 1,
        ]);

        PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Screwdriver',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 2,
            'sort_order' => 2,
        ]);

        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
        ])->save();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'pick_list_participants' => 8,
                'pick_list_notes' => 'Bring extension cords',
                'checked_item_ids' => [$allowedItem->id, 999999],
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('pick_list_participants', 8);
        $response->assertJsonPath('checked_item_ids.0', $allowedItem->id);

        $freshWorkshop = $workshop->fresh();

        $this->assertSame(8, (int) $freshWorkshop->pick_list_participants);
        $this->assertSame('Bring extension cords', (string) $freshWorkshop->pick_list_notes);
        $this->assertSame([$allowedItem->id], array_map('intval', $freshWorkshop->pick_list_checked_item_ids ?? []));
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
