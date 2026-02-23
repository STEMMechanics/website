<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaPublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_media_show_hides_sensitive_fields(): void
    {
        $owner = User::factory()->create();
        $media = Media::create([
            'name' => 'example.pdf',
            'title' => 'Example File',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'application/pdf',
            'size' => 12345,
            'user_id' => $owner->id,
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $response = $this->getJson(route('media.show', $media));

        $response->assertOk();
        $response->assertJsonPath('name', 'example.pdf');
        $response->assertJsonMissingPath('hash');
        $response->assertJsonMissingPath('variants');
        $response->assertJsonMissingPath('user_id');
    }

    public function test_public_media_index_hides_sensitive_fields(): void
    {
        $owner = User::factory()->create();
        Media::create([
            'name' => 'sample.txt',
            'title' => 'Sample',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'text/plain',
            'size' => 99,
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson(route('media.index'));

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.hash');
        $response->assertJsonMissingPath('data.0.user_id');
    }
}

