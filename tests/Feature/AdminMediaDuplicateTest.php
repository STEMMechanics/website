<?php

namespace Tests\Feature;

use App\Jobs\Media\GeneratePerceptualHash;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminMediaDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_page_groups_exact_file_matches(): void
    {
        $admin = $this->createAdmin();
        $this->createMedia('first.png', 'First Image', $admin, str_repeat('a', 64));
        $second = $this->createMedia('second.png', 'Second Image', $admin, str_repeat('a', 64));
        $this->createMedia('unique.png', 'Unique Image', $admin, str_repeat('b', 64));
        $workshop = Workshop::query()->create([
            'title' => 'Workshop Using Duplicate',
            'content' => '<p>Content</p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
            'hero_media_name' => $second->name,
        ]);
        $workshop->photos()->attach($second->name, ['collection' => 'workshop_photos']);

        $this->actingAs($admin)
            ->get(route('admin.media.duplicates'))
            ->assertOk()
            ->assertSeeInOrder(['Second Image', 'First Image'])
            ->assertSeeText('Keep this record')
            ->assertSeeText('1024 bytes')
            ->assertDontSeeText('2 identical media records')
            ->assertDontSee('type="radio"', false)
            ->assertDontSeeText('Unique Image');
    }

    public function test_duplicate_page_has_a_compact_empty_similar_image_state(): void
    {
        $admin = $this->createAdmin();
        $this->createMedia('only.png', 'Only Image', $admin, str_repeat('a', 64));

        $this->actingAs($admin)
            ->get(route('admin.media.duplicates'))
            ->assertOk()
            ->assertSeeText('No similar images found.')
            ->assertSeeText('Show ignored matches')
            ->assertDontSeeText('Possible similar images')
            ->assertDontSeeText('0 of 1 images scanned');
    }

    public function test_merge_moves_references_to_keeper_before_removing_duplicate(): void
    {
        Storage::fake('media');

        $admin = $this->createAdmin();
        $hash = str_repeat('c', 64);
        $keeper = $this->createMedia('keeper.png', 'Keeper Image', $admin, $hash);
        $duplicate = $this->createMedia('duplicate.png', 'Duplicate Image', $admin, $hash);
        Storage::disk('media')->put($hash, 'identical-image');

        $workshop = Workshop::query()->create([
            'title' => 'Duplicate Media Workshop',
            'content' => '<p><img src="/media/download/'.$duplicate->name.'"></p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
            'hero_media_name' => $duplicate->name,
        ]);
        $workshop->photos()->attach($duplicate->name, ['collection' => 'workshop_photos']);
        $workshop->photos()->attach($keeper->name, ['collection' => 'workshop_photos']);

        $this->actingAs($admin)
            ->post(route('admin.media.duplicates.merge'), [
                'keeper' => $keeper->name,
                'members' => [$keeper->name, $duplicate->name],
            ])
            ->assertRedirect(route('admin.media.duplicates'));

        $this->assertDatabaseMissing('media', ['name' => $duplicate->name]);
        $this->assertDatabaseHas('media', ['name' => $keeper->name]);
        $this->assertDatabaseHas('workshops', [
            'id' => $workshop->id,
            'hero_media_name' => $keeper->name,
        ]);
        $this->assertDatabaseHas('mediables', [
            'media_name' => $keeper->name,
            'mediable_id' => $workshop->id,
            'collection' => 'workshop_photos',
        ]);
        $this->assertSame(1, $workshop->photos()->where('media.name', $keeper->name)->count());
        $this->assertStringContainsString($keeper->name, (string) $workshop->fresh()->content);
        Storage::disk('media')->assertExists($hash);
    }

    public function test_similar_image_suggestion_can_be_ignored_and_restored(): void
    {
        $admin = $this->createAdmin();
        $first = $this->createMedia('similar-one.png', 'Similar One', $admin, str_repeat('d', 64));
        $second = $this->createMedia('similar-two.jpg', 'Similar Two', $admin, str_repeat('e', 64));
        $first->update(['perceptual_hash' => '1234567890abcdef', 'perceptual_hash_scanned_at' => now()]);
        $second->update(['perceptual_hash' => '1234567890abcdee', 'perceptual_hash_scanned_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.media.duplicates'))
            ->assertOk()
            ->assertSeeText('98.4% visually similar')
            ->assertSeeText('Similar One')
            ->assertSeeText('Similar Two');
        $this->actingAs($admin)
            ->get(route('admin.media.index'))
            ->assertViewHas('duplicateAttentionCount', 1)
            ->assertSeeText('Find Duplicates');

        $this->actingAs($admin)
            ->post(route('admin.media.duplicates.ignore-similar'), ['first' => $first->name, 'second' => $second->name])
            ->assertRedirect(route('admin.media.duplicates'));

        $this->assertDatabaseHas('media_similarity_ignores', [
            'media_name_a' => $first->name,
            'media_name_b' => $second->name,
        ]);
        $this->actingAs($admin)
            ->get(route('admin.media.duplicates'))
            ->assertDontSeeText('98.4% visually similar');
        $this->actingAs($admin)
            ->get(route('admin.media.index'))
            ->assertViewHas('duplicateAttentionCount', 0);
        $this->actingAs($admin)
            ->get(route('admin.media.duplicates', ['show_ignored' => 1]))
            ->assertSeeText('98.4% visually similar');

        $this->actingAs($admin)
            ->delete(route('admin.media.duplicates.restore-similar'), ['first' => $first->name, 'second' => $second->name])
            ->assertRedirect(route('admin.media.duplicates', ['show_ignored' => 1]));
        $this->assertDatabaseMissing('media_similarity_ignores', ['media_name_a' => $first->name, 'media_name_b' => $second->name]);
    }

    public function test_similarity_scan_queues_unscanned_images(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $media = $this->createMedia('unscanned.png', 'Unscanned Image', $admin, str_repeat('f', 64));

        $this->actingAs($admin)
            ->post(route('admin.media.duplicates.scan-similar'))
            ->assertRedirect(route('admin.media.duplicates'));

        Queue::assertPushed(GeneratePerceptualHash::class, fn (GeneratePerceptualHash $job): bool => $job->mediaName === $media->name);
    }

    public function test_confirmed_similar_image_can_be_merged_into_selected_keeper(): void
    {
        Storage::fake('media');
        $admin = $this->createAdmin();
        $keeper = $this->createMedia('preferred.png', 'Preferred Image', $admin, str_repeat('1', 64));
        $duplicate = $this->createMedia('similar-copy.jpg', 'Similar Copy', $admin, str_repeat('2', 64));
        $keeper->update(['perceptual_hash' => '1234567890abcdef', 'perceptual_hash_scanned_at' => now()]);
        $duplicate->update(['perceptual_hash' => '1234567890abcdee', 'perceptual_hash_scanned_at' => now()]);
        Storage::disk('media')->put((string) $keeper->hash, 'keeper-file');
        Storage::disk('media')->put((string) $duplicate->hash, 'duplicate-file');

        $workshop = Workshop::query()->create([
            'title' => 'Similar Image Workshop',
            'content' => '<p>Content</p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => Location::factory()->create()->id,
            'user_id' => $admin->id,
            'hero_media_name' => $duplicate->name,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.media.duplicates.merge-similar'), [
                'first' => $keeper->name,
                'second' => $duplicate->name,
            ])
            ->assertRedirect(route('admin.media.duplicates'));

        $this->assertDatabaseMissing('media', ['name' => $duplicate->name]);
        $this->assertSame($keeper->name, $workshop->fresh()->hero_media_name);
        Storage::disk('media')->assertExists((string) $keeper->hash);
        Storage::disk('media')->assertMissing((string) $duplicate->hash);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }

    private function createMedia(string $name, string $title, User $owner, string $hash): Media
    {
        return Media::query()->create([
            'name' => $name,
            'title' => $title,
            'hash' => $hash,
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
            'storage_disk' => 'media',
        ]);
    }
}
