<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMediaUnusedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_media_unused_filter_hides_referenced_media_and_shows_unused_media(): void
    {
        Storage::fake('media');

        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);
        $location = Location::factory()->create([
            'name' => 'Test Lab',
        ]);

        $heroMedia = $this->createMedia('workshop-hero.png', 'Workshop Hero', 'image/png', $admin->id);
        $contentMedia = $this->createMedia('content-image.png', 'Content Image', 'image/png', $admin->id);
        $attachedMedia = $this->createMedia('attached-file.pdf', 'Attached File', 'application/pdf', $admin->id);
        $unusedMedia = $this->createMedia('unused-image.png', 'Unused Image', 'image/png', $admin->id);

        $workshop = Workshop::query()->create([
            'title' => 'Media Usage Workshop',
            'content' => '<p>Workshop body</p><p><img src="/media/download/'.$contentMedia->name.'" alt="content"></p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $heroMedia->name,
        ]);

        $workshop->files()->attach($attachedMedia->name, ['collection' => null]);

        $response = $this->actingAs($admin)->get(route('admin.media.index', ['unused_only' => 1]));

        $response->assertOk();
        $response->assertSee('Showing media with no detected site references.');
        $response->assertSeeText('Unused Image');
        $response->assertDontSeeText('Workshop Hero');
        $response->assertDontSeeText('Content Image');
        $response->assertDontSeeText('Attached File');

        $this->actingAs($admin)
            ->get(route('admin.media.edit', $attachedMedia))
            ->assertOk()
            ->assertSee('Workshop file')
            ->assertSee('Media Usage Workshop')
            ->assertSee($workshop->starts_at->format('j M Y'))
            ->assertSee($location->name);

        $this->actingAs($admin)
            ->get(route('admin.media.edit', $heroMedia))
            ->assertOk()
            ->assertSeeText('Workshop hero')
            ->assertSeeText('Media Usage Workshop')
            ->assertSeeText($workshop->starts_at->format('j M Y'))
            ->assertSeeText($location->name);

        $this->actingAs($admin)
            ->put(route('admin.media.update', $heroMedia), [
                'title' => $heroMedia->title,
                'visibility' => 'public',
                'storage_disk' => 'media',
                'workshop_links' => [[
                    'workshop_id' => (string) $workshop->id,
                    'type' => 'photo',
                ]],
            ])
            ->assertRedirect(route('admin.media.edit', $heroMedia));

        $this->assertDatabaseHas('mediables', [
            'media_name' => $heroMedia->name,
            'mediable_id' => $workshop->id,
            'mediable_type' => Workshop::class,
            'collection' => 'workshop_photos',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.media.update', $heroMedia), [
                'title' => $heroMedia->title,
                'visibility' => 'public',
                'storage_disk' => 'media',
                'workshop_links' => [[
                    'workshop_id' => (string) $workshop->id,
                    'type' => 'file',
                ]],
            ])
            ->assertRedirect(route('admin.media.edit', $heroMedia));

        $this->assertDatabaseHas('mediables', [
            'media_name' => $heroMedia->name,
            'mediable_id' => $workshop->id,
            'mediable_type' => Workshop::class,
            'collection' => null,
        ]);
        $this->assertDatabaseMissing('mediables', [
            'media_name' => $heroMedia->name,
            'mediable_id' => $workshop->id,
            'mediable_type' => Workshop::class,
            'collection' => 'workshop_photos',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.media.update', $heroMedia), [
                'title' => $heroMedia->title,
                'visibility' => 'public',
                'storage_disk' => 'media',
                'workshop_links' => [],
            ])
            ->assertRedirect(route('admin.media.edit', $heroMedia));

        $this->assertDatabaseMissing('mediables', [
            'media_name' => $heroMedia->name,
            'mediable_id' => $workshop->id,
            'mediable_type' => Workshop::class,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.media.edit', $attachedMedia))
            ->put(route('admin.media.update', $attachedMedia), [
                'title' => $attachedMedia->title,
                'visibility' => 'public',
                'storage_disk' => 'media',
                'workshop_links' => [[
                    'workshop_id' => (string) $workshop->id,
                    'type' => 'photo',
                ]],
            ])
            ->assertRedirect(route('admin.media.edit', $attachedMedia))
            ->assertSessionHasErrors('workshop_links');
    }

    private function createMedia(string $name, string $title, string $mimeType, string|int $userId): Media
    {
        $media = Media::query()->create([
            'name' => $name,
            'title' => $title,
            'hash' => Str::random(64),
            'mime_type' => $mimeType,
            'size' => 1024,
            'user_id' => (string) $userId,
        ]);

        Storage::disk('media')->put($media->hash, $title);

        return $media;
    }
}
