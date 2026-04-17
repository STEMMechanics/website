<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Carbon\Carbon;
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

    public function test_category_page_sorts_regular_topics_latest_first_by_default_and_can_switch_to_oldest(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'General Forum',
            'slug' => 'general-forum',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 4, 16, 9, 0, 0));
        $this->createTopicWithFirstPost($category, $user, 'Older discussion');
        Carbon::setTestNow(Carbon::create(2026, 4, 16, 10, 0, 0));
        $this->createTopicWithFirstPost($category, $user, 'Newer discussion');
        Carbon::setTestNow();

        $response = $this->actingAs($user)->get(route('forum.category.show', $category->slug));
        $response->assertOk();
        $response->assertSeeInOrder(['Newer discussion', 'Older discussion'], false);

        $response = $this->actingAs($user)->get(route('forum.category.show', [
            'categorySlug' => $category->slug,
            'topicSort' => 'oldest',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Older discussion', 'Newer discussion'], false);
    }

    public function test_course_category_defaults_to_oldest_topics_and_can_switch_to_latest(): void
    {
        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'Course Forum',
            'slug' => 'course-forum',
        ]);

        ClassSession::query()->create([
            'title' => 'Course Forum',
            'slug' => 'course-forum',
            'room_name' => 'course-forum',
            'forum_category_id' => $category->id,
            'starts_at' => Carbon::create(2026, 4, 16, 9, 0, 0),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 4, 16, 9, 0, 0));
        $this->createTopicWithFirstPost($category, $user, 'Course oldest');
        Carbon::setTestNow(Carbon::create(2026, 4, 16, 10, 0, 0));
        $this->createTopicWithFirstPost($category, $user, 'Course newest');
        Carbon::setTestNow();

        $response = $this->actingAs($user)->get(route('forum.category.show', $category->slug));
        $response->assertOk();
        $response->assertSeeInOrder(['Course date: 16 Apr 2026', '2 threads'], false);
        $response->assertSeeInOrder(['Course oldest', 'Course newest'], false);

        $response = $this->actingAs($user)->get(route('forum.category.show', [
            'categorySlug' => $category->slug,
            'topicSort' => 'latest',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Course newest', 'Course oldest'], false);
    }

    public function test_admin_forum_category_index_shows_course_labels(): void
    {
        $admin = $this->createAdminUser();
        $generalCategory = ForumCategory::query()->create([
            'name' => 'General Forum',
            'slug' => 'general-forum',
        ]);
        $courseCategory = ForumCategory::query()->create([
            'name' => 'Robotics Forum',
            'slug' => 'robotics-forum',
        ]);
        $newerCourseCategory = ForumCategory::query()->create([
            'name' => 'AI Forum',
            'slug' => 'ai-forum',
        ]);

        ClassSession::query()->create([
            'title' => 'Robotics',
            'slug' => 'robotics',
            'room_name' => 'robotics',
            'forum_category_id' => $courseCategory->id,
            'starts_at' => Carbon::create(2026, 4, 16, 9, 0, 0),
        ]);

        ClassSession::query()->create([
            'title' => 'AI',
            'slug' => 'ai',
            'room_name' => 'ai',
            'forum_category_id' => $newerCourseCategory->id,
            'starts_at' => Carbon::create(2026, 5, 16, 9, 0, 0),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.forum.category.index'));

        $response->assertOk();
        $response->assertSeeText('Normal categories');
        $response->assertSeeText('Course categories');
        $response->assertDontSee('Course categories are linked to a course session', false);
        $response->assertSeeText('These are linked to courses, sorted by course date newest first, and are read-only except for delete.');

        $content = $response->getContent();
        $aiForumPosition = strpos($content, 'AI Forum');
        $roboticsForumPosition = strpos($content, 'Robotics Forum');
        $this->assertNotFalse($aiForumPosition);
        $this->assertNotFalse($roboticsForumPosition);
        $this->assertLessThan($roboticsForumPosition, $aiForumPosition);
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

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        $admin->groups()->create([
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
