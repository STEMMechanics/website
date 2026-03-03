<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_TOKEN = 'test-token';

    public function test_account_media_page_lists_owned_media(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'owned-image.png',
            'title' => 'Owned Image',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $user->id,
        ]);

        Storage::disk('media')->put($media->hash, 'image-bytes');

        $this->actingAs($user)
            ->get(route('account.media.index'))
            ->assertOk()
            ->assertSee('My Media')
            ->assertSee('Owned Image');
    }

    public function test_user_can_delete_their_own_media_from_account_area(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'delete-me.png',
            'title' => 'Delete Me',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 2048,
            'user_id' => $user->id,
        ]);

        Storage::disk('media')->put($media->hash, 'image-bytes');

        $this->actingAs($user)
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->deleteJson(route('account.media.destroy', $media), [], ['X-CSRF-TOKEN' => self::CSRF_TOKEN])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('media', [
            'name' => $media->name,
        ]);
    }

    public function test_user_cannot_delete_another_users_media_from_account_area(): void
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'protected.png',
            'title' => 'Protected',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 512,
            'user_id' => $owner->id,
        ]);

        Storage::disk('media')->put($media->hash, 'image-bytes');

        $this->actingAs($otherUser)
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->deleteJson(route('account.media.destroy', $media), [], ['X-CSRF-TOKEN' => self::CSRF_TOKEN])
            ->assertForbidden();

        $this->assertDatabaseHas('media', [
            'name' => $media->name,
        ]);
    }

    public function test_admin_my_media_page_only_lists_admin_owned_media(): void
    {
        Storage::fake('media');

        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);
        $otherUser = User::factory()->create();

        $adminMedia = Media::query()->create([
            'name' => 'admin-owned.png',
            'title' => 'Admin Owned',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 900,
            'user_id' => $admin->id,
        ]);
        $otherMedia = Media::query()->create([
            'name' => 'other-owned.png',
            'title' => 'Other Owned',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'image/png',
            'size' => 901,
            'user_id' => $otherUser->id,
        ]);

        Storage::disk('media')->put($adminMedia->hash, 'admin');
        Storage::disk('media')->put($otherMedia->hash, 'other');

        $this->actingAs($admin)
            ->get(route('account.media.index'))
            ->assertOk()
            ->assertSee('Admin Owned')
            ->assertDontSee('Other Owned');
    }
}
