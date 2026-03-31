<?php

namespace Tests\Feature;

use App\Models\ClassChatMessage;
use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomChatModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_delete_an_individual_chat_message_for_everyone(): void
    {
        [$teacher, $student, $classSession] = $this->createClassroomParticipants();

        $message = $this->createChatMessage($classSession, $student, 'First message');

        $response = $this->actingAs($teacher)
            ->deleteJson(route('class.chat.destroy', [$classSession, $message]));

        $response->assertOk();
        $response->assertJsonCount(0, 'state.chatMessages');

        $message->refresh();
        $this->assertNotNull($message->deleted_at);
        $this->assertSame((string) $teacher->id, (string) $message->deleted_by_user_id);
    }

    public function test_teacher_can_clear_all_chat_messages(): void
    {
        [$teacher, $student, $classSession] = $this->createClassroomParticipants();

        $firstMessage = $this->createChatMessage($classSession, $student, 'First message');
        $secondMessage = $this->createChatMessage($classSession, $student, 'Second message');

        $response = $this->actingAs($teacher)
            ->deleteJson(route('class.chat.clear', $classSession));

        $response->assertOk();
        $response->assertJsonPath('deletedCount', 2);
        $response->assertJsonCount(0, 'state.chatMessages');

        $firstMessage->refresh();
        $secondMessage->refresh();

        $this->assertNotNull($firstMessage->deleted_at);
        $this->assertNotNull($secondMessage->deleted_at);
        $this->assertSame((string) $teacher->id, (string) $firstMessage->deleted_by_user_id);
        $this->assertSame((string) $teacher->id, (string) $secondMessage->deleted_by_user_id);
    }

    public function test_teacher_can_disable_and_reenable_chat_for_a_participant(): void
    {
        [$teacher, $student, $classSession] = $this->createClassroomParticipants();

        $enabledResponse = $this->actingAs($student)
            ->postJson(route('class.chat.store', $classSession), [
                'message' => 'Chat still works.',
            ]);

        $enabledResponse->assertOk();

        $disableResponse = $this->actingAs($teacher)
            ->putJson(route('class.chat.participant.update', [$classSession, $student]), [
                'disabled' => true,
            ]);

        $disableResponse->assertOk();
        $disableResponse->assertJsonPath('state.classSession.chatMutedUserIds.0', (string) $student->id);

        $this->assertDatabaseHas('class_chat_participant_states', [
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $student->id,
            'disabled_by_user_id' => (string) $teacher->id,
        ]);

        $blockedResponse = $this->actingAs($student)
            ->postJson(route('class.chat.store', $classSession), [
                'message' => 'This should be blocked.',
            ]);

        $blockedResponse->assertStatus(422);
        $blockedResponse->assertJsonPath('message', 'Chat has been disabled for you by the teacher.');

        $enableResponse = $this->actingAs($teacher)
            ->putJson(route('class.chat.participant.update', [$classSession, $student]), [
                'disabled' => false,
            ]);

        $enableResponse->assertOk();
        $enableResponse->assertJsonCount(0, 'state.classSession.chatMutedUserIds');

        $this->assertDatabaseMissing('class_chat_participant_states', [
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $student->id,
        ]);

        $allowedResponse = $this->actingAs($student)
            ->postJson(route('class.chat.store', $classSession), [
                'message' => 'Chat works again.',
            ]);

        $allowedResponse->assertOk();
        $allowedResponse->assertJsonPath('chatMessage.message', 'Chat works again.');
    }

    /**
     * @return array{0: User, 1: User, 2: ClassSession}
     */
    private function createClassroomParticipants(): array
    {
        $teacher = User::factory()->create();
        $student = User::factory()->create();

        $classSession = ClassSession::query()->create([
            'title' => 'Chat Moderation',
            'slug' => 'chat-moderation',
            'room_name' => 'chat-moderation',
            'access_group_slug' => 'chat-moderation',
            'live_chat_enabled' => true,
            'summary' => 'Chat moderation test',
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $student->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        return [$teacher, $student, $classSession];
    }

    private function createChatMessage(ClassSession $classSession, User $user, string $message): ClassChatMessage
    {
        return ClassChatMessage::query()->create([
            'class_session_id' => (string) $classSession->id,
            'user_id' => (string) $user->id,
            'raw_message' => $message,
            'display_message' => $message,
            'is_blocked' => false,
        ]);
    }
}
