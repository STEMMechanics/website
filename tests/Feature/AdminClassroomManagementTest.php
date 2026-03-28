<?php

namespace Tests\Feature;

use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_duplicate_and_delete_classrooms(): void
    {
        $admin = $this->createAdminUser();
        $teacher = User::factory()->create([
            'username' => 'teacher.one',
            'email' => 'teacher.one@example.com',
        ]);
        $student = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);
        $forumCategory = ForumCategory::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.classroom.store'), [
            'title' => 'Microbit Term 1',
            'slug' => 'microbit-term-1',
            'room_name' => 'microbit-term-1-room',
            'access_group_slug' => 'Microbit T1 2026',
            'forum_category_id' => $forumCategory->id,
            'summary' => 'Intro classroom',
            'instructions_html' => '<p>Welcome</p>',
            'live_chat_enabled' => 1,
            'teacher_identifiers' => $teacher->email,
            'student_identifiers' => $student->username,
        ]);

        $classSession = ClassSession::query()->where('slug', 'microbit-term-1')->firstOrFail();
        $response->assertRedirect(route('admin.classroom.edit', $classSession));

        $this->assertSame('Microbit Term 1', $classSession->title);
        $this->assertSame('microbit-term-1', $classSession->slug);
        $this->assertSame('microbit-term-1-room', $classSession->room_name);
        $this->assertSame('microbit-t1-2026', $classSession->access_group_slug);
        $this->assertSame($forumCategory->id, $classSession->forum_category_id);
        $this->assertTrue($classSession->live_chat_enabled);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $student->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classroom.edit', $classSession))
            ->assertOk()
            ->assertSeeText('Edit Classroom')
            ->assertSeeText('Current enrolments');

        $duplicateResponse = $this->actingAs($admin)->get(route('admin.classroom.create', [
            'duplicate_from' => $classSession->id,
        ]));

        $duplicateResponse
            ->assertOk()
            ->assertSeeText($teacher->username)
            ->assertSeeText($student->username)
            ->assertSeeText('Duplicating from');

        $this->actingAs($admin)
            ->put(route('admin.classroom.update', $classSession), [
                'title' => 'Microbit Term 1 Updated',
                'slug' => 'microbit-term-1',
                'room_name' => 'microbit-term-1-room',
                'access_group_slug' => 'Microbit T2 2026',
                'forum_category_id' => $forumCategory->id,
                'summary' => 'Updated classroom',
                'instructions_html' => '<p>Updated instructions</p>',
                'live_chat_enabled' => 0,
                'teacher_identifiers' => $student->email,
                'student_identifiers' => $teacher->email,
            ])
            ->assertRedirect(route('admin.classroom.edit', $classSession));

        $classSession->refresh();
        $this->assertSame('Microbit Term 1 Updated', $classSession->title);
        $this->assertSame('microbit-term-1', $classSession->slug);
        $this->assertSame('microbit-term-1-room', $classSession->room_name);
        $this->assertSame('microbit-t2-2026', $classSession->access_group_slug);
        $this->assertFalse($classSession->live_chat_enabled);

        $this->assertDatabaseMissing('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $student->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $teacher->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        $duplicateActionResponse = $this->actingAs($admin)->get(route('admin.classroom.duplicate', $classSession));
        $duplicateActionResponse->assertRedirect();

        $duplicated = ClassSession::query()->where('duplicated_from_class_session_id', $classSession->id)->latest('created_at')->firstOrFail();
        $this->assertSame($classSession->title.' (copy)', $duplicated->title);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $duplicated->id,
            'user_id' => $student->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.classroom.destroy', $classSession))
            ->assertRedirect(route('admin.classroom.index'));

        $this->assertDatabaseMissing('class_sessions', [
            'id' => $classSession->id,
        ]);
    }

    public function test_non_admin_cannot_access_classroom_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.classroom.index'))
            ->assertForbidden();
    }

    public function test_admin_classroom_index_lists_existing_classrooms(): void
    {
        $admin = $this->createAdminUser();
        $classSession = ClassSession::query()->create([
            'title' => 'Existing classroom',
            'slug' => 'existing-classroom',
            'room_name' => 'existing-classroom-room',
            'created_by_user_id' => $admin->id,
            'summary' => 'Existing classroom summary',
            'live_chat_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classroom.index'))
            ->assertOk()
            ->assertSeeText('Classrooms')
            ->assertSeeText($classSession->title)
            ->assertSee(route('admin.classroom.edit', $classSession), false)
            ->assertSee(route('class.show', $classSession), false);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
