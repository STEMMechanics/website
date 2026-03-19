<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\ChildForumActivityNotification;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChildAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_parent_can_create_child_account_and_child_can_log_in_with_password(): void
    {
        $parent = User::factory()->create();

        $response = $this->actingAs($parent)->post(route('account.children.store'), [
            'username' => 'kid-forum',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'child_can_create_forum_topics' => '1',
            'child_can_reply_in_forum' => '1',
        ]);

        $child = User::query()->where('username', 'kid-forum')->firstOrFail();

        $response->assertRedirect(route('account.children.edit', $child));
        $this->assertSame((string) $parent->id, (string) $child->parent_user_id);
        $this->assertTrue($child->isChildAccount());

        auth()->logout();

        $loginResponse = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'kid-forum',
            'password' => 'secret1234',
            'remember_email' => '0',
        ]);

        $loginResponse->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($child);
    }

    public function test_verified_full_user_can_log_in_with_password(): void
    {
        $user = User::factory()->create([
            'username' => 'member-pass',
            'password' => 'secret1234',
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'member-pass',
            'password' => 'secret1234',
            'remember_email' => '0',
        ]);

        $response->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_child_account_is_blocked_from_full_account_routes_and_forum_permissions_can_be_disabled(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
            'child_can_create_forum_topics' => false,
            'child_can_reply_in_forum' => false,
        ]);

        $category = $this->createCategory('General', 'general');
        [$topic] = $this->createTopicWithFirstPost($category, $parent, 'Welcome');

        $this->actingAs($child)
            ->get(route('account.ticket.index'))
            ->assertRedirect(route('account.show'));

        $this->actingAs($child)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Blocked thread',
                'body' => '<p>Not allowed.</p>',
            ])
            ->assertForbidden();

        $this->actingAs($child)
            ->post(route('forum.post.store', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
            ]), [
                'body' => '<p>Blocked reply.</p>',
            ])
            ->assertForbidden();
    }

    public function test_child_reply_can_require_parent_approval_and_sends_notification_email(): void
    {
        Queue::fake();

        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
            'child_forum_reply_requires_approval' => true,
            'child_parent_notified_on_forum_replies' => true,
        ]);

        $category = $this->createCategory('Ideas', 'ideas');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Shared topic');

        $response = $this->actingAs($child)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>Pending reply body.</p>',
        ]);

        $response->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'sort' => 'oldest',
        ]));

        $pendingReply = ForumPost::query()
            ->where('user_id', (string) $child->id)
            ->where('forum_topic_id', (string) $topic->id)
            ->where('is_approved', false)
            ->firstOrFail();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($parent): bool {
            return $job->to === $parent->email
                && $job->mailable instanceof ChildForumActivityNotification;
        });

        $this->actingAs($parent)
            ->post(route('account.children.post.approve', [
                'child' => $child,
                'forumPost' => $pendingReply,
            ]))
            ->assertRedirect(route('account.children.edit', $child));

        $pendingReply->refresh();
        $topic->refresh();

        $this->assertTrue((bool) $pendingReply->is_approved);
        $this->assertSame((string) $parent->id, (string) $pendingReply->approved_by_user_id);
        $this->assertSame((string) $child->id, (string) $topic->last_post_user_id);

        $this->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))->assertSee('Pending reply body.');
    }

    public function test_child_deletion_anonymizes_account_and_releases_username(): void
    {
        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-delete',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);

        $category = $this->createCategory('General', 'general');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Existing topic');
        $reply = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $child->id,
            'body' => '<p>Reply stays visible.</p>',
        ]);

        $this->actingAs($parent)
            ->delete(route('account.children.destroy', $child), [
                'delete_discussion_threads' => '0',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $reply->refresh();

        $this->assertNotNull($child->anonymized_at);
        $this->assertNotSame('kid-delete', (string) $child->username);
        $this->assertNull($child->email);
        $this->assertNull($child->parent_user_id);
        $this->assertSame('<p>Reply stays visible.</p>', $reply->body);

        $replacement = User::factory()->create([
            'username' => 'kid-delete',
        ]);

        $this->assertSame('kid-delete', $replacement->username);

        $this->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))->assertSee('deleted');
    }

    public function test_reply_deletion_is_soft_deleted_placeholder_for_author(): void
    {
        $author = User::factory()->create();
        $replyAuthor = User::factory()->create();
        $category = $this->createCategory('General', 'general');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Thread title');

        $reply = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $replyAuthor->id,
            'body' => '<p>Temporary reply.</p>',
        ]);

        $this->actingAs($replyAuthor)
            ->delete(route('forum.post.destroy', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
                'forumPost' => $reply,
            ]))
            ->assertRedirect();

        $reply->refresh();

        $this->assertNotNull($reply->deleted_at);
        $this->assertSame('<p><em>deleted</em></p>', $reply->body);
    }

    private function createCategory(string $name, string $slug): ForumCategory
    {
        return ForumCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
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
}
