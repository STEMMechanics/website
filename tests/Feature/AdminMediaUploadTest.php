<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSeeText('Drop multiple files here to create media items with default titles and your admin account as the owner.')
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
            ->assertSeeText('Select File');
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
