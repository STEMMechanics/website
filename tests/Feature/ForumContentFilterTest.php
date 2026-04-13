<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumContentFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_forum_topic_creation_is_blocked_by_blasp_profanity_detection(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        $response = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Need help',
            'body' => '<p>This contains fuck in the post body.</p>',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('forum_topics', 0);
        $this->assertDatabaseCount('forum_posts', 0);
    }

    public function test_forum_topic_creation_blocks_profanity_hidden_inside_title_markdown(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        $response = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Need f**u**ck help',
            'body' => '<p>This body is clean.</p>',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('forum_topics', 0);
        $this->assertDatabaseCount('forum_posts', 0);
    }

    public function test_forum_reply_is_blocked_for_all_caps_when_enabled(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Ideas',
            'slug' => 'ideas',
        ]);
        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $user->id,
            'last_post_user_id' => $user->id,
            'title' => 'Welcome',
            'slug' => 'welcome',
            'last_post_at' => now(),
        ]);

        SiteOption::query()->create([
            'name' => 'moderation.content-filter.block-all-caps',
            'value' => '1',
        ]);
        SiteOption::query()->create([
            'name' => 'moderation.content-filter.min-all-caps-letters',
            'value' => '8',
        ]);

        $response = $this->actingAs($user)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>THIS IS BAD</p>',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('forum_posts', 0);
    }

    public function test_forum_filter_can_be_disabled_globally(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Ideas',
            'slug' => 'ideas',
        ]);
        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $user->id,
            'last_post_user_id' => $user->id,
            'title' => 'Welcome',
            'slug' => 'welcome',
            'last_post_at' => now(),
        ]);

        SiteOption::query()->create([
            'name' => 'moderation.content-filter.enabled',
            'value' => '0',
        ]);
        $response = $this->actingAs($user)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>fuck is here but filtering is disabled.</p>',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('forum_posts', 1);
    }

    public function test_forum_topic_creation_honours_custom_exception_words(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        SiteOption::query()->create([
            'name' => 'moderation.content-filter.exception-words',
            'value' => "fuck\n",
        ]);

        $response = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Need help',
            'body' => '<p>This contains fuck but should be allowed because it is on the exception list.</p>',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('forum_topics', 1);
        $this->assertDatabaseCount('forum_posts', 1);
    }

    public function test_custom_regex_pattern_blocks_exact_term_without_matching_substrings(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Ideas',
            'slug' => 'ideas',
        ]);

        SiteOption::query()->create([
            'name' => 'moderation.content-filter.custom-patterns',
            'value' => '\bfck\b',
        ]);

        $blockedResponse = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'First thread',
            'body' => '<p>This contains fck directly.</p>',
        ]);

        $blockedResponse->assertSessionHasErrors('body');
        $this->assertDatabaseCount('forum_topics', 0);

        $allowedResponse = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Second thread',
            'body' => '<p>This contains bofck but should pass.</p>',
        ]);

        $allowedResponse->assertRedirect();
        $allowedResponse->assertSessionHasNoErrors();
        $this->assertDatabaseCount('forum_topics', 1);
        $this->assertDatabaseCount('forum_posts', 1);
    }
}
