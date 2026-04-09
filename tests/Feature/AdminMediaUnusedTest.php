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
        $adminAvatar = $this->createMedia('admin-avatar.png', 'Admin Avatar', 'image/png', $admin->id);
        $admin->avatar_media_name = $adminAvatar->name;
        $admin->save();

        $location = Location::factory()->create();

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

        $workshop->files()->attach($attachedMedia->name, ['collection' => 'gallery']);

        $response = $this->actingAs($admin)->get(route('admin.media.index', ['unused_only' => 1]));

        $response->assertOk();
        $response->assertSee('Showing media with no detected site references.');
        $response->assertSeeText('Unused Image');
        $response->assertDontSeeText('Workshop Hero');
        $response->assertDontSeeText('Content Image');
        $response->assertDontSeeText('Attached File');
        $response->assertDontSeeText('Admin Avatar');
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
