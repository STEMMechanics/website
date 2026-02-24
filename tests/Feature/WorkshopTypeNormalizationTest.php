<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
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

