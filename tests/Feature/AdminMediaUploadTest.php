<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Jobs\Media\GenerateVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_media_index_exposes_bulk_upload_dropzone(): void
    {
        $admin = $this->makeAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.media.index'))
            ->assertOk()
            ->assertSeeText('Quick upload')
            ->assertSeeText('Drop multiple files here to create media items with default values and your account as the owner.')
            ->assertSee('admin-media-bulk-upload-input', false)
            ->assertSee('admin-media-bulk-upload-status-bar', false)
            ->assertSee('multiple', false);
    }

    public function test_admin_media_create_page_supports_drag_and_drop_on_file_field(): void
    {
        $admin = $this->makeAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.media.create'))
            ->assertOk()
            ->assertSee('file_dropzone', false)
            ->assertSee('file_state_progress_bar', false)
            ->assertSeeText('Drop a file here or click Browse files')
            ->assertSeeText('Browse files');
    }

    public function test_admin_media_store_creates_a_record_when_title_is_present(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $file = UploadedFile::fake()->image('bulk-upload-example.png', 120, 80);

        $this->actingAs($admin)
            ->postJson(route('admin.media.store'), [
                'title' => 'Bulk Upload Example',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'bulk-upload-example.png');

        $this->assertDatabaseHas('media', [
            'title' => 'Bulk Upload Example',
            'name' => 'bulk-upload-example.png',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_media_store_reuses_an_existing_exact_duplicate(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $file = UploadedFile::fake()->image('duplicate-upload.png', 120, 80);
        $hash = hash_file('sha256', $file->path());
        Media::query()->create([
            'name' => 'existing-image.png',
            'title' => 'Existing Image',
            'hash' => $hash,
            'mime_type' => 'image/png',
            'size' => $file->getSize(),
            'user_id' => $admin->id,
            'storage_disk' => 'media',
        ]);
        Storage::disk('media')->put($hash, $file->getContent());

        $this->actingAs($admin)
            ->postJson(route('admin.media.store'), [
                'title' => 'Duplicate Upload',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'existing-image.png')
            ->assertJsonPath('reused', true);

        $this->assertDatabaseCount('media', 1);
    }

    public function test_workshop_media_upload_accepts_mov_video_files(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $location = Location::factory()->create();
        Media::query()->create([
            'name' => 'stemmechanics-logo.png',
            'title' => 'STEMMechanics Logo',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create([
            'location_id' => $location->id,
            'user_id' => $admin->id,
        ]);
        $file = UploadedFile::fake()->create('IMG_0131.MOV', 1024, 'video/quicktime');

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.photos.store', $workshop), [
                'photos' => [$file],
                'photos_meta' => [[
                    'title' => 'Workshop Clip',
                    'visibility' => 'private',
                    'photographed_at' => now()->toDateString(),
                    'tags' => 'video',
                    'caption' => 'Test clip',
                    'consent_notes' => '',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('media', [
            'name' => 'img-0131.MOV',
            'mime_type' => 'video/quicktime',
            'user_id' => $admin->id,
        ]);
    }

    public function test_workshop_files_and_photos_use_the_shared_existing_media_uploader(): void
    {
        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create([
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
        ]);
        $workshop->photos()->attach('stemmechanics-logo.png', [
            'collection' => 'workshop_photos',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.files', $workshop))
            ->assertOk()
            ->assertSeeText('Select Local Files')
            ->assertSeeText('Browse Existing Media')
            ->assertSee('workshop_files_pending', false);

        $this->actingAs($admin)
            ->get(route('admin.workshop.photos', $workshop))
            ->assertOk()
            ->assertSeeText('Select Local Files')
            ->assertSeeText('Browse Existing Media')
            ->assertViewHas('attachedPhotoNames', ['stemmechanics-logo.png'])
            ->assertSee('x-on:change="appendFiles($event.target.files)"', false)
            ->assertSee("require_mime_type: 'image/*,video/*'", false);
    }

    public function test_existing_image_can_be_added_to_workshop_photos(): void
    {
        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create([
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
        ]);
        $media = Media::query()->create([
            'name' => 'existing-workshop-photo.jpg',
            'title' => 'Existing Workshop Photo',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.photos.store', $workshop), [
                'existing_media_names' => [$media->name],
            ])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('attached', 1);

        $this->assertDatabaseHas('mediables', [
            'media_name' => $media->name,
            'mediable_id' => $workshop->id,
            'mediable_type' => Workshop::class,
            'collection' => 'workshop_photos',
        ]);
    }

    public function test_non_visual_existing_media_cannot_be_added_to_workshop_photos(): void
    {
        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create([
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
        ]);
        $media = Media::query()->create([
            'name' => 'coordinator-handbook.pdf',
            'title' => 'Coordinator Handbook',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.photos.store', $workshop), [
                'existing_media_names' => [$media->name],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('existing_media_names');

        $this->assertDatabaseMissing('mediables', [
            'media_name' => $media->name,
            'mediable_id' => $workshop->id,
            'collection' => 'workshop_photos',
        ]);
    }

    public function test_workshop_media_upload_applies_image_edits_before_storing(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $location = Location::factory()->create();
        Media::query()->create([
            'name' => 'stemmechanics-logo.png',
            'title' => 'STEMMechanics Logo',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create([
            'location_id' => $location->id,
            'user_id' => $admin->id,
        ]);
        $file = UploadedFile::fake()->image('edited-still.png', 100, 80);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.photos.store', $workshop), [
                'photos' => [$file],
                'photos_meta' => [[
                    'title' => 'Edited Still',
                    'visibility' => 'private',
                    'photographed_at' => now()->toDateString(),
                    'edit_rotation' => 90,
                    'edit_crop_left' => 10,
                    'edit_crop_right' => 10,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('created', 1);

        $media = Media::query()->where('title', 'Edited Still')->firstOrFail();
        [$width, $height] = getimagesize(Storage::disk('media')->path((string) $media->hash));

        $this->assertSame(64, $width);
        $this->assertSame(100, $height);
    }

    public function test_admin_media_update_applies_image_edits_and_replaces_original_hash(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $source = UploadedFile::fake()->image('source.png', 120, 60);
        $oldHash = hash_file('sha256', $source->path());
        $source->storeAs('/', $oldHash, 'media');

        $media = Media::query()->create([
            'name' => 'source.png',
            'title' => 'Source',
            'hash' => $oldHash,
            'mime_type' => 'image/png',
            'size' => $source->getSize(),
            'user_id' => $admin->id,
            'variants' => [
                'thumbnail' => ['mime_type' => 'image/webp', 'extension' => 'webp'],
            ],
        ]);
        Storage::disk('media')->put($oldHash.'-thumbnail', 'fake-variant');

        $this->actingAs($admin)
            ->put(route('admin.media.update', $media), [
                'title' => 'Source',
                'visibility' => 'private',
                'edit_rotation' => 90,
                'edit_crop_top' => 10,
                'edit_crop_bottom' => 10,
            ])
            ->assertRedirect(route('admin.media.edit', $media));

        $media->refresh();
        $this->assertNotSame($oldHash, $media->hash);
        $this->assertFalse(Storage::disk('media')->exists($oldHash));

        [$width, $height] = getimagesize(Storage::disk('media')->path((string) $media->hash));

        $this->assertSame(60, $width);
        $this->assertSame(96, $height);
    }

    public function test_admin_media_store_persists_a_password_on_create(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $file = UploadedFile::fake()->create('protected-archive.zip', 12, 'application/zip');

        $this->actingAs($admin)
            ->postJson(route('admin.media.store'), [
                'title' => 'Protected Archive',
                'password_password' => 'secret1234',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'protected-archive.zip');

        $media = Media::query()->findOrFail('protected-archive.zip');

        $this->assertNotNull($media->password);
        $this->assertTrue(Hash::check('secret1234', (string) $media->password));
    }

    public function test_admin_media_update_persists_password_from_form_field_name(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $media = Media::query()->create([
            'name' => 'update-archive.zip',
            'title' => 'Update Archive',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'application/zip',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);

        Storage::disk('media')->put($media->hash, 'zip-bytes');

        $this->actingAs($admin)
            ->put(route('admin.media.update', $media), [
                'title' => 'Update Archive',
                'password_password' => 'secret1234',
            ])
            ->assertRedirect(route('admin.media.edit', $media));

        $media->refresh();

        $this->assertNotNull($media->password);
        $this->assertTrue(Hash::check('secret1234', (string) $media->password));
    }

    public function test_zip_media_processing_skips_variant_generation_without_error(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $media = Media::query()->create([
            'name' => 'archive.zip',
            'title' => 'Archive',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'application/zip',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);

        Storage::disk('media')->put($media->hash, 'zip-bytes');

        (new GenerateVariants($media, true))->handle();

        $media->refresh();

        $this->assertSame('ready', $media->status);
        $this->assertNull($media->last_processing_error);
        $this->assertNull($media->last_processing_failed_at);
    }

    private function makeAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    private function makeDefaultWorkshopHero(User $admin): void
    {
        Media::query()->firstOrCreate([
            'name' => 'stemmechanics-logo.png',
        ], [
            'title' => 'STEMMechanics Logo',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
    }
}
