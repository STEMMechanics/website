<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumIndexCourseDiscussionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_discussions_are_grouped_below_other_forums_with_unread_counts(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        $generalCategory = ForumCategory::query()->create([
            'name' => 'General chat',
            'slug' => 'general-chat',
        ]);

        $courseCategory = ForumCategory::query()->create([
            'name' => 'Course one',
            'slug' => 'course-one-forum',
            'read_group_slug' => 'user',
            'write_group_slug' => 'user',
        ]);

        ClassSession::query()->create([
            'title' => 'Course One',
            'slug' => 'course-one',
            'room_name' => 'course-one',
            'forum_category_id' => $courseCategory->id,
            'starts_at' => '2026-04-16 09:00:00',
            'ends_at' => '2026-05-14 10:00:00',
        ]);

        $this->createUnreadForumTopic($generalCategory, $author, $viewer, 'General thread');
        $this->createUnreadForumTopic($courseCategory, $author, $viewer, 'Course thread 1');
        $this->createUnreadForumTopic($courseCategory, $author, $viewer, 'Course thread 2');

        $response = $this->actingAs($viewer)->get(route('forum.index'));

        $response->assertOk();
        $response->assertSeeText('Categories');
        $response->assertSeeText('Course discussions');
        $response->assertSeeText('16 Apr 2026 - 14 May 2026');
        $response->assertSeeInOrder(['Categories', 'General chat', 'Course discussions', 'Course one'], false);
        $response->assertSee('data-unread-count="2"', false);
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
