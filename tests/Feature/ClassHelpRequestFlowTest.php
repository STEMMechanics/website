<?php

namespace Tests\Feature;

use App\Models\ClassEnrolment;
use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\LiveKit\LiveKitParticipantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ClassHelpRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_accept_a_teacher_requested_camera_broadcast(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacher.one',
            'email' => 'teacher.one@example.com',
        ]);

        $student = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $teacher->id,
            'slug' => $classSession->slug,
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => $classSession->slug,
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class.help-requests.store', $classSession), [
                'type' => ClassHelpRequest::TYPE_CAMERA,
                'target_user_id' => (string) $student->id,
            ])
            ->assertOk();

        $helpRequest = ClassHelpRequest::query()->where('class_session_id', $classSession->id)->firstOrFail();
        $this->assertSame(ClassHelpRequest::STATUS_PENDING, $helpRequest->status);

        $this->actingAs($student)
            ->postJson(route('class.help-requests.approve', [$classSession, $helpRequest]))
            ->assertOk()
            ->assertJsonPath('helpRequest.status', ClassHelpRequest::STATUS_APPROVED);

        $helpRequest->refresh();
        $this->assertSame(ClassHelpRequest::STATUS_APPROVED, $helpRequest->status);
        $this->assertSame((string) $student->id, (string) $helpRequest->approved_by_user_id);
        $this->assertNotNull($helpRequest->approved_at);
    }

    public function test_student_can_reject_a_teacher_requested_screen_share(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacher.two',
            'email' => 'teacher.two@example.com',
        ]);

        $student = User::factory()->create([
            'username' => 'student.two',
            'email' => 'student.two@example.com',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $teacher->id,
            'slug' => $classSession->slug,
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => $classSession->slug,
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class.help-requests.store', $classSession), [
                'type' => ClassHelpRequest::TYPE_SCREEN,
                'target_user_id' => (string) $student->id,
            ])
            ->assertOk();

        $helpRequest = ClassHelpRequest::query()->where('class_session_id', $classSession->id)->firstOrFail();
        $this->assertSame(ClassHelpRequest::STATUS_PENDING, $helpRequest->status);

        $this->actingAs($student)
            ->postJson(route('class.help-requests.revoke', [$classSession, $helpRequest]))
            ->assertOk()
            ->assertJsonPath('helpRequest.status', ClassHelpRequest::STATUS_REJECTED);

        $helpRequest->refresh();
        $this->assertSame(ClassHelpRequest::STATUS_REJECTED, $helpRequest->status);
        $this->assertNotNull($helpRequest->resolved_at);
    }

    public function test_student_can_mark_an_approved_screen_share_as_failed_with_a_reason(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacher.five',
            'email' => 'teacher.five@example.com',
        ]);

        $student = User::factory()->create([
            'username' => 'student.five',
            'email' => 'student.five@example.com',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $teacher->id,
            'slug' => $classSession->slug,
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => $classSession->slug,
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class.help-requests.store', $classSession), [
                'type' => ClassHelpRequest::TYPE_SCREEN,
                'target_user_id' => (string) $student->id,
            ])
            ->assertOk();

        $helpRequest = ClassHelpRequest::query()->where('class_session_id', $classSession->id)->firstOrFail();

        $this->actingAs($student)
            ->postJson(route('class.help-requests.approve', [$classSession, $helpRequest]))
            ->assertOk();

        $this->actingAs($student)
            ->postJson(route('class.help-requests.revoke', [$classSession, $helpRequest]), [
                'resolution_reason' => 'getDisplayMedia not supported on this device.',
            ])
            ->assertOk()
            ->assertJsonPath('helpRequest.status', ClassHelpRequest::STATUS_REJECTED)
            ->assertJsonPath('helpRequest.resolutionReason', 'getDisplayMedia not supported on this device.');

        $helpRequest->refresh();
        $this->assertSame(ClassHelpRequest::STATUS_REJECTED, $helpRequest->status);
        $this->assertSame('getDisplayMedia not supported on this device.', (string) $helpRequest->resolution_reason);
        $this->assertNotNull($helpRequest->resolved_at);
    }

    public function test_teacher_can_request_help_when_target_identity_needs_livekit_resolution(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacher.three',
            'email' => 'teacher.three@example.com',
        ]);

        $student = User::factory()->create([
            'username' => 'student.three',
            'email' => 'student.three@example.com',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $teacher->id,
            'slug' => $classSession->slug,
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => $classSession->slug,
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $participantService = $this->mock(LiveKitParticipantService::class, function (MockInterface $mock) use ($classSession, $student): void {
            $mock->shouldReceive('resolveParticipantUserId')
                ->once()
                ->withArgs(function (ClassSession $session, string $identity) use ($classSession): bool {
                    return (string) $session->id === (string) $classSession->id && $identity === 'livekit-participant-1';
                })
                ->andReturn((string) $student->id);
        });

        $this->actingAs($teacher)
            ->postJson(route('class.help-requests.store', $classSession), [
                'type' => ClassHelpRequest::TYPE_CAMERA,
                'target_participant_identity' => 'livekit-participant-1',
            ])
            ->assertOk()
            ->assertJsonPath('helpRequest.userId', (string) $student->id);

        $helpRequest = ClassHelpRequest::query()->where('class_session_id', $classSession->id)->firstOrFail();
        $this->assertSame((string) $student->id, (string) $helpRequest->user_id);
        $this->assertSame(ClassHelpRequest::STATUS_PENDING, $helpRequest->status);
    }

    public function test_teacher_can_request_help_when_livekit_cannot_resolve_the_target_user(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacher.four',
            'email' => 'teacher.four@example.com',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $teacher->id,
            'slug' => $classSession->slug,
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $this->mock(LiveKitParticipantService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolveParticipantUserId')->andReturnNull();
            $mock->shouldReceive('resolveParticipantUserIdByLabels')->andReturnNull();
        });

        $this->actingAs($teacher)
            ->postJson(route('class.help-requests.store', $classSession), [
                'type' => ClassHelpRequest::TYPE_CAMERA,
                'target_participant_identity' => 'class-test-user-11111111-1111-1111-1111-111111111111',
            ])
            ->assertOk()
            ->assertJsonPath('helpRequest.targetParticipantIdentity', 'class-test-user-11111111-1111-1111-1111-111111111111');

        $helpRequest = ClassHelpRequest::query()->where('class_session_id', $classSession->id)->firstOrFail();
        $this->assertNull($helpRequest->user_id);
        $this->assertSame('class-test-user-11111111-1111-1111-1111-111111111111', (string) $helpRequest->target_participant_identity);
        $this->assertSame(ClassHelpRequest::STATUS_PENDING, $helpRequest->status);
    }
}
