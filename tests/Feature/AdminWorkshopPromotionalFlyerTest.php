<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkshopPromotionalFlyerController;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class AdminWorkshopPromotionalFlyerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_flyer_builder_with_only_upcoming_workshops(): void
    {
        $admin = $this->makeAdmin();
        $upcoming = $this->makeWorkshop($admin, 'Upcoming Robotics', now()->addWeek());
        $past = $this->makeWorkshop($admin, 'Past Robotics', now()->subWeek());

        $this->actingAs($admin)
            ->get(route('admin.workshop-flyer.create'))
            ->assertOk()
            ->assertSeeText('Workshop Promotional Flyer')
            ->assertSeeText($upcoming->title)
            ->assertDontSeeText($past->title)
            ->assertDontSee('name="header"', false)
            ->assertSeeText('A4 with three DL flyers')
            ->assertSeeText('One DL flyer')
            ->assertSeeText('Picture size')
            ->assertSee('name="preview_workshop"', false)
            ->assertSee('x-model="customizations[activeId].description"', false);
    }

    public function test_admin_can_generate_a_three_up_a4_flyer_for_selected_workshops(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->makeWorkshop($admin, 'Robotics Lab', now()->addWeek());
        $second = $this->makeWorkshop($admin, 'Minecraft Makers', now()->addWeeks(2));

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop-flyer.generate'), [
                'workshop_ids' => [(string) $first->id, (string) $second->id],
                'footer' => 'Book at stemmechanics.com.au/workshops',
                'customizations' => [
                    (string) $first->id => [
                        'description' => 'Custom robotics description',
                        'image_zoom' => 140,
                        'image_x' => 25,
                        'image_y' => 75,
                    ],
                    (string) $second->id => [
                        'description' => 'Custom Minecraft description',
                        'image_zoom' => 100,
                        'image_x' => 50,
                        'image_y' => 50,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_flyer_rejects_more_than_three_workshops(): void
    {
        $admin = $this->makeAdmin();
        $workshops = collect(range(1, 4))->map(fn (int $index) => $this->makeWorkshop(
            $admin,
            'Workshop '.$index,
            now()->addDays($index),
        ));

        $this->actingAs($admin)
            ->from(route('admin.workshop-flyer.create'))
            ->post(route('admin.workshop-flyer.generate'), [
                'workshop_ids' => $workshops->pluck('id')->map('strval')->all(),
                'footer' => 'Book now',
            ])
            ->assertRedirect(route('admin.workshop-flyer.create'))
            ->assertSessionHasErrors('workshop_ids');
    }

    public function test_flyer_reads_hero_images_through_the_storage_disk(): void
    {
        $admin = $this->makeAdmin();
        $workshop = $this->makeWorkshop($admin, 'Robotics Lab', now()->addWeek());
        $variantStorage = Mockery::mock(Filesystem::class);
        $variantStorage->shouldReceive('exists')->times(3)->with('remote-hash-md')->andReturnTrue();
        $variantStorage->shouldReceive('get')->times(3)->with('remote-hash-md')->andReturn('unsupported image data');
        $sourceStorage = Mockery::mock(Filesystem::class);
        $sourceStorage->shouldReceive('exists')->times(3)->with('remote-hash')->andReturnTrue();
        $sourceStorage->shouldReceive('get')->times(3)->with('remote-hash')->andReturn(
            file_get_contents(public_path('logo.png')),
        );
        $media = Mockery::mock(Media::class)->makePartial();
        $media->hash = 'remote-hash';
        $media->shouldReceive('variantStorage')->times(3)->andReturn($variantStorage);
        $media->shouldReceive('sourceStorage')->times(3)->andReturn($sourceStorage);
        $workshop->setRelation('hero', $media);

        $image = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerImageData'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop);

        $this->assertIsString($image);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $image);

        $customizedImage = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerImageData'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop, 150, 20, 80);

        $this->assertIsString($customizedImage);
        $this->assertNotSame($image, $customizedImage);

        $rightAlignedImage = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerImageData'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop, 100, 100, 100);

        $this->assertIsString($rightAlignedImage);
        $this->assertNotSame($image, $rightAlignedImage);
    }

    public function test_flyer_description_replaces_line_breaks_and_removes_emoji(): void
    {
        $admin = $this->makeAdmin();
        $workshop = $this->makeWorkshop($admin, 'Robotics Lab', now()->addWeek());
        $workshop->summary = null;
        $workshop->content = "<p>Build a robot</p>\n<div>Then test it 🚀</div><br>Bring ideas 🤖</br>";

        $description = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerDescription'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop);

        $this->assertSame('Build a robot Then test it Bring ideas', $description);

        $customDescription = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerDescription'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop, "A custom line\nwith an emoji 🚀");

        $this->assertSame('A custom line with an emoji', $customDescription);
    }

    public function test_flyer_rejects_image_customization_outside_supported_bounds(): void
    {
        $admin = $this->makeAdmin();
        $workshop = $this->makeWorkshop($admin, 'Robotics Lab', now()->addWeek());

        $this->actingAs($admin)
            ->from(route('admin.workshop-flyer.create'))
            ->post(route('admin.workshop-flyer.generate'), [
                'workshop_ids' => [(string) $workshop->id],
                'footer' => 'Book now',
                'customizations' => [
                    (string) $workshop->id => [
                        'description' => 'Custom copy',
                        'image_zoom' => 250,
                        'image_x' => -1,
                        'image_y' => 101,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.workshop-flyer.create'))
            ->assertSessionHasErrors([
                'customizations.'.$workshop->id.'.image_zoom',
                'customizations.'.$workshop->id.'.image_x',
                'customizations.'.$workshop->id.'.image_y',
            ]);
    }

    public function test_flyer_description_prefers_a_complete_sentence_within_its_larger_limit(): void
    {
        $admin = $this->makeAdmin();
        $workshop = $this->makeWorkshop($admin, 'Nightfall', now()->addWeek());
        $workshop->summary = 'Ready to survive the night? Join us on Saturday from 3:00–4:00 pm for an hour of Nightfall on the STEMCraft server! This time, everyone is on the same team. Work together to explore, build and survive the night.';

        $description = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerDescription'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop);

        $this->assertSame($workshop->summary, $description);

        $workshop->summary .= ' This additional sentence should fall beyond the flyer description limit.';
        $description = (new ReflectionMethod(WorkshopPromotionalFlyerController::class, 'flyerDescription'))
            ->invoke(app(WorkshopPromotionalFlyerController::class), $workshop);

        $this->assertSame('Ready to survive the night? Join us on Saturday from 3:00–4:00 pm for an hour of Nightfall on the STEMCraft server! This time, everyone is on the same team. Work together to explore, build and survive the night.', $description);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        Media::query()->create([
            'name' => 'stemmechanics-logo.png',
            'title' => 'STEMMechanics logo',
            'hash' => hash('sha256', 'stemmechanics-logo.png'),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);

        return $admin;
    }

    private function makeWorkshop(User $admin, string $title, $startsAt): Workshop
    {
        return Workshop::query()->create([
            'title' => $title,
            'content' => '<p>A hands-on workshop packed with creative STEM challenges.</p>',
            'summary' => 'A hands-on workshop packed with creative STEM challenges.',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'status' => 'open',
            'type' => Workshop::TYPE_ONLINE,
            'registration' => 'tickets',
            'price' => '35.00',
            'user_id' => $admin->id,
            'hero_media_name' => 'stemmechanics-logo.png',
        ]);
    }
}
