<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivateWorkshopMediaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_or_download_private_workshop_media(): void
    {
        [$media] = $this->createPrivateWorkshopMedia();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('media.show', $media))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('media.download', $media))
            ->assertForbidden();
    }

    public function test_admin_can_view_and_download_private_workshop_media(): void
    {
        [$media] = $this->createPrivateWorkshopMedia();
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->getJson(route('media.show', $media))
            ->assertOk()
            ->assertJsonPath('name', $media->name);

        $this->actingAs($admin)
            ->get(route('media.download', $media))
            ->assertOk();
    }

    public function test_non_admin_media_index_excludes_private_workshop_media(): void
    {
        [$privateMedia] = $this->createPrivateWorkshopMedia();
        $publicOwner = User::factory()->create();
        $publicMedia = Media::query()->create([
            'name' => 'public-'.Str::lower(Str::random(8)).'.txt',
            'title' => 'Public File',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'text/plain',
            'size' => 9,
            'user_id' => $publicOwner->id,
        ]);

        Storage::disk('media')->put($publicMedia->hash, 'public');

        $response = $this->getJson(route('media.index'));

        $response->assertOk();
        $response->assertJsonFragment(['name' => $publicMedia->name]);
        $response->assertJsonMissing(['name' => $privateMedia->name]);
    }

    /**
     * @return array{0: Media, 1: Workshop}
     */
    private function createPrivateWorkshopMedia(): array
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $location = Location::factory()->create();

        $heroMedia = Media::query()->create([
            'name' => 'hero-'.Str::lower(Str::random(8)).'.png',
            'title' => 'Hero Image',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        $media = Media::query()->create([
            'name' => 'private-'.Str::lower(Str::random(8)).'.txt',
            'title' => 'Private Admin File',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'text/plain',
            'size' => 12,
            'user_id' => $owner->id,
        ]);

        Storage::disk('media')->put($media->hash, 'private file');

        $workshop = Workshop::query()->create([
            'title' => 'Private Media Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroMedia->name,
        ]);

        DB::table('mediables')->insert([
            'media_name' => $media->name,
            'mediable_id' => (string) $workshop->id,
            'mediable_type' => Workshop::class,
            'collection' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$media, $workshop];
    }
}

