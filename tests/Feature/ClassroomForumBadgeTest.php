<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomForumBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_page_forum_tab_shows_unread_discussion_count(): void
    {
        $viewer = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $viewer->id,
            'slug' => 'admin',
        ]);

        $author = User::factory()->create();
        $forumCategory = ForumCategory::query()->create([
            'name' => 'Course forum',
            'slug' => 'course-forum',
            'read_group_slug' => 'admin',
            'write_group_slug' => 'admin',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Course Badge',
            'slug' => 'course-badge',
            'room_name' => 'course-badge',
            'forum_category_id' => $forumCategory->id,
        ]);

        $this->createUnreadForumTopic($forumCategory, $author, $viewer, 'Unread discussion 1');
        $this->createUnreadForumTopic($forumCategory, $author, $viewer, 'Unread discussion 2');

        $response = $this->actingAs($viewer)->get(route('class.show', $classSession));

        $response->assertOk();
        $response->assertSee('aria-label="2 unread discussions"', false);
    }

    private function createUnreadForumTopic(ForumCategory $category, User $author, User $reader, string $title): ForumTopic
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
            'is_topic_starter' => true,
            'is_approved' => true,
            'body' => '<p>Unread topic.</p>',
        ]);

        ForumTopicUserState::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $reader->id,
            'notifications_enabled' => true,
        ]);

        return $topic;
    }
}
