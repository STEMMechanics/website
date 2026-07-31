<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkshopBulkEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_bulk_editor_for_selected_workshops(): void
    {
        $admin = $this->makeAdmin();
        $hero = $this->makeHero($admin);
        $location = Location::factory()->create();
        $first = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $hero->name, 'location_id' => $location->id, 'ages' => '8+']);
        $second = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $hero->name, 'location_id' => $location->id, 'ages' => '8+']);

        $this->actingAs($admin)
            ->post(route('admin.workshop.bulk.select'), ['workshop_ids' => [$first->id, $second->id]])
            ->assertRedirect(route('admin.workshop.bulk.edit'));

        $this->actingAs($admin)
            ->get(route('admin.workshop.bulk.edit'))
            ->assertOk()
            ->assertSeeText('Editing 2 workshops')
            ->assertSeeText($first->title)
            ->assertSeeText($second->title)
            ->assertSee('8+');
    }

    public function test_bulk_update_changes_only_submitted_shared_values(): void
    {
        $admin = $this->makeAdmin();
        $hero = $this->makeHero($admin);
        $location = Location::factory()->create();
        $first = Workshop::factory()->create([
            'user_id' => $admin->id,
            'hero_media_name' => $hero->name,
            'location_id' => $location->id,
            'price' => '$20',
            'ages' => '8+',
            'status' => 'open',
        ]);
        $second = Workshop::factory()->create([
            'user_id' => $admin->id,
            'hero_media_name' => $hero->name,
            'location_id' => $location->id,
            'price' => '$30',
            'ages' => '12+',
            'status' => 'open',
        ]);

        $this->withSession(['admin_workshop_bulk_selection' => [$first->id, $second->id]])
            ->actingAs($admin)
            ->put(route('admin.workshop.bulk.update'), [
                'status' => 'closed',
                'is_private' => '1',
                'is_hidden' => '1',
                'price' => '',
                'ages' => '',
            ])
            ->assertRedirect(route('admin.workshop.index'));

        $this->assertSame('closed', $first->fresh()->status);
        $this->assertSame('closed', $second->fresh()->status);
        $this->assertTrue($first->fresh()->is_private);
        $this->assertTrue($second->fresh()->is_private);
        $this->assertTrue($first->fresh()->is_hidden);
        $this->assertTrue($second->fresh()->is_hidden);
        $this->assertSame('$20', $first->fresh()->price);
        $this->assertSame('$30', $second->fresh()->price);
        $this->assertSame('8+', $first->fresh()->ages);
        $this->assertSame('12+', $second->fresh()->ages);
    }

    public function test_bulk_update_adds_and_removes_categories_without_replacing_other_categories(): void
    {
        $admin = $this->makeAdmin();
        $hero = $this->makeHero($admin);
        $location = Location::factory()->create();
        $first = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $hero->name, 'location_id' => $location->id]);
        $second = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $hero->name, 'location_id' => $location->id]);
        $remove = WorkshopCategory::query()->create(['name' => 'Remove', 'slug' => 'remove']);
        $add = WorkshopCategory::query()->create(['name' => 'Add', 'slug' => 'add']);
        $keep = WorkshopCategory::query()->create(['name' => 'Keep', 'slug' => 'keep']);
        $first->categories()->attach([$remove->id, $keep->id]);
        $second->categories()->attach($remove->id);

        $this->withSession(['admin_workshop_bulk_selection' => [$first->id, $second->id]])
            ->actingAs($admin)
            ->put(route('admin.workshop.bulk.update'), [
                'remove_category_ids' => [$remove->id],
                'add_category_ids' => [$add->id],
            ])
            ->assertRedirect(route('admin.workshop.index'));

        $this->assertEqualsCanonicalizing([$keep->id, $add->id], $first->fresh()->categories->pluck('id')->all());
        $this->assertSame([$add->id], $second->fresh()->categories->pluck('id')->all());
    }

    public function test_bulk_update_preserves_categories_when_no_category_changes_are_submitted(): void
    {
        $admin = $this->makeAdmin();
        $hero = $this->makeHero($admin);
        $location = Location::factory()->create();
        $first = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $hero->name, 'location_id' => $location->id]);
        $second = Workshop::factory()->create(['user_id' => $admin->id, 'hero_media_name' => $hero->name, 'location_id' => $location->id]);
        $firstCategory = WorkshopCategory::query()->create(['name' => 'First', 'slug' => 'first']);
        $secondCategory = WorkshopCategory::query()->create(['name' => 'Second', 'slug' => 'second']);
        $first->categories()->attach([$firstCategory->id, $secondCategory->id]);
        $second->categories()->attach($secondCategory->id);

        $this->withSession(['admin_workshop_bulk_selection' => [$first->id, $second->id]])
            ->actingAs($admin)
            ->put(route('admin.workshop.bulk.update'), ['status' => 'closed'])
            ->assertRedirect(route('admin.workshop.index'));

        $this->assertEqualsCanonicalizing(
            [$firstCategory->id, $secondCategory->id],
            $first->fresh()->categories->pluck('id')->all(),
        );
        $this->assertSame([$secondCategory->id], $second->fresh()->categories->pluck('id')->all());
    }

    public function test_bulk_editor_requires_an_existing_selection(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.workshop.bulk.edit'))
            ->assertRedirect(route('admin.workshop.index'))
            ->assertSessionHasErrors('workshop_ids');
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }

    private function makeHero(User $owner): Media
    {
        return Media::query()->create([
            'name' => 'workshop-hero.jpg',
            'title' => 'Workshop Hero',
            'hash' => hash('sha256', 'workshop-hero.jpg'),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);
    }
}
