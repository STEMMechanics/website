<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaXSendfileDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_media_download_uses_x_sendfile_when_enabled(): void
    {
        Storage::fake('media');

        config(['media.use_x_sendfile' => true]);

        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'private-archive.zip',
            'title' => 'Private Archive',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'application/zip',
            'size' => 13,
            'user_id' => $owner->id,
        ]);

        Storage::disk('media')->put($media->hash, 'archive-bytes');

        $response = $this->actingAs($owner)->get(route('media.download', $media));

        $response->assertOk();
        $response->assertHeader('X-Sendfile', Storage::disk('media')->path($media->hash));
        self::assertStringContainsString(
            'private-archive.zip',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_admin_upload_cap_uses_configured_limit(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        $this->assertSame(PHP_INT_MAX, \App\Helpers::roleUploadCap($admin));
    }

    public function test_non_admin_upload_cap_uses_configured_limit(): void
    {
        $user = User::factory()->create();

        SiteOption::query()->updateOrCreate(
            ['name' => 'media.upload.non-admin-max-bytes'],
            ['value' => (string) (75 * 1024 * 1024)],
        );

        $this->assertSame(75 * 1024 * 1024, \App\Helpers::roleUploadCap($user));
    }
}
