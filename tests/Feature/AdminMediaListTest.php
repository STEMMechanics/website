<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Storage::fake('media');
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function media(string $name, array $attributes = []): Media
    {
        return Media::create(array_merge(['name' => $name, 'title' => $name, 'hash' => hash('sha256', $name), 'mime_type' => 'image/png', 'size' => 1048576, 'visibility' => 'private', 'storage_disk' => 'media'], $attributes));
    }

    public function test_pagination_remains_inside_ajax_region(): void
    {
        $this->admin();
        foreach (range(1, 26) as $n) $this->media("pagination-$n.png");
        $response = $this->get(route('admin.media.index', ['page' => 2]))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//section[@data-dynamic-list="admin-media-index"]//nav[@aria-label="Pagination Navigation"]')->length);
        $this->assertSame(0, $xpath->query('//section[@data-dynamic-list="admin-media-index"]//script')->length);
    }

    public function test_filtering_sorting_and_preset_counts_are_server_rendered(): void
    {
        $this->admin();
        $this->media('small.png', ['size' => 1048576, 'visibility' => 'public']);
        $this->media('large.png', ['size' => 3145728, 'visibility' => 'public']);
        $this->media('private.png', ['size' => 5242880]);
        $this->media('file.pdf', ['mime_type' => 'application/pdf']);
        $response = $this->get(route('admin.media.index', ['preset' => 'images', 'visibility' => 'public', 'size_min' => 1, 'sort' => 'size', 'direction' => 'desc']));
        $response->assertOk()->assertSee('aria-sort="descending"', false)->assertSee('data-list-dialog', false);
        $this->assertSame(['large.png', 'small.png'], $response->viewData('media')->pluck('name')->all());
        $this->assertSame(4, $response->viewData('presetCounts')['all']);
        $this->assertSame(3, $response->viewData('presetCounts')['images']);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@data-media-selection]//*[@data-list-footer]//form[@id="admin-media-bulk-form"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-list-footer]//*[@id="admin-media-edit-selected"]')->length);
        $this->assertSame(0, $xpath->query('//form//form')->length);
        $response->assertSeeInOrder(['large.png', 'Showing 1–2 of 2 files']);
    }

    public function test_advanced_filters_combine_patterns_and_whole_tags_for_list_and_selection(): void
    {
        $this->admin();
        $this->media('family-cats.png', ['title' => 'Family-cats', 'tags' => ' Pets ,  cats, school holiday ']);
        $this->media('excluded-cats.png', ['title' => 'Excluded-cats', 'tags' => 'pets,cats,archived']);
        $this->media('substring-cats.png', ['title' => 'Substring-cats', 'tags' => 'pets,caterpillar']);
        $this->media('wrong-mime.pdf', ['title' => 'Document-cats', 'mime_type' => 'application/pdf', 'tags' => 'pets,cats']);
        $filters = ['name_pattern' => '*-cats', 'mime_type' => 'image/*, video/*', 'tags_include' => 'PETS, cats', 'tags_exclude' => 'archived'];
        $response = $this->get(route('admin.media.index', $filters))->assertOk()->assertSee('Has all tags: PETS, cats');
        $this->assertSame(['family-cats.png'], $response->viewData('media')->pluck('name')->all());
        $this->getJson(route('admin.media.selection', $filters))->assertOk()->assertExactJson(['names' => ['family-cats.png']]);
    }

    public function test_wildcards_escape_sql_metacharacters_and_exclusions_include_untagged_files(): void
    {
        $this->admin();
        $this->media('literal.png', ['title' => '100%_cats']);
        $this->media('wild.png', ['title' => '100xxcats', 'tags' => 'cats']);
        $response = $this->get(route('admin.media.index', ['name_pattern' => '100%_cat?', 'tags_exclude' => 'cats']))->assertOk();
        $this->assertSame(['literal.png'], $response->viewData('media')->pluck('name')->all());
    }

    public function test_legacy_presets_render_as_removable_normal_filters(): void
    {
        $this->admin();
        $this->media('image.png');
        $response = $this->get(route('admin.media.index', ['preset' => 'images']))->assertOk()->assertSee('Type: image');
        $response->assertDontSee('name="preset"', false);
        $this->get(route('admin.media.index'))->assertOk()->assertDontSee('Type: image');
    }

    public function test_all_matching_selection_uses_the_same_filters_and_includes_other_pages(): void
    {
        $this->admin();
        foreach (range(1, 30) as $index) $this->media('image-'.$index.'.png');
        $this->media('ignore.pdf', ['mime_type' => 'application/pdf']);
        $response = $this->getJson(route('admin.media.selection', ['type' => 'image', 'page' => 2]));
        $response->assertOk()->assertJsonCount(30, 'names');
        $this->assertNotContains('ignore.pdf', $response->json('names'));
    }

    public function test_select_all_is_bounded_to_the_bulk_editor_limit(): void
    {
        $this->admin();
        Media::query()->insert(array_map(fn ($index) => [
            'name' => 'file-'.$index.'.png', 'title' => 'File '.$index,
            'hash' => hash('sha256', (string) $index), 'mime_type' => 'image/png', 'size' => 1,
        ], range(1, 5001)));
        $this->getJson(route('admin.media.selection'))->assertUnprocessable();
        Media::query()->whereNotIn('name', array_map(fn ($index) => 'file-'.$index.'.png', range(1, 834)))->delete();
        $this->getJson(route('admin.media.selection', ['page' => 2, 'per_page' => 25]))->assertOk()->assertJsonCount(834, 'names');
    }

    public function test_invalid_sort_and_unbounded_page_sizes_are_rejected(): void
    {
        $this->admin();
        $this->getJson(route('admin.media.index', ['sort' => 'title desc; drop table media', 'per_page' => 100000]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'per_page']);
    }

    public function test_quick_edit_only_updates_metadata_and_preserves_password_and_owner(): void
    {
        $admin = $this->admin();
        $media = $this->media('keep.png', ['user_id' => $admin->id, 'password' => 'existing-password-hash']);
        $this->patchJson(route('admin.media.quick-update', $media), ['title' => 'Renamed', 'caption' => 'A caption', 'tags' => 'robotics', 'visibility' => 'protected', 'user_id' => null, 'password' => null, 'storage_disk' => 'archive'])
            ->assertOk()->assertJson(['success' => true]);
        $media->refresh();
        $this->assertSame('Renamed', $media->title);
        $this->assertSame('existing-password-hash', $media->password);
        $this->assertSame($admin->id, $media->user_id);
        $this->assertSame('media', $media->storage_disk);
    }

    public function test_non_admin_cannot_select_or_quick_edit_media(): void
    {
        $this->actingAs(User::factory()->create());
        $media = $this->media('private.png');
        $this->getJson(route('admin.media.selection'))->assertForbidden();
        $this->patchJson(route('admin.media.quick-update', $media), ['title' => 'Stolen', 'visibility' => 'public'])->assertForbidden();
        $this->assertSame('private.png', $media->fresh()->title);
    }
}
