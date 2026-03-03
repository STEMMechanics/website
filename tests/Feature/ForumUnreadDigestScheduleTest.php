<?php

namespace Tests\Feature;

use App\Jobs\SendForumUnreadNotification;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForumUnreadDigestScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_daily_digest_command_dispatches_for_distinct_users_with_notifications_enabled(): void
    {
        Queue::fake();

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userDisabled = User::factory()->create();

        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        $topicOne = $this->createTopic($category, $userA, 'Topic one');
        $topicTwo = $this->createTopic($category, $userB, 'Topic two');

        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicOne->id,
            'user_id' => $userA->id,
            'notifications_enabled' => true,
        ]);
        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicTwo->id,
            'user_id' => $userA->id,
            'notifications_enabled' => true,
        ]);
        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicTwo->id,
            'user_id' => $userB->id,
            'notifications_enabled' => true,
        ]);
        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicOne->id,
            'user_id' => $userDisabled->id,
            'notifications_enabled' => false,
        ]);

        Artisan::call('forum:send-unread-digest');

        Queue::assertPushed(SendForumUnreadNotification::class, 2);
        Queue::assertPushed(SendForumUnreadNotification::class, fn (SendForumUnreadNotification $job): bool => (string) $job->userId === (string) $userA->id);
        Queue::assertPushed(SendForumUnreadNotification::class, fn (SendForumUnreadNotification $job): bool => (string) $job->userId === (string) $userB->id);
        Queue::assertNotPushed(SendForumUnreadNotification::class, fn (SendForumUnreadNotification $job): bool => (string) $job->userId === (string) $userDisabled->id);
    }

    public function test_posting_a_reply_does_not_queue_unread_digest_immediately(): void
    {
        Queue::fake();

        $author = User::factory()->create();
        $recipient = User::factory()->create();

        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        $topic = $this->createTopic($category, $author, 'Immediate queue test');

        ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $author->id,
            'body' => '<p>Initial post body.</p>',
        ]);

        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $recipient->id,
            'notifications_enabled' => true,
            'last_read_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($author)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>Fresh reply content.</p>',
        ]);

        $response->assertRedirect();
        Queue::assertNotPushed(SendForumUnreadNotification::class);
    }

    private function createTopic(ForumCategory $category, User $author, string $title): ForumTopic
    {
        return ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'last_post_user_id' => $author->id,
            'title' => $title,
            'slug' => ForumTopic::generateUniqueSlug($title, (string) $category->id),
            'last_post_at' => now()->subHour(),
        ]);
    }
}
