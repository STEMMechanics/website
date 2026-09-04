<?php

namespace Tests\Feature;

use App\Helpers;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Jobs\Media\GenerateVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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

    public function test_admin_media_index_defaults_to_table_and_offers_photo_view_toggle(): void
    {
        $admin = $this->makeAdminUser();
        Media::query()->create([
            'name' => 'operation-table-photo.jpg',
            'title' => 'Operation Table Photo',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.media.index', ['search' => 'Operation']))
            ->assertOk()
            ->assertSee('aria-label="Photo view"', false)
            ->assertSee('fa-solid fa-table-cells-large', false)
            ->assertSee(route('admin.media.index', [
                'search' => 'Operation',
                'view' => 'photos',
            ]))
            ->assertSee('<table', false)
            ->assertDontSee('name="view" value="photos"', false);
    }

    public function test_admin_media_photo_view_renders_thumbnail_cards_and_preserves_view_when_filtering(): void
    {
        Storage::fake('media');

        $admin = $this->makeAdminUser();
        $media = Media::query()->create([
            'name' => 'operation-photo.jpg',
            'title' => 'Operation Workshop Photo',
            'hash' => str_repeat('f', 64),
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'user_id' => $admin->id,
        ]);
        $media->variants = [
            'thumbnail' => ['mime_type' => 'image/webp', 'extension' => 'webp'],
            'md' => ['mime_type' => 'image/webp', 'extension' => 'webp'],
        ];
        $media->save();
        Storage::disk('media')->put($media->hash.'-thumbnail', 'thumbnail-image');
        Storage::disk('media')->put($media->hash.'-md', 'medium-image');

        $this->actingAs($admin)
            ->get(route('admin.media.index', ['view' => 'photos']))
            ->assertOk()
            ->assertSeeText('Operation Workshop Photo')
            ->assertSee('aria-label="Table view"', false)
            ->assertSee('fa-solid fa-table-list', false)
            ->assertSee('admin-media-select-photos-', false)
            ->assertSee('class="flex aspect-square', false)
            ->assertSee($media->url('md', true), false)
            ->assertDontSee($media->url('thumbnail', true), false)
            ->assertSee('name="view" value="photos"', false)
            ->assertDontSee('<table', false);
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
        Queue::fake([GenerateVariants::class]);

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

    public function test_workshop_media_upload_reuses_an_exact_duplicate_across_workshops(): void
    {
        Storage::fake('media');
        Queue::fake([GenerateVariants::class]);

        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $location = Location::factory()->create();
        $sourceWorkshop = Workshop::factory()->create([
            'location_id' => $location->id,
            'user_id' => $admin->id,
        ]);
        $targetWorkshop = Workshop::factory()->create([
            'location_id' => $location->id,
            'user_id' => $admin->id,
        ]);
        $originalUpload = UploadedFile::fake()->image('existing-stopmotion.png', 120, 80);
        $fileContents = $originalUpload->getContent();
        $hash = hash_file('sha256', $originalUpload->path());
        $existingMedia = Media::query()->create([
            'name' => 'existing-stopmotion.png',
            'title' => 'Existing Stopmotion',
            'hash' => $hash,
            'mime_type' => 'image/png',
            'size' => $originalUpload->getSize(),
            'user_id' => $admin->id,
            'storage_disk' => 'media',
            'visibility' => 'private',
            'tags' => 'existing-tag',
        ]);
        $sourceWorkshop->photos()->attach($existingMedia->name, ['collection' => 'workshop_photos']);
        $initialMediaCount = Media::query()->count();

        $metadata = [[
            'title' => 'Replacement title',
            'visibility' => 'public',
            'storage_disk' => 'archive',
            'photographed_at' => now()->toDateString(),
            'tags' => 'replacement-tag',
            'caption' => '',
            'consent_notes' => '',
        ]];

        $retryUpload = UploadedFile::fake()->createWithContent('retried-stopmotion.png', $fileContents);
        $this->assertSame($hash, hash_file('sha256', $retryUpload->path()));

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.photos.store', $targetWorkshop), [
                'photos' => [$retryUpload],
                'photos_meta' => $metadata,
            ])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('attached', 1)
            ->assertJsonPath('reused', 1)
            ->assertJsonPath('already_attached', 0);

        $this->assertDatabaseCount('media', $initialMediaCount);
        $this->assertTrue($targetWorkshop->photos()->where('media.name', $existingMedia->name)->exists());
        $this->assertDatabaseHas('media', [
            'name' => $existingMedia->name,
            'title' => 'Existing Stopmotion',
            'visibility' => 'private',
            'storage_disk' => 'media',
            'tags' => 'existing-tag',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.photos.store', $targetWorkshop), [
                'photos' => [UploadedFile::fake()->createWithContent('retried-again-stopmotion.png', $fileContents)],
                'photos_meta' => $metadata,
            ])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('attached', 0)
            ->assertJsonPath('reused', 0)
            ->assertJsonPath('already_attached', 1);

        $this->assertDatabaseCount('media', $initialMediaCount);
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
            ->assertSeeText('All public files are displayed on the workshop page.')
            ->assertSeeText('Select Local Files')
            ->assertSeeText('Browse Existing Media')
            ->assertDontSeeText('Selected Files & Metadata')
            ->assertDontSeeText('Add Media')
            ->assertSee('Uploading files', false)
            ->assertSee('workshop_files_pending', false);

        $this->actingAs($admin)
            ->get(route('admin.workshop.photos', $workshop))
            ->assertOk()
            ->assertSeeText('Photos are not displayed on the workshop page.')
            ->assertSeeText('Select Local Files')
            ->assertSeeText('Browse Existing Media')
            ->assertDontSeeText('Selected Media & Metadata')
            ->assertViewHas('attachedPhotoNames', ['stemmechanics-logo.png'])
            ->assertSeeText('Original Name')
            ->assertSee('value="stemmechanics-logo.png"', false)
            ->assertSee('x-on:change="appendFiles($event.target.files)"', false)
            ->assertSeeText('Select')
            ->assertSeeText('Actions')
            ->assertSee("variant=thumbnail", false)
            ->assertSee('class="hidden text-center lg:table-cell">Tags</th>', false)
            ->assertSee('class="hidden px-3 py-3 text-center text-gray-600 lg:table-cell"', false)
            ->assertSeeText('Hatched tags are currently present on only some selected items.')
            ->assertDontSeeText('Add Tags')
            ->assertDontSeeText('Remove Tags')
            ->assertSee('workshop-photos-progress-title', false)
            ->assertSee("require_mime_type: 'image/*,video/*'", false);
    }

    public function test_workshop_file_queue_uploads_and_attaches_one_file(): void
    {
        Storage::fake('archive');

        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create([
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
        ]);
        $file = UploadedFile::fake()->createWithContent('animation-project.sb3', 'scratch-project');

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.files.upload', $workshop), [
                'pending_files' => [$file],
                'pending_file_keys' => json_encode([17]),
                'pending_files_meta' => [
                    17 => [
                        'title' => 'Animation Project',
                        'visibility' => 'public',
                        'notes' => 'Student project file',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('file.title', 'Animation Project')
            ->assertJsonPath('file.visibility', 'public')
            ->assertJsonPath('file.storage_disk', 'archive');

        $this->assertDatabaseHas('media', [
            'title' => 'Animation Project',
            'visibility' => 'public',
            'consent_notes' => 'Student project file',
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id' => $workshop->id,
            'mediable_type' => Workshop::class,
            'collection' => null,
        ]);
    }

    public function test_workshop_file_upload_reuses_an_exact_duplicate(): void
    {
        Storage::fake('archive');

        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create([
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
        ]);
        $contents = 'the-same-large-archive-content';
        $hash = hash('sha256', $contents);
        $existing = Media::query()->create([
            'name' => 'original-project.zip',
            'title' => 'Original Project',
            'hash' => $hash,
            'mime_type' => 'application/zip',
            'size' => strlen($contents),
            'user_id' => $admin->id,
            'storage_disk' => 'archive',
        ]);
        Storage::disk('archive')->put($hash, $contents);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.files.upload', $workshop), [
                'pending_files' => [UploadedFile::fake()->createWithContent('retried-project.zip', $contents)],
                'pending_file_keys' => json_encode([17]),
                'pending_files_meta' => [17 => ['title' => 'Retried Project']],
            ])
            ->assertOk()
            ->assertJsonPath('file.name', $existing->name);

        $this->assertDatabaseCount('media', 2); // The duplicate plus the default workshop hero.
        $this->assertTrue($workshop->files()->where('media.name', $existing->name)->exists());
    }

    public function test_chunked_upload_can_be_finalized_as_a_workshop_file(): void
    {
        Storage::fake('archive');

        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create([
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
        ]);
        $contents = 'chunked-workshop-file';

        $chunkResponse = $this->actingAs($admin)->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->createWithContent('chunk', $contents),
            'filename' => 'large-project.zip',
            'filesize' => strlen($contents),
            'filestart' => 'true',
        ])->assertOk()->assertJsonStructure(['upload_token']);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.files.upload', $workshop), [
                'upload_token' => $chunkResponse->json('upload_token'),
                'filename' => 'large-project.zip',
                'pending_file_keys' => json_encode([17]),
                'pending_files_meta' => [17 => [
                    'title' => 'Large Project',
                    'visibility' => 'public',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('file.title', 'Large Project');

        $this->assertDatabaseHas('media', [
            'title' => 'Large Project',
            'hash' => hash('sha256', $contents),
            'storage_disk' => 'archive',
        ]);
    }

    public function test_mac_stopmotion_archive_suffix_is_not_included_in_generated_title(): void
    {
        $this->assertSame(
            '2022 09 I Am Spooder Dave',
            Helpers::filenameToTitle('2022-09-i-am-spooder-dave.stopmotion.zip')
        );
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

    public function test_attached_workshop_photo_can_be_moved_to_archive_storage(): void
    {
        Storage::fake('media');
        Storage::fake('archive');
        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create(['location_id' => Location::factory()->create()->id, 'user_id' => $admin->id]);
        $hash = str_repeat('9', 64);
        $media = Media::query()->create([
            'name' => 'storage-move.jpg', 'title' => 'Storage Move', 'hash' => $hash,
            'mime_type' => 'image/jpeg', 'size' => 12, 'user_id' => $admin->id, 'storage_disk' => 'media',
            'visibility' => 'private',
        ]);
        Storage::disk('media')->put($hash, 'photo-bytes');
        $workshop->photos()->attach($media->name, ['collection' => 'workshop_photos']);

        $this->actingAs($admin)->put(route('admin.workshop.photos.bulk-update', $workshop), [
            'photos' => [$media->name => [
                'title' => 'Storage Move', 'visibility' => 'private', 'storage_disk' => 'archive',
                'caption' => '', 'consent_notes' => '', 'tags' => '', 'photographed_at' => '',
            ]],
        ])->assertRedirect(route('admin.workshop.photos', $workshop));

        $this->assertDatabaseHas('media', ['name' => $media->name, 'storage_disk' => 'archive']);
        Storage::disk('archive')->assertExists($hash);
        Storage::disk('media')->assertMissing($hash);
    }

    public function test_attached_workshop_files_can_be_bulk_updated(): void
    {
        Storage::fake('media');
        Storage::fake('archive');
        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create(['location_id' => Location::factory()->create()->id, 'user_id' => $admin->id]);
        $hash = str_repeat('8', 64);
        $media = Media::query()->create([
            'name' => 'workshop-notes.pdf', 'title' => 'Workshop Notes', 'hash' => $hash,
            'mime_type' => 'application/pdf', 'size' => 12, 'user_id' => $admin->id,
            'storage_disk' => 'media', 'visibility' => 'private',
        ]);
        Storage::disk('media')->put($hash, 'file-bytes');
        $workshop->files()->attach($media->name, ['collection' => null]);

        $this->actingAs($admin)->putJson(route('admin.workshop.files.bulk-update', $workshop), [
            'media_names' => [$media->name],
            'storage_disk' => 'archive',
            'visibility' => 'protected',
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('media', [
            'name' => $media->name,
            'storage_disk' => 'archive',
            'visibility' => 'protected',
        ]);
        Storage::disk('archive')->assertExists($hash);
        Storage::disk('media')->assertMissing($hash);
    }

    public function test_existing_media_can_be_attached_to_workshop_files_without_replacing_existing_files(): void
    {
        $admin = $this->makeAdminUser();
        $this->makeDefaultWorkshopHero($admin);
        $workshop = Workshop::factory()->create(['location_id' => Location::factory()->create()->id, 'user_id' => $admin->id]);
        $existing = Media::query()->create([
            'name' => 'already-attached.pdf', 'title' => 'Already Attached', 'hash' => str_repeat('6', 64),
            'mime_type' => 'application/pdf', 'size' => 10, 'user_id' => $admin->id,
        ]);
        $added = Media::query()->create([
            'name' => 'newly-attached.pdf', 'title' => 'Newly Attached', 'hash' => str_repeat('7', 64),
            'mime_type' => 'application/pdf', 'size' => 10, 'user_id' => $admin->id,
        ]);
        $workshop->files()->attach($existing->name, ['collection' => null]);

        $this->actingAs($admin)->postJson(route('admin.workshop.files.attach', $workshop), [
            'media_names' => [$added->name],
        ])->assertOk()->assertJsonPath('attached', 1);

        $this->assertTrue($workshop->files()->where('media.name', $existing->name)->exists());
        $this->assertTrue($workshop->files()->where('media.name', $added->name)->exists());
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
