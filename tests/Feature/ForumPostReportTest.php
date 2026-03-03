<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\ForumPostReport;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForumPostReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_authenticated_user_can_report_a_forum_post_and_email_is_queued(): void
    {
        Queue::fake();

        config()->set('mail.contact_to.address', 'hello@stemmechanics.com.au');

        $reporter = User::factory()->create();
        $author = User::factory()->create([
            'firstname' => 'Post',
            'surname' => 'Author',
            'email' => 'author@example.com',
        ]);
        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);
        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'last_post_user_id' => $author->id,
            'title' => 'Welcome thread',
            'slug' => 'welcome-thread',
            'last_post_at' => now(),
        ]);
        $post = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $author->id,
            'body' => '<p>This is the reported post body.</p>',
        ]);

        $response = $this->actingAs($reporter)->post(route('forum.post.report', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'forumPost' => $post->id,
        ]), [
            'reason' => 'This contains inappropriate language.',
            'report_post_id' => (string) $post->id,
            'report_author' => 'Post Author',
        ]);

        $response->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'sort' => 'oldest',
        ]).'#post-'.$post->id);
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($post, $reporter) {
            return $job->to === 'hello@stemmechanics.com.au'
                && $job->mailable instanceof ForumPostReport
                && (string) $job->mailable->post->id === (string) $post->id
                && (string) $job->mailable->reporter->id === (string) $reporter->id
                && $job->mailable->reason === 'This contains inappropriate language.'
                && str_contains($job->mailable->postUrl, '#post-'.$post->id);
        });
    }
}
