<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaBulkEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_bulk_editor_for_selected_media(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->makeMedia($admin, 'first.jpg', ['visibility' => 'private', 'tags' => 'robotics']);
        $second = $this->makeMedia($admin, 'second.jpg', ['visibility' => 'private', 'tags' => 'robotics']);

        $response = $this->actingAs($admin)->postJson(route('admin.media.bulk.select'), [
            'media_names' => [$first->name, $second->name],
        ])->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Editing 2 media items', $html);
        $this->assertStringContainsString('robotics', $html);
        $this->assertStringContainsString('data-media-bulk-edit-form', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<html', $html);
    }

    public function test_bulk_update_changes_only_values_that_differ_from_the_displayed_state(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->makeMedia($admin, 'first.jpg', [
            'visibility' => 'private',
            'tags' => 'first-tag',
            'caption' => 'First caption',
        ]);
        $second = $this->makeMedia($admin, 'second.jpg', [
            'visibility' => 'private',
            'tags' => 'second-tag',
            'caption' => 'Second caption',
        ]);

        $this->withSession(['admin_media_bulk_selection' => [$first->name, $second->name]])
            ->actingAs($admin)
            ->put(route('admin.media.bulk.update'), [
                'visibility' => 'public',
                'user_id' => (string) $admin->id,
                'tags' => '',
                'caption' => '',
                'consent_notes' => '',
                'photographed_at' => '',
            ])
            ->assertRedirect(route('admin.media.index'));

        $this->assertSame('public', $first->fresh()->visibility);
        $this->assertSame('public', $second->fresh()->visibility);
        $this->assertSame('first-tag', $first->fresh()->tags);
        $this->assertSame('second-tag', $second->fresh()->tags);
        $this->assertSame('First caption', $first->fresh()->caption);
        $this->assertSame('Second caption', $second->fresh()->caption);
    }

    public function test_bulk_editor_marks_different_dropdown_values_as_mixed(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->makeMedia($admin, 'first.jpg', ['visibility' => 'public']);
        $second = $this->makeMedia($admin, 'second.jpg', ['visibility' => 'private']);

        $response = $this->withSession(['admin_media_bulk_selection' => [$first->name, $second->name]])
            ->actingAs($admin)
            ->postJson(route('admin.media.bulk.select'), ['media_names' => [$first->name, $second->name]]);

        $response->assertOk();
        $this->assertStringContainsString('value="__mixed"', $response->json('html'));
        $this->assertStringContainsString('Mixed', $response->json('html'));
    }

    public function test_bulk_update_moves_selected_media_to_another_storage_disk(): void
    {
        $admin = $this->makeAdmin();
        Storage::fake('media');
        Storage::fake('archive');
        $first = $this->makeMedia($admin, 'first.jpg');
        $second = $this->makeMedia($admin, 'second.jpg');
        Storage::disk('media')->put((string) $first->hash, $first->name);

        $this->withSession(['admin_media_bulk_selection' => [$first->name, $second->name]])
            ->actingAs($admin)
            ->put(route('admin.media.bulk.update'), [
                'storage_disk' => 'archive',
            ])
            ->assertRedirect(route('admin.media.index'));

        foreach ([$first, $second] as $medium) {
            $this->assertSame('archive', $medium->fresh()->storage_disk);
            Storage::disk('archive')->assertExists((string) $medium->hash);
            Storage::disk('media')->assertMissing((string) $medium->hash);
        }
    }

    public function test_bulk_editor_shows_mixed_storage_without_moving_media_until_changed(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->makeMedia($admin, 'first.jpg');
        $second = $this->makeMedia($admin, 'second.jpg', ['storage_disk' => 'archive']);

        $response = $this->withSession(['admin_media_bulk_selection' => [$first->name, $second->name]])
            ->actingAs($admin)
            ->postJson(route('admin.media.bulk.select'), ['media_names' => [$first->name, $second->name]]);

        $response->assertOk();
        $this->assertStringContainsString('name="storage_disk"', $response->json('html'));
        $this->assertStringContainsString('<option value="__mixed" selected>Mixed</option>', $response->json('html'));
    }

    public function test_media_editor_only_requires_a_new_user_first_name_when_the_create_modal_is_open(): void
    {
        $admin = $this->makeAdmin();
        $medium = $this->makeMedia($admin, 'first.jpg');

        $this->actingAs($admin)
            ->get(route('admin.media.edit', $medium))
            ->assertOk()
            ->assertSee('name="new_user_firstname"', false)
            ->assertSee('x-bind:required="createUserOpen"', false)
            ->assertDontSee('name="new_user_firstname" required', false);
    }

    public function test_bulk_update_adds_and_removes_workshop_links_without_replacing_other_links(): void
    {
        $admin = $this->makeAdmin();
        $location = Location::factory()->create();
        $hero = $this->makeMedia($admin, 'workshop-hero.jpg');
        $workshopAttributes = [
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $hero->name,
        ];
        $wrongWorkshop = Workshop::factory()->create($workshopAttributes);
        $rightWorkshop = Workshop::factory()->create($workshopAttributes);
        $otherWorkshop = Workshop::factory()->create($workshopAttributes);
        $first = $this->makeMedia($admin, 'first.jpg');
        $second = $this->makeMedia($admin, 'second.jpg');

        $first->workshopPhotos()->attach($wrongWorkshop->id, ['collection' => 'workshop_photos']);
        $first->workshopPhotos()->attach($otherWorkshop->id, ['collection' => 'workshop_photos']);
        $second->workshopPhotos()->attach($wrongWorkshop->id, ['collection' => 'workshop_photos']);

        $this->withSession(['admin_media_bulk_selection' => [$first->name, $second->name]])
            ->actingAs($admin)
            ->put(route('admin.media.bulk.update'), [
                'remove_workshop_ids' => [(string) $wrongWorkshop->id],
                'add_workshop_ids' => [(string) $rightWorkshop->id],
            ])
            ->assertRedirect(route('admin.media.index'));

        $this->assertEqualsCanonicalizing(
            [(string) $otherWorkshop->id, (string) $rightWorkshop->id],
            $first->fresh()->workshopPhotos->pluck('id')->map('strval')->all(),
        );
        $this->assertSame(
            [(string) $rightWorkshop->id],
            $second->fresh()->workshopPhotos->pluck('id')->map('strval')->all(),
        );
    }

    public function test_bulk_editor_requires_an_existing_selection(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->postJson(route('admin.media.bulk.select'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('media_names');
        $this->get(route('admin.media.bulk.edit'))->assertRedirect(route('admin.media.index'));
    }

    public function test_popup_save_uses_explicit_selection_and_returns_validation_errors_as_json(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->makeMedia($admin, 'selected.jpg', ['tags' => 'keep']);
        $other = $this->makeMedia($admin, 'other-tab.jpg');
        $this->actingAs($admin)->withSession(['admin_media_bulk_selection' => [$other->name]])
            ->putJson(route('admin.media.bulk.update'), ['media_names' => [$first->name], 'caption' => 'Changed'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Changed', $first->fresh()->caption);
        $this->assertSame('keep', $first->fresh()->tags);
        $this->assertNotSame('Changed', $other->fresh()->caption);
        $this->putJson(route('admin.media.bulk.update'), ['caption' => 'No selection'])->assertUnprocessable()->assertJsonValidationErrors('media_names');
        $this->putJson(route('admin.media.bulk.update'), ['media_names' => [$first->name], 'storage_disk' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('storage_disk');
        $this->postJson(route('admin.media.bulk.select'), ['media_names' => ['missing.jpg']])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson(route('admin.media.bulk.select'), ['media_names' => [$first->name]])->assertForbidden();
        $this->putJson(route('admin.media.bulk.update'), ['media_names' => [$first->name], 'caption' => 'Denied'])->assertForbidden();
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeMedia(User $owner, string $name, array $attributes = []): Media
    {
        Storage::fake('media');
        $media = Media::query()->create(array_merge([
            'name' => $name,
            'title' => ucfirst(pathinfo($name, PATHINFO_FILENAME)),
            'hash' => hash('sha256', $name),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'user_id' => $owner->id,
        ], $attributes));
        Storage::disk('media')->put((string) $media->hash, $name);

        return $media;
    }
}
