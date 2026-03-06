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

class ForumAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_stemcraft_example_public_read_minecraft_write(): void
    {
        $author = User::factory()->create();
        $minecraftUser = User::factory()->create();
        $regularUser = User::factory()->create();
        $this->addGroup($minecraftUser, 'minecraft');

        $category = $this->createCategory('Stemcraft', 'stemcraft', null, 'minecraft');
        $this->createTopicWithFirstPost($category, $author, 'Server updates');

        $this->get(route('forum.category.show', $category->slug))->assertOk();
        $this->actingAs($regularUser)->get(route('forum.category.show', $category->slug))->assertOk();

        $this->actingAs($regularUser)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Can I post?',
                'body' => 'Hello from a regular member.',
            ])
            ->assertForbidden();

        $response = $this->actingAs($minecraftUser)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Minecraft post',
                'body' => 'Hello from minecraft group.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_topics', [
            'forum_category_id' => $category->id,
            'title' => 'Minecraft post',
        ]);
    }

    public function test_helpdesk_example_public_read_logged_in_write(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $category = $this->createCategory('Helpdesk', 'helpdesk', null, null);
        $this->createTopicWithFirstPost($category, $author, 'Need help');

        $this->get(route('forum.category.show', $category->slug))->assertOk();

        $this->post(route('forum.topic.store', $category->slug), [
            'title' => 'Guest attempt',
            'body' => 'I am not logged in.',
        ])->assertRedirect(route('login'));

        $response = $this->actingAs($member)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Member help request',
                'body' => 'I need assistance.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_topics', [
            'forum_category_id' => $category->id,
            'title' => 'Member help request',
        ]);
    }

    public function test_workshopinfo_example_user_read_logged_in_write(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $category = $this->createCategory('Workshop Info', 'workshopinfo', 'user', null);
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Workshop schedule');

        $this->get(route('forum.category.show', $category->slug))->assertRedirect(route('login'));
        $this->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))->assertRedirect(route('login'));

        $this->actingAs($member)->get(route('forum.category.show', $category->slug))->assertOk();
        $this->actingAs($member)->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))->assertOk();

        $response = $this->actingAs($member)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Logged-in workshop question',
                'body' => 'Can you confirm times?',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_topics', [
            'forum_category_id' => $category->id,
            'title' => 'Logged-in workshop question',
        ]);
    }

    public function test_admins_example_admin_read_and_write(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $this->addGroup($admin, 'admin');

        $category = $this->createCategory('Admins', 'admins', 'admin', 'admin');
        [$topic] = $this->createTopicWithFirstPost($category, $admin, 'Private admin thread');

        $this->get(route('forum.category.show', $category->slug))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('forum.category.show', $category->slug))
            ->assertRedirect(route('forum.index'))
            ->assertSessionHas('message-title', 'Access denied');
        $this->actingAs($admin)->get(route('forum.category.show', $category->slug))->assertOk();

        $this->actingAs($member)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Non-admin thread',
                'body' => 'This should be blocked.',
            ])
            ->assertForbidden();

        $response = $this->actingAs($admin)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Admin thread',
                'body' => 'This is admin-only.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_topics', [
            'forum_category_id' => $category->id,
            'title' => 'Admin thread',
        ]);

        $this->actingAs($member)->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))
            ->assertRedirect(route('forum.index'))
            ->assertSessionHas('message-title', 'Access denied');
        $this->actingAs($admin)->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))->assertOk();
    }

    public function test_posting_notifications_and_reporting_require_login(): void
    {
        $author = User::factory()->create();
        $category = $this->createCategory('General', 'general', null, null);
        [$topic, $post] = $this->createTopicWithFirstPost($category, $author, 'General topic');

        $this->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => 'Guest reply',
        ])->assertRedirect(route('login'));

        $this->post(route('forum.topic.notifications', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'notifications_enabled' => 1,
        ])->assertRedirect(route('login'));

        $this->post(route('forum.post.report', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'forumPost' => $post->id,
        ]), [
            'reason' => 'Guest report attempt',
        ])->assertRedirect(route('login'));
    }

    private function createCategory(string $name, string $slug, ?string $readGroupSlug, ?string $writeGroupSlug): ForumCategory
    {
        return ForumCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'read_group_slug' => $readGroupSlug,
            'write_group_slug' => $writeGroupSlug,
        ]);
    }

    /**
     * @return array{ForumTopic, ForumPost}
     */
    private function createTopicWithFirstPost(ForumCategory $category, User $author, string $title): array
    {
        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'last_post_user_id' => $author->id,
            'title' => $title,
            'slug' => ForumTopic::generateUniqueSlug($title, (string) $category->id),
            'last_post_at' => now(),
        ]);

        $post = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $author->id,
            'body' => '<p>Seed post body.</p>',
        ]);

        return [$topic, $post];
    }

    private function addGroup(User $user, string $slug): void
    {
        UserGroup::query()->create([
            'user_id' => $user->id,
            'slug' => UserGroup::normalizeSlug($slug),
        ]);
    }
}
