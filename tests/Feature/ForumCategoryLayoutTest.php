<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumCategoryLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_shows_create_thread_button_without_the_old_meta_panel(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Micro:bit Journey Forum',
            'slug' => 'microbit-journey-forum',
        ]);

        $response = $this->actingAs($user)->get(route('forum.category.show', $category->slug));

        $response->assertOk();
        $response->assertSee('Create Thread', false);
        $response->assertDontSee('forum-category-meta-panel', false);
    }

    public function test_category_snapshot_returns_plain_empty_text(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Empty Forum',
            'slug' => 'empty-forum',
        ]);

        $response = $this->actingAs($user)->getJson(route('forum.category.snapshot', $category->slug));

        $response->assertOk();
        $response->assertJsonPath('emptyText', 'No threads have been created in this category yet.');

        $payload = $response->json();
        $this->assertArrayNotHasKey('metaHtml', $payload);
    }
}
