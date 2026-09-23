<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDownloadTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_downloads_are_recorded_but_inline_requests_are_not(): void
    {
        Storage::fake('media');
        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'cairns-minecraft-guide.pdf',
            'title' => 'Cairns Minecraft Guide',
            'hash' => str_repeat('f', 64),
            'mime_type' => 'application/pdf',
            'size' => 12,
            'user_id' => $owner->id,
        ]);
        Storage::disk('media')->put($media->hash, 'guide-bytes');

        $this->actingAs($owner)->get(route('media.download', $media));
        $this->assertDatabaseCount('media_downloads', 0);

        $response = $this->actingAs($owner)->get(route('media.download', ['media' => $media, 'download' => 1]));

        $response->assertOk();
        $this->assertDatabaseHas('media_downloads', [
            'media_name' => $media->name,
            'user_id' => $owner->id,
            'variant' => null,
            'source' => 'public',
        ]);
        $this->assertSame(1, $media->downloads()->count());
    }

    public function test_media_item_and_report_show_recorded_downloads(): void
    {
        $admin = User::factory()->create();
        \App\Models\UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $media = Media::query()->create([
            'name' => 'cairns-minecraft-checklist.pdf',
            'title' => 'Cairns Minecraft Checklist',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'application/pdf',
            'size' => 12,
            'user_id' => $admin->id,
        ]);
        MediaDownload::query()->create(['media_name' => $media->name, 'user_id' => $admin->id, 'source' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.media.edit', $media))
            ->assertOk()
            ->assertSee('Downloads')
            ->assertSee('Cairns Minecraft Checklist');

        $this->actingAs($admin)
            ->get(route('admin.media.downloads', ['limit' => 100]))
            ->assertOk()
            ->assertSee('Cairns Minecraft Checklist')
            ->assertSee('Top 100');
    }
}
