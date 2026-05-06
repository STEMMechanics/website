<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostAttachment;
use App\Models\ForumTopic;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_forum_index_is_served_with_no_cache_headers(): void
    {
        $response = $this->get(route('forum.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
        $this->assertStringContainsString(
            'Cookie',
            (string) $response->headers->get('Vary')
        );
    }

    public function test_public_forum_feed_exposes_thread_metadata_and_excludes_private_categories(): void
    {
        Storage::fake('local');

        $author = User::factory()->create([
            'firstname' => 'Feed',
            'surname' => 'Author',
            'username' => 'feedauthor',
        ]);

        $publicCategory = $this->createCategory('General Discussion', 'general-discussion', null, null);
        $membersCategory = $this->createCategory('Members Lounge', 'members-lounge', 'user', null);
        $adminsCategory = $this->createCategory('Admin Lounge', 'admin-lounge', 'admin', null);

        [$publicTopic, $publicFirstPost] = $this->createTopicWithFirstPost($publicCategory, $author, 'Public forum topic');
        $publicFirstPost->forceFill([
            'body' => '<p>Public topic excerpt with <strong>formatting</strong>.</p>',
        ])->save();

        ForumPost::query()->create([
            'forum_topic_id' => $publicTopic->id,
            'parent_forum_post_id' => $publicFirstPost->id,
            'user_id' => $author->id,
            'body' => '<p>Public reply body.</p>',
            'is_approved' => true,
        ]);

        $imagePath = 'forum-post-attachments/'.(string) $publicFirstPost->id.'/'.'topic-cover.jpg';
        Storage::disk('local')->put($imagePath, 'image-bytes');

        $imageAttachment = ForumPostAttachment::query()->create([
            'forum_post_id' => $publicFirstPost->id,
            'uploaded_by_user_id' => $author->id,
            'original_filename' => 'topic-cover.jpg',
            'storage_path' => $imagePath,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'sort_order' => 1,
        ]);

        $publicUpdatedAt = now();
        $publicTopic->forceFill([
            'is_pinned' => true,
            'is_locked' => true,
            'last_post_at' => $publicUpdatedAt,
            'last_post_user_id' => $author->id,
        ])->save();

        $this->createTopicWithFirstPost($membersCategory, $author, 'Members-only forum topic');
        $this->createTopicWithFirstPost($adminsCategory, $author, 'Admin-only forum topic');

        $indexResponse = $this->get(route('forum.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('forum.feed'));

        $feedResponse = $this->get(route('forum.feed'));
        $feedResponse->assertOk();
        $feedResponse->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $feedResponse->assertSee('Public forum topic');
        $feedResponse->assertSee(route('forum.topic.show', [
            'categorySlug' => $publicCategory->slug,
            'topicSlug' => $publicTopic->slug,
        ]));
        $feedResponse->assertSee(route('forum.post.attachment.download', [
            'categorySlug' => $publicCategory->slug,
            'topicSlug' => $publicTopic->slug,
            'forumPost' => $publicFirstPost->id,
            'attachment' => $imageAttachment->id,
        ]), false);
        $feedResponse->assertSee('type="image/jpeg"', false);
        $feedResponse->assertSee('Public topic excerpt with formatting.', false);
        $feedResponse->assertSee('<sm:category>General Discussion</sm:category>', false);
        $feedResponse->assertSee('<sm:author>feedauthor</sm:author>', false);
        $feedResponse->assertSee('<sm:excerpt>Public topic excerpt with formatting.</sm:excerpt>', false);
        $feedResponse->assertSee('<sm:replyCount>1</sm:replyCount>', false);
        $feedResponse->assertSee('<sm:locked>true</sm:locked>', false);
        $feedResponse->assertSee('<sm:pinned>true</sm:pinned>', false);
        $feedResponse->assertSee('<sm:updatedAt>'.$publicUpdatedAt->toAtomString().'</sm:updatedAt>', false);
        $feedResponse->assertDontSee('Members-only forum topic');
        $feedResponse->assertDontSee('Admin-only forum topic');
        $feedResponse->assertSee('STEMMechanics Discussions');
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

    public function test_ended_course_discussions_become_read_only_for_users(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $category = $this->createCategory('Course Forum', 'course-forum', null, null);

        ClassSession::query()->create([
            'title' => 'Course Forum',
            'slug' => 'course-forum',
            'room_name' => 'course-forum',
            'forum_category_id' => $category->id,
            'starts_at' => Carbon::now()->subWeeks(2),
            'ends_at' => Carbon::now()->subDay(),
        ]);

        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Archived discussion');

        $this->actingAs($member)->get(route('forum.category.show', $category->slug))->assertOk();
        $this->actingAs($member)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Attempted new thread',
                'body' => 'This should not be allowed after the course ends.',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('forum.post.store', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
            ]), [
                'body' => 'Attempted reply',
            ])
            ->assertForbidden();
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
