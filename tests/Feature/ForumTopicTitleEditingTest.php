<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumTopicTitleEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_topic_creation_stores_raw_markdown_and_renders_formatted_title(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory('General Discussion', 'general-discussion');

        $response = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Need **help** ~~soon~~',
            'body' => '<p>Opening post.</p>',
        ]);

        $response->assertRedirect();

        $topic = ForumTopic::query()->firstOrFail();
        $this->assertSame('Need **help** ~~soon~~', $topic->title);
        $this->assertSame('need-help-soon', $topic->slug);

        $categoryResponse = $this->get(route('forum.category.show', $category->slug));
        $categoryResponse->assertOk();
        $categoryResponse->assertSee('<strong>help</strong>', false);
        $categoryResponse->assertSee('<del>soon</del>', false);
    }

    public function test_topic_owner_can_update_title(): void
    {
        $owner = User::factory()->create();
        $category = $this->createCategory('Ideas', 'ideas');
        $topic = $this->createTopicWithFirstPost($category, $owner, 'First title');

        $response = $this->actingAs($owner)->put(route('forum.topic.title.update', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'title' => 'Owner *edited* **title**',
        ]);

        $response->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => 'owner-edited-title',
            'sort' => 'oldest',
        ]));

        $topic->refresh();
        $this->assertSame('Owner *edited* **title**', $topic->title);
        $this->assertSame('owner-edited-title', $topic->slug);
    }

    public function test_admin_can_update_another_users_title(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $this->addGroup($admin, 'admin');

        $category = $this->createCategory('Admins', 'admins');
        $topic = $this->createTopicWithFirstPost($category, $owner, 'Original title');

        $response = $this->actingAs($admin)->put(route('forum.topic.title.update', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'title' => 'Admin ~~updated~~ title',
        ]);

        $response->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => 'admin-updated-title',
            'sort' => 'oldest',
        ]));

        $topic->refresh();
        $this->assertSame('Admin ~~updated~~ title', $topic->title);
        $this->assertSame('admin-updated-title', $topic->slug);
    }

    public function test_non_owner_non_admin_cannot_update_title(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory('Helpdesk', 'helpdesk');
        $topic = $this->createTopicWithFirstPost($category, $owner, 'Owner title');

        $this->actingAs($otherUser)->put(route('forum.topic.title.update', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'title' => 'No access',
        ])->assertForbidden();
    }

    public function test_title_rendering_escapes_raw_html_and_only_formats_supported_markdown(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory('Workshop Info', 'workshop-info');
        $this->createTopicWithFirstPost($category, $user, 'Literal <b>tag</b> *only* ~~here~~ [link](https://example.com)');

        $response = $this->get(route('forum.category.show', $category->slug));

        $response->assertOk();
        $response->assertSee('&lt;b&gt;tag&lt;/b&gt;', false);
        $response->assertSee('<em>only</em>', false);
        $response->assertSee('<del>here</del>', false);
        $response->assertSee('[link](https://example.com)', false);
        $response->assertDontSee('href="https://example.com"', false);
        $response->assertDontSee('<b>tag</b>', false);
    }

    private function createCategory(string $name, string $slug): ForumCategory
    {
        return ForumCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createTopicWithFirstPost(ForumCategory $category, User $author, string $title): ForumTopic
    {
        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'last_post_user_id' => $author->id,
            'title' => $title,
            'slug' => ForumTopic::generateUniqueSlug($title, (string) $category->id),
            'last_post_at' => now(),
        ]);

        ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $author->id,
            'body' => '<p>Seed post body.</p>',
        ]);

        return $topic;
    }

    private function addGroup(User $user, string $slug): void
    {
        UserGroup::query()->create([
            'user_id' => $user->id,
            'slug' => UserGroup::normalizeSlug($slug),
        ]);
    }
}
