<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
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
            ->assertRedirect(route('admin.media.index'));

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
}
