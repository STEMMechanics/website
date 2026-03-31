<?php

namespace App\Services\Classroom;

use App\Models\ClassChatParticipantState;
use App\Models\ClassChatMessage;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use App\Services\MinecraftMessageModerationService;
use App\Support\MinecraftMessageModerationResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClassroomChatService
{
    public function __construct(
        private readonly MinecraftMessageModerationService $moderationService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function store(User $user, ClassSession $classSession, string $message): array
    {
        $message = trim((string) $message);
        if ($message === '') {
            throw new RuntimeException('Chat message cannot be empty.');
        }

        if ($classSession->isChatMutedForUser($user)) {
            throw new RuntimeException('Chat has been disabled for you by the teacher.');
        }

        $moderation = $this->moderationService->inspect($message);
        $displayMessage = $this->displayMessage($message, $moderation);

        $chatMessage = ClassChatMessage::query()->create([
            'class_session_id' => $classSession->id,
            'user_id' => $user->id,
            'raw_message' => $message,
            'display_message' => $displayMessage,
            'is_blocked' => ! $moderation->pass,
            'moderation_reason' => $moderation->reason,
            'moderation_reason_label' => $moderation->reasonLabel,
            'moderation_reason_detail' => $moderation->reasonDetail,
        ]);

        return $this->serialize($chatMessage->fresh(['user']));
    }

    public function deleteMessage(ClassSession $classSession, ClassChatMessage $chatMessage, User $deletedBy): void
    {
        abort_unless((string) $chatMessage->class_session_id === (string) $classSession->id, 404);

        if ($chatMessage->deleted_at !== null) {
            return;
        }

        $chatMessage->forceFill([
            'deleted_at' => CarbonImmutable::now(),
            'deleted_by_user_id' => $deletedBy->id,
        ])->save();
    }

    public function clearMessages(ClassSession $classSession, User $clearedBy): int
    {
        $timestamp = CarbonImmutable::now();

        return ClassChatMessage::query()
            ->where('class_session_id', $classSession->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'deleted_by_user_id' => $clearedBy->id,
            ]);
    }

    public function setParticipantChatDisabled(ClassSession $classSession, User $targetUser, User $disabledBy, bool $disabled): void
    {
        if (! $classSession->canJoin($targetUser) && ! $classSession->canManage($targetUser)) {
            throw new RuntimeException('Participant is not part of this class session.');
        }

        DB::transaction(function () use ($classSession, $targetUser, $disabledBy, $disabled): void {
            $state = ClassChatParticipantState::query()->firstOrNew([
                'class_session_id' => $classSession->id,
                'user_id' => $targetUser->id,
            ]);

            if ($disabled) {
                $state->disabled_by_user_id = $disabledBy->id;
                $state->disabled_at = CarbonImmutable::now();
                $state->save();

                return;
            }

            if ($state->exists) {
                $state->delete();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ClassChatMessage $chatMessage): array
    {
        $user = $chatMessage->user;
        $isTeacher = $user?->isAdmin()
            || $user?->hasGroup('minecraft-org')
            || (($chatMessage->classSession?->roleForUser($user) ?? null) === 'teacher');

        return [
            'id' => (string) $chatMessage->id,
            'classSessionId' => (string) $chatMessage->class_session_id,
            'userId' => (string) $chatMessage->user_id,
            'identity' => 'class-'.$chatMessage->class_session_id.'-user-'.$chatMessage->user_id,
            'name' => (string) ($chatMessage->user?->getName() ?? $chatMessage->user?->username ?? ''),
            'username' => (string) ($chatMessage->user?->username ?? ''),
            'role' => $isTeacher ? 'teacher' : 'student',
            'isTeacher' => $isTeacher,
            'message' => (string) $chatMessage->display_message,
            'displayMessage' => (string) $chatMessage->display_message,
            'rawMessage' => (string) $chatMessage->raw_message,
            'isBlocked' => (bool) $chatMessage->is_blocked,
            'moderationReason' => $chatMessage->moderation_reason,
            'moderationReasonLabel' => $chatMessage->moderation_reason_label,
            'moderationReasonDetail' => $chatMessage->moderation_reason_detail,
            'createdAt' => $chatMessage->created_at?->toIso8601String(),
        ];
    }

    private function displayMessage(string $message, MinecraftMessageModerationResult $moderation): string
    {
        if ($moderation->pass) {
            return $message;
        }

        return trim((string) ($moderation->filteredMessage ?: $this->moderationService->blockedPlaceholder()));
    }
}
