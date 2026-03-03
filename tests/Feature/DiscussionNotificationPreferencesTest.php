<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\ForumUnreadNotification;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DiscussionNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_account_preferences_show_discussion_subscription_count_and_can_unsubscribe_all(): void
    {
        $user = User::factory()->create([
            'email' => 'discussion-user@example.com',
        ]);

        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        $topicA = $this->createTopic($category, $user, 'First discussion');
        $topicB = $this->createTopic($category, $user, 'Second discussion');
        $topicC = $this->createTopic($category, $user, 'Third discussion');

        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicA->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
        ]);
        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicB->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
        ]);
        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicC->id,
            'user_id' => $user->id,
            'notifications_enabled' => false,
        ]);

        $showResponse = $this->actingAs($user)->get(route('account.show'));
        $showResponse->assertOk();
        $showResponse->assertSee('You are subscribed to');
        $showResponse->assertSee('2');
        $showResponse->assertSee('discussion threads');

        $unsubscribeResponse = $this->actingAs($user)
            ->post(route('account.discussions.unsubscribe-all'));

        $unsubscribeResponse->assertRedirect(route('account.show'));
        $this->assertSame(0, ForumTopicUserState::query()
            ->where('user_id', $user->id)
            ->where('notifications_enabled', true)
            ->count());
    }

    public function test_discussion_unsubscribe_link_disables_all_discussion_notifications(): void
    {
        $user = User::factory()->create([
            'email' => 'discussion-link@example.com',
        ]);

        $category = ForumCategory::query()->create([
            'name' => 'STEMCraft',
            'slug' => 'stemcraft',
        ]);

        $topicA = $this->createTopic($category, $user, 'Alpha discussion');
        $topicB = $this->createTopic($category, $user, 'Beta discussion');

        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicA->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
        ]);
        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topicB->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
        ]);

        $sentEmail = SentEmail::query()->create([
            'recipient' => $user->email,
            'mailable_class' => ForumUnreadNotification::class,
            'status' => SentEmail::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $response = $this->get(route('unsubscribe.discussions', ['email' => $sentEmail->id]));

        $response->assertRedirect(route('index'));
        $this->assertSame(0, ForumTopicUserState::query()
            ->where('user_id', $user->id)
            ->where('notifications_enabled', true)
            ->count());
    }

    public function test_forum_unread_notification_uses_discussion_unsubscribe_route(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'discussion-mail@example.com',
        ]);

        $category = ForumCategory::query()->create([
            'name' => 'Announcements',
            'slug' => 'announcements',
        ]);

        $topic = $this->createTopic($category, $user, 'Release notes');
        $post = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $user->id,
            'body' => '<p>Welcome to the release notes.</p>',
        ]);

        $threadDigests = collect([
            [
                'topic' => $topic->fresh('category'),
                'posts' => collect([$post]),
                'url' => route('forum.topic.show', [
                    'categorySlug' => $category->slug,
                    'topicSlug' => $topic->slug,
                ]),
            ],
        ]);

        $job = new SendEmail($user->email, new ForumUnreadNotification($user, $threadDigests));
        $job->handle();

        Mail::assertSent(ForumUnreadNotification::class, function (ForumUnreadNotification $mailable): bool {
            $html = $mailable->render();

            return str_contains($html, '/unsubscribe/discussions/');
        });
    }

    private function createTopic(ForumCategory $category, User $user, string $title): ForumTopic
    {
        return ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $user->id,
            'last_post_user_id' => $user->id,
            'title' => $title,
            'slug' => ForumTopic::generateUniqueSlug($title, (string) $category->id),
            'last_post_at' => now(),
        ]);
    }
}
