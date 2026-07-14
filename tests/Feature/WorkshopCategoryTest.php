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

class WorkshopCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_workshop_category_with_an_icon(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->post(route('admin.workshop-category.store'), [
                'name' => 'Robotics',
                'slug' => 'robotics',
                'icon_class' => 'fa-solid fa-robot',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workshop_categories', [
            'name' => 'Robotics',
            'slug' => 'robotics',
            'icon_class' => 'fa-solid fa-robot',
        ]);
    }

    public function test_admin_can_assign_multiple_categories_to_a_workshop(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $hero = Media::factory()->create([
            'name' => 'robot-workshop.jpg',
            'mime_type' => 'image/jpeg',
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create([
            'title' => 'Robot Builders',
            'content' => '<p>Build robots.</p>',
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $hero->name,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addMonth()->subHour(),
            'status' => 'open',
            'registration' => 'none',
        ]);
        $robotics = WorkshopCategory::factory()->create(['name' => 'Robotics', 'slug' => 'robotics']);
        $coding = WorkshopCategory::factory()->create(['name' => 'Coding', 'slug' => 'coding']);

        $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), [
                'title' => 'Robot Builders',
                'content' => '<p>Build robots.</p>',
                'summary' => '',
                'type' => 'physical',
                'location_id' => $location->id,
                'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
                'publish_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'closes_at' => now()->addMonth()->subHour()->format('Y-m-d\TH:i'),
                'status' => 'open',
                'registration' => 'none',
                'hero_media_name' => $hero->name,
                'category_ids' => [$robotics->id, $coding->id],
            ])
            ->assertRedirect(route('admin.workshop.index'));

        $this->assertEqualsCanonicalizing(
            [$robotics->id, $coding->id],
            $workshop->fresh()->categories()->pluck('workshop_categories.id')->all()
        );
    }

    public function test_public_workshop_cards_can_be_filtered_by_category(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $robotics = WorkshopCategory::factory()->create(['name' => 'Robotics', 'slug' => 'robotics', 'icon_class' => 'fa-solid fa-robot']);
        $art = WorkshopCategory::factory()->create(['name' => 'Creative Art', 'slug' => 'creative-art']);

        $robotWorkshop = $this->createPublicWorkshop($admin, $location, 'Robot Builders');
        $artWorkshop = $this->createPublicWorkshop($admin, $location, 'Painted Circuits');
        $robotWorkshop->categories()->attach($robotics);
        $artWorkshop->categories()->attach($art);

        $this->get(route('workshop.index', ['category' => 'robotics']))
            ->assertOk()
            ->assertSee('Robot Builders')
            ->assertSee('Robotics')
            ->assertDontSee('Painted Circuits');
    }

    public function test_footer_links_to_category_filtered_workshops(): void
    {
        WorkshopCategory::factory()->create(['name' => 'Robotics', 'slug' => 'robotics', 'icon_class' => 'fa-solid fa-robot']);
        WorkshopCategory::factory()->create(['name' => 'Hidden Category', 'slug' => 'hidden-category', 'hide_in_footer' => true]);

        $this->get(route('workshop.index'))
            ->assertOk()
            ->assertSee('Workshops')
            ->assertSee('Robotics')
            ->assertSee(route('workshop.index', ['category' => 'robotics']), false)
            ->assertDontSee(route('workshop.index', ['category' => 'hidden-category']), false);
    }

    public function test_public_workshop_show_page_displays_category_tags(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $robotics = WorkshopCategory::factory()->create(['name' => 'Robotics', 'slug' => 'robotics', 'icon_class' => 'fa-solid fa-robot']);
        $workshop = $this->createPublicWorkshop($admin, $location, 'Robot Builders');
        $workshop->categories()->attach($robotics);

        $this->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSee('Robotics')
            ->assertSee(route('workshop.index', ['category' => 'robotics']), false)
            ->assertSeeInOrder(['Robotics', 'Workshop content.']);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    private function createPublicWorkshop(User $admin, Location $location, string $title): Workshop
    {
        $hero = Media::factory()->create([
            'name' => str($title)->slug().'.jpg',
            'mime_type' => 'image/jpeg',
            'user_id' => $admin->id,
        ]);

        return Workshop::factory()->create([
            'title' => $title,
            'content' => '<p>Workshop content.</p>',
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $hero->name,
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addWeeks(2)->subHour(),
            'status' => 'open',
            'registration' => 'none',
        ]);
    }
}
