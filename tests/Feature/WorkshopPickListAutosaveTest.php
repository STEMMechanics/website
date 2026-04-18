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
use Illuminate\Support\Facades\Storage;
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

    public function test_pick_list_notes_only_save_keeps_the_template_link_and_shows_template_notes_when_blank(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop();

        $template = PickListTemplate::query()->create([
            'name' => 'Robotics Kit',
            'description' => 'Template notes',
        ]);

        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
            'pick_list_notes' => null,
        ])->save();

        $viewResponse = $this->actingAs($admin)
            ->get(route('admin.workshop.pick-list', $workshop));

        $viewResponse->assertOk();
        $this->assertSame('Template notes', (string) $viewResponse->viewData('pickListNotes'));

        $saveResponse = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'pick_list_notes' => 'Workshop-specific notes',
            ]);

        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('pick_list_is_customized', false);

        $freshWorkshop = $workshop->fresh();
        $this->assertSame($template->id, (int) $freshWorkshop->pick_list_template_id);
        $this->assertSame('Workshop-specific notes', (string) $freshWorkshop->pick_list_notes);
        $this->assertFalse((bool) $freshWorkshop->pick_list_is_customized);
    }

    public function test_pick_list_autosave_for_template_items_does_not_turn_the_workshop_custom(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop();

        $template = PickListTemplate::query()->create([
            'name' => 'Minecraft (Laptops Only)',
            'description' => 'Template notes',
        ]);

        $item = PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Laptop charger',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);

        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
            'pick_list_is_customized' => false,
            'pick_list_custom_items' => null,
        ])->save();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'checked_item_ids' => [$item->id],
            ]);

        $response->assertOk();
        $response->assertJsonPath('pick_list_is_customized', false);
        $response->assertJsonMissingPath('pick_list_custom_items.0');

        $freshWorkshop = $workshop->fresh();
        $this->assertFalse((bool) $freshWorkshop->pick_list_is_customized);
        $this->assertNull($freshWorkshop->pick_list_custom_items);
        $this->assertSame([$item->id], array_map('intval', $freshWorkshop->pick_list_checked_item_ids ?? []));
    }

    public function test_pick_list_reset_can_restore_the_selected_template_and_clear_custom_items(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop();

        $template = PickListTemplate::query()->create([
            'name' => 'Minecraft (Laptops Only)',
            'description' => 'Template notes',
        ]);

        $workshop->forceFill([
            'pick_list_template_id' => $template->id,
            'pick_list_is_customized' => true,
            'pick_list_custom_items' => [
                [
                    'id' => 1,
                    'item_name' => 'Laptop charger',
                    'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                    'quantity_value' => 1,
                    'sort_order' => 10,
                ],
            ],
            'pick_list_notes' => 'Workshop notes',
        ])->save();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'reset_pick_list_customization' => 1,
                'pick_list_notes' => '',
            ]);

        $response->assertOk();
        $response->assertJsonPath('pick_list_is_customized', false);
        $response->assertJsonPath('pick_list_custom_items', []);

        $freshWorkshop = $workshop->fresh();
        $this->assertFalse((bool) $freshWorkshop->pick_list_is_customized);
        $this->assertNull($freshWorkshop->pick_list_custom_items);
        $this->assertSame($template->id, (int) $freshWorkshop->pick_list_template_id);
        $this->assertSame('Template notes', (string) $freshWorkshop->pick_list_notes);
    }

    public function test_pick_list_button_is_available_without_a_template_selected(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop();

        $this->actingAs($admin)
            ->get(route('admin.workshop.index'))
            ->assertOk()
            ->assertSee(route('admin.workshop.pick-list', $workshop), false);

        $this->actingAs($admin)
            ->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSee(route('admin.workshop.pick-list', $workshop), false);
    }

    public function test_non_ticketed_pick_list_shows_participant_count_and_persists_manual_items_without_a_template(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop(registration: 'none');

        $viewResponse = $this->actingAs($admin)
            ->get(route('admin.workshop.pick-list', $workshop));

        $viewResponse->assertOk();
        $viewResponse->assertSeeText('Participants');

        $saveResponse = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'pick_list_participants' => 6,
                'pick_list_notes' => 'Manual materials list',
                'pick_list_custom_items' => [
                    [
                        'id' => 1,
                        'item_name' => 'Workshop pack',
                        'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
                        'quantity_value' => 2,
                    ],
                    [
                        'id' => 2,
                        'item_name' => 'Extension lead',
                        'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                        'quantity_value' => 3,
                    ],
                ],
                'checked_item_ids' => [1],
            ]);

        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('pick_list_participants', 6);
        $saveResponse->assertJsonPath('pick_list_is_customized', true);
        $saveResponse->assertJsonPath('pick_list_custom_items.0.item_name', 'Workshop pack');
        $saveResponse->assertJsonPath('pick_list_custom_items.0.quantity_type', PickListTemplateItem::TYPE_PER_PARTICIPANT);

        $freshWorkshop = $workshop->fresh();
        $this->assertNull($freshWorkshop->pick_list_template_id);
        $this->assertSame(6, (int) $freshWorkshop->pick_list_participants);
        $this->assertTrue((bool) $freshWorkshop->pick_list_is_customized);
        $this->assertSame('Workshop pack', (string) ($freshWorkshop->pick_list_custom_items[0]['item_name'] ?? ''));

        $pickListResponse = $this->actingAs($admin)
            ->get(route('admin.workshop.pick-list', $workshop));

        $pickListResponse->assertOk();
        $this->assertSame(6, (int) $pickListResponse->viewData('participants'));

        $calculatedItems = collect($pickListResponse->viewData('calculatedItems') ?? []);
        $this->assertSame(12, (int) ($calculatedItems->first()['quantity'] ?? 0));
        $this->assertSame('(2 per participant)', (string) ($calculatedItems->first()['type_note'] ?? ''));
    }

    public function test_pick_list_autosave_persists_editable_canvas_json_and_thumbnail_and_clears_them(): void
    {
        Storage::fake('public');

        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop();
        $thumbnailPath = 'workshop-pick-list-thumbnails/workshop-'.$workshop->id.'.png';
        $canvasPayload = [
            'schema_version' => 1,
            'viewport' => [
                'transform' => [1.4, 0, 0, 1.4, 120, -40],
            ],
            'brush' => [
                'color' => '#16a34a',
                'size' => 6,
            ],
            'canvas' => [
                'version' => '7.2.0',
                'objects' => [
                    [
                        'type' => 'path',
                        'version' => '7.2.0',
                        'originX' => 'left',
                        'originY' => 'top',
                        'left' => 100,
                        'top' => 80,
                        'width' => 50,
                        'height' => 20,
                        'fill' => null,
                        'stroke' => '#16a34a',
                        'strokeWidth' => 6,
                        'strokeLineCap' => 'round',
                        'strokeLineJoin' => 'round',
                        'strokeMiterLimit' => 10,
                        'scaleX' => 1,
                        'scaleY' => 1,
                        'angle' => 0,
                        'flipX' => false,
                        'flipY' => false,
                        'opacity' => 1,
                        'shadow' => null,
                        'visible' => true,
                        'backgroundColor' => '',
                        'fillRule' => 'nonzero',
                        'paintFirst' => 'fill',
                        'globalCompositeOperation' => 'source-over',
                        'skewX' => 0,
                        'skewY' => 0,
                        'path' => [
                            ['M', 0, 0],
                            ['Q', 10, 10, 20, 5],
                        ],
                        'smIsEraser' => false,
                        'selectable' => false,
                        'evented' => false,
                        'hasControls' => false,
                        'hasBorders' => false,
                    ],
                ],
                'background' => '#ffffff',
            ],
        ];

        $saveResponse = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'pick_list_participants' => 3,
                'pick_list_notes' => 'Marked up on iPad',
                'pick_list_canvas_data' => json_encode($canvasPayload, JSON_UNESCAPED_SLASHES),
                'pick_list_canvas_thumbnail_data' => $this->samplePngDataUrl(),
            ]);

        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('ok', true);
        $saveResponse->assertJsonPath('pick_list_canvas_has_content', true);
        $this->assertStringContainsString($thumbnailPath, (string) $saveResponse->json('pick_list_canvas_thumbnail_url'));

        $savedWorkshop = $workshop->fresh();

        $this->assertSame($canvasPayload, json_decode((string) $savedWorkshop->pick_list_canvas_data, true));
        $this->assertSame($thumbnailPath, $savedWorkshop->pick_list_canvas_thumbnail_path);
        Storage::disk('public')->assertExists($thumbnailPath);

        $clearResponse = $this->actingAs($admin)
            ->postJson(route('admin.workshop.pick-list.save', $workshop), [
                'pick_list_canvas_data' => '',
                'pick_list_canvas_thumbnail_data' => '',
            ]);

        $clearResponse->assertOk();
        $clearResponse->assertJsonPath('ok', true);
        $clearResponse->assertJsonPath('pick_list_canvas_has_content', false);
        $clearResponse->assertJsonPath('pick_list_canvas_thumbnail_url', null);

        $clearedWorkshop = $workshop->fresh();

        $this->assertNull($clearedWorkshop->pick_list_canvas_data);
        $this->assertNull($clearedWorkshop->pick_list_canvas_thumbnail_path);
        Storage::disk('public')->assertMissing($thumbnailPath);
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

    private function createWorkshop(string $registration = 'tickets'): Workshop
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
            'registration' => $registration,
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }

    private function samplePngDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';
    }
}
