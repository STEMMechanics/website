<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClassroomClientErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_classroom_client_errors_are_logged(): void
    {
        $user = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $user->id,
            'slug' => 'microbit-t1-2026',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
            'access_group_slug' => 'microbit-t1-2026',
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($classSession, $user): bool {
                return $message === 'Classroom client error'
                    && ($context['class_session_id'] ?? null) === (string) $classSession->id
                    && ($context['user_id'] ?? null) === (string) $user->id
                    && ($context['message'] ?? null) === 'failed to publish track'
                    && ($context['source'] ?? null) === 'classroom.publish.camera';
            });

        $this->actingAs($user)
            ->postJson(route('class.client-error.store', $classSession), [
                'message' => 'failed to publish track',
                'source' => 'classroom.publish.camera',
                'stack' => 'Error: failed to publish track',
                'context' => [
                    'help_request_id' => 'request-1',
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
