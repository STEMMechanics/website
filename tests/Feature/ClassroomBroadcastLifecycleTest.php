<?php

namespace Tests\Feature;

use App\Models\ClassEnrolment;
use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Classroom\ClassroomBroadcastLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livekit\ParticipantInfo;
use Tests\TestCase;

class ClassroomBroadcastLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_leaving_closes_the_active_broadcast_request(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacher.one',
            'email' => 'teacher.one@example.com',
        ]);

        $student = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $teacher->id,
            'slug' => 'microbit-t1-2026',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => 'microbit-t1-2026',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
            'access_group_slug' => 'microbit-t1-2026',
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $helpRequest = ClassHelpRequest::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $student->id,
            'target_participant_identity' => 'class-'.$classSession->id.'-user-'.(string) $student->id,
            'target_username' => $student->username,
            'target_display_name' => $student->getName(),
            'requested_by_user_id' => (string) $teacher->id,
            'type' => ClassHelpRequest::TYPE_CAMERA,
            'status' => ClassHelpRequest::STATUS_APPROVED,
            'approved_by_user_id' => (string) $teacher->id,
            'approved_at' => now(),
        ]);

        $participant = $this->mock(ParticipantInfo::class, function ($mock) use ($student, $classSession): void {
            $mock->shouldReceive('getIdentity')
                ->andReturn('class-'.$classSession->id.'-user-'.(string) $student->id);
            $mock->shouldReceive('getName')
                ->andReturn($student->getName());
            $mock->shouldReceive('getMetadata')
                ->andReturn(json_encode([
                    'username' => $student->username,
                    'name' => $student->getName(),
                ], JSON_THROW_ON_ERROR));
            $mock->shouldReceive('getAttributes')
                ->andReturn([
                    'app_user_id' => (string) $student->id,
                    'app_user_username' => $student->username,
                ]);
        });

        app(ClassroomBroadcastLifecycleService::class)->handleParticipantLeft($classSession, $participant);

        $helpRequest->refresh();

        $this->assertSame(ClassHelpRequest::STATUS_DONE, $helpRequest->status);
        $this->assertNotNull($helpRequest->resolved_at);
    }
}
