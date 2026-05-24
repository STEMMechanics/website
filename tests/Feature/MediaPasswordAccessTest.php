<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPasswordAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_media_password_prompt_unlocks_via_post_without_password_in_url(): void
    {
        Storage::fake('media');

        $media = Media::query()->create([
            'name' => 'private-archive.zip',
            'title' => 'Private Archive',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'application/zip',
            'size' => 1234,
            'user_id' => User::factory()->create()->id,
            'password' => password_hash('secret1234', PASSWORD_DEFAULT),
        ]);

        Storage::disk('media')->put($media->hash, 'archive-bytes');

        $response = $this->get(route('media.download', $media));

        $response->assertOk();
        $response->assertSee(route('media.download.unlock', $media), false);
        $response->assertDontSee('?password=');
        $response->assertDontSee('searchParams.set');

        $unlock = $this->post(route('media.download.unlock', $media), [
            'password' => 'secret1234',
        ]);

        $unlock->assertRedirect(route('media.download', $media));

        $this->get(route('media.download', $media))
            ->assertOk();
    }

    public function test_private_media_password_prompt_accepts_raw_password_query_strings_for_compatibility(): void
    {
        Storage::fake('media');

        $media = Media::query()->create([
            'name' => 'private-archive.zip',
            'title' => 'Private Archive',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'application/zip',
            'size' => 1234,
            'user_id' => User::factory()->create()->id,
            'password' => password_hash('secret1234', PASSWORD_DEFAULT),
        ]);

        Storage::disk('media')->put($media->hash, 'archive-bytes');

        $response = $this->get(route('media.download', ['media' => $media, 'password' => 'secret1234']));

        $response->assertRedirect(route('media.download', $media));

        $this->get(route('media.download', $media))
            ->assertOk();
    }

    public function test_private_media_password_prompt_still_accepts_legacy_base64_password_query_strings(): void
    {
        Storage::fake('media');

        $media = Media::query()->create([
            'name' => 'private-archive.zip',
            'title' => 'Private Archive',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'application/zip',
            'size' => 1234,
            'user_id' => User::factory()->create()->id,
            'password' => password_hash('secret1234', PASSWORD_DEFAULT),
        ]);

        Storage::disk('media')->put($media->hash, 'archive-bytes');

        $response = $this->get(route('media.download', ['media' => $media, 'password' => base64_encode('secret1234')]));

        $response->assertRedirect(route('media.download', $media));

        $this->get(route('media.download', $media))
            ->assertOk();
    }
}
