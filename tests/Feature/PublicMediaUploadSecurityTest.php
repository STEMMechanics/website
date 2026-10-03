<?php

namespace Tests\Feature;

use App\Jobs\Media\GenerateVariants;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_can_upload_a_public_raster_image_that_is_reencoded_and_shared(): void
    {
        Storage::fake('media');
        Queue::fake([GenerateVariants::class]);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('workshop-photo.jpg', 80, 60);

        $this->actingAs($user)
            ->postJson(route('media.store'), [
                'title' => 'Workshop photo',
                'visibility' => 'public',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('mime_type', 'image/jpeg');

        $media = Media::query()->where('title', 'Workshop photo')->firstOrFail();
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertTrue(Storage::disk('media')->exists((string) $media->hash));

        $this->get(route('media.download', $media))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_non_admin_cannot_make_active_content_public(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('not-an-image.jpg', '<!doctype html><script>alert(1)</script>');

        $this->actingAs($user)
            ->postJson(route('media.store'), [
                'title' => 'Rejected upload',
                'visibility' => 'public',
                'file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.file', 'Public uploads must be a valid JPG, PNG, or WebP image.');

        $this->assertDatabaseMissing('media', ['title' => 'Rejected upload']);
    }

    public function test_non_admin_can_only_attach_uploaded_photos_to_an_authorized_workshop(): void
    {
        Storage::fake('media');
        Queue::fake([GenerateVariants::class]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $location = Location::factory()->create();
        $hero = Media::query()->create([
            'name' => 'test-hero.png',
            'title' => 'Test hero',
            'hash' => str_repeat('h', 64),
            'mime_type' => 'image/png',
            'size' => 1,
            'user_id' => $user->id,
        ]);
        $ownedWorkshop = Workshop::factory()->create([
            'location_id' => $location->id,
            'user_id' => $user->id,
            'hero_media_name' => $hero->name,
        ]);
        $otherWorkshop = Workshop::factory()->create([
            'location_id' => $location->id,
            'user_id' => $otherUser->id,
            'hero_media_name' => $hero->name,
        ]);

        $file = UploadedFile::fake()->image('shared-photo.png', 80, 60);

        $this->actingAs($user)
            ->postJson(route('media.store'), [
                'title' => 'Shared workshop photo',
                'visibility' => 'public',
                'file' => $file,
                'workshop_links' => [
                    ['workshop_id' => (string) $ownedWorkshop->id, 'type' => 'photo'],
                    ['workshop_id' => (string) $otherWorkshop->id, 'type' => 'photo'],
                ],
            ])
            ->assertOk();

        $media = Media::query()->where('title', 'Shared workshop photo')->firstOrFail();
        $this->assertTrue($ownedWorkshop->photos()->where('media.name', $media->name)->exists());
        $this->assertFalse($otherWorkshop->photos()->where('media.name', $media->name)->exists());
    }

    public function test_abandoned_upload_cleanup_command_removes_old_chunk_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chunk-security-');
        if ($path === false) {
            self::fail('Could not create a temporary chunk file for the test.');
        }

        touch($path, now()->subHours(3)->getTimestamp());

        $this->artisan('media:cleanup-upload-temp', ['--minutes' => 120])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($path);
    }
}
