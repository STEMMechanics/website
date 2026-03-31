<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ClassEnrolment;
use App\Models\User;
use App\Services\Classroom\ClassroomBroadcastLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livekit\ParticipantInfo;
use Livekit\TrackInfo;
use Livekit\TrackSource;
use Tests\TestCase;

class ClassroomBroadcastTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_camera_publish_marks_the_broadcast_as_camera_active(): void
    {
        $teacher = User::factory()->create();
        $classSession = ClassSession::query()->create([
            'title' => 'Timeout Test',
            'slug' => 'timeout-test',
            'room_name' => 'timeout-test',
            'live_broadcast_started_at' => now()->subMinute(),
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $participant = $this->mock(ParticipantInfo::class, function ($mock) use ($teacher): void {
            $mock->shouldReceive('getIdentity')->andReturn('class-test-user-1');
            $mock->shouldReceive('getName')->andReturn('Teacher');
            $mock->shouldReceive('getMetadata')->andReturn(json_encode([
                'role' => 'teacher',
                'username' => 'teacher.one',
                'name' => 'Teacher',
            ], JSON_THROW_ON_ERROR));
            $mock->shouldReceive('getAttributes')->andReturn([
                'app_user_role' => 'teacher',
                'app_user_id' => (string) $teacher->id,
                'app_user_username' => 'teacher.one',
                'app_user_name' => 'Teacher',
            ]);
        });

        $track = new TrackInfo([
            'source' => TrackSource::CAMERA,
        ]);

        $result = app(ClassroomBroadcastLifecycleService::class)->markCameraPublished($classSession, $participant, $track);

        $classSession->refresh();

        $this->assertTrue($result);
        $this->assertNotNull($classSession->live_broadcast_camera_started_at);
    }

    public function test_stale_livestream_without_camera_is_auto_ended(): void
    {
        $staleSession = ClassSession::query()->create([
            'title' => 'Stale Stream',
            'slug' => 'stale-stream',
            'room_name' => 'stale-stream',
            'live_broadcast_started_at' => now()->subMinutes(11),
        ]);

        $activeSession = ClassSession::query()->create([
            'title' => 'Active Stream',
            'slug' => 'active-stream',
            'room_name' => 'active-stream',
            'live_broadcast_started_at' => now()->subMinutes(11),
            'live_broadcast_camera_started_at' => now()->subMinute(),
        ]);

        Artisan::call('classroom:broadcasts:auto-end-stale');

        $staleSession->refresh();
        $activeSession->refresh();

        $this->assertNotNull($staleSession->live_broadcast_ended_at);
        $this->assertNull($staleSession->live_broadcast_ended_by_user_id);
        $this->assertNull($activeSession->live_broadcast_ended_at);
    }
}
