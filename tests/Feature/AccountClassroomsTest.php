<?php

namespace Tests\Feature;

use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountClassroomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_menu_includes_classrooms_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee(route('account.course.index'), false);
    }

    public function test_account_classrooms_page_lists_accessible_classrooms_and_hides_inaccessible_ones(): void
    {
        $user = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);
        $heroMedia = Media::query()->create([
            'name' => 'course-listing-hero.png',
            'title' => 'Course Listing Hero',
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $user->id,
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $user->id,
            'slug' => 'microbit-t1-2026',
        ]);

        $groupClass = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
            'access_group_slug' => 'microbit-t1-2026',
            'summary' => 'Group classroom',
            'starts_at' => now()->addDay(),
        ]);

        $enrolledClass = ClassSession::query()->create([
            'title' => 'Special Workshop',
            'slug' => 'special-workshop',
            'room_name' => 'special-workshop',
            'hero_media_name' => $heroMedia->name,
            'summary' => 'Enrolment classroom',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $forumCategory = ForumCategory::query()->create([
            'name' => 'Course discussion',
            'slug' => 'course-discussion',
            'read_group_slug' => 'user',
            'write_group_slug' => 'user',
        ]);
        $enrolledClass->forceFill([
            'forum_category_id' => $forumCategory->id,
        ])->save();
        $forumAuthor = User::factory()->create();
        $this->createUnreadForumTopic($forumCategory, $forumAuthor, $user, 'Course update 1');
        $this->createUnreadForumTopic($forumCategory, $forumAuthor, $user, 'Course update 2');

        ClassEnrolment::query()->create([
            'class_session_id' => $enrolledClass->id,
            'user_id' => $user->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        ClassSession::query()->create([
            'title' => 'Hidden Classroom',
            'slug' => 'hidden-classroom',
            'room_name' => 'hidden-classroom',
            'access_group_slug' => 'different-group',
            'summary' => 'Should not be visible',
        ]);

        $response = $this->actingAs($user)->get(route('account.course.index'));

        $response->assertOk();
        $response->assertSeeText('Courses');
        $response->assertSeeText('Microbit T1');
        $response->assertSeeText('Special Workshop');
        $response->assertSee($heroMedia->url.'?lg', false);
        $response->assertSee('aria-label="2 unread discussion notifications"', false);
        $response->assertDontSeeText('Hidden Classroom');
        $response->assertSee(route('class.show', $groupClass), false);
        $response->assertSee(route('class.show', $enrolledClass), false);
        $response->assertSeeText('Upcoming courses');
        $response->assertSeeText('Active courses');
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
