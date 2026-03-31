<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumMastTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_forum_tab_stays_active_on_topic_pages(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Course Forum',
            'slug' => 'course-forum',
        ]);
        ClassSession::query()->create([
            'title' => 'Course 101',
            'slug' => 'course-101',
            'forum_category_id' => $category->id,
        ]);

        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $user->id,
            'last_post_user_id' => $user->id,
            'title' => 'Thread title',
            'slug' => 'thread-title',
            'last_post_at' => now(),
            'is_approved' => true,
        ]);

        ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_topic_starter' => true,
            'is_approved' => true,
            'body' => '<p>Thread body.</p>',
        ]);

        $response = $this->actingAs($user)->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'href="'.route('forum.category.show', $category->slug).'" class="shrink-0 rounded-t-md px-4 py-2 bg-gray-100 text-primary-color-dark transition-colors"',
            $response->getContent()
        );
    }
}
