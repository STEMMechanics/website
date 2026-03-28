<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ClassHelpRequest;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Classroom\ClassroomStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_accounts_join_classrooms_as_students_not_teachers(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin.user',
            'email' => 'admin@example.com',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Admin Classroom Check',
            'slug' => 'admin-classroom-check',
            'room_name' => 'admin-classroom-check',
            'summary' => 'Role behaviour test',
        ]);

        $state = app(ClassroomStateService::class)->stateFor($admin, $classSession);

        $this->assertSame('student', $state['viewer']['role']);
        $this->assertTrue($state['viewer']['canManage']);
        $this->assertFalse($state['viewer']['canRequestHelp']);
        $this->assertTrue($state['viewer']['canRequestBroadcast']);
    }

    public function test_admin_accounts_can_request_a_camera_broadcast_for_a_student(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin.user',
            'email' => 'admin@example.com',
        ]);

        $student = User::factory()->create([
            'username' => 'student.user',
            'email' => 'student@example.com',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => 'admin',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Admin Classroom Check',
            'slug' => 'admin-classroom-check',
            'room_name' => 'admin-classroom-check',
            'access_group_slug' => 'admin',
            'summary' => 'Role behaviour test',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('class.help-requests.store', $classSession), [
                'type' => ClassHelpRequest::TYPE_CAMERA,
                'target_user_id' => (string) $student->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('class_help_requests', [
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $student->id,
            'requested_by_user_id' => (string) $admin->id,
            'status' => ClassHelpRequest::STATUS_PENDING,
            'type' => ClassHelpRequest::TYPE_CAMERA,
        ]);
    }
}
