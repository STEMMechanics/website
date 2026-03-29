<?php

namespace App\Services\Classroom;

use App\Models\ClassEnrolment;
use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Collection;

class ClassroomStateService
{
    /**
     * @return array<string, mixed>
     */
    public function stateFor(User $viewer, ClassSession $classSession): array
    {
        $classSession->loadMissing(['forumCategory', 'createdBy', 'duplicatedFrom', 'chatMessages.user', 'workshop.classSession.forumCategory']);

        $role = $classSession->roleForUser($viewer);
        $canManage = $classSession->canManage($viewer);
        $pendingRequests = $this->serializeHelpRequests(
            $classSession->helpRequests()
                ->with(['user', 'approvedBy', 'requestedBy'])
                ->where('status', ClassHelpRequest::STATUS_PENDING)
                ->orderBy('created_at')
                ->get()
        );
        $activeRequest = $classSession->helpRequests()
            ->with(['user', 'approvedBy', 'requestedBy'])
            ->where('status', ClassHelpRequest::STATUS_APPROVED)
            ->orderByDesc('approved_at')
            ->first();
        $myRequest = $classSession->helpRequestForUser($viewer);
        $recentRequest = $classSession->helpRequests()
            ->with(['user', 'approvedBy', 'requestedBy'])
            ->whereIn('status', [ClassHelpRequest::STATUS_DONE, ClassHelpRequest::STATUS_REJECTED])
            ->orderByDesc('resolved_at')
            ->orderByDesc('created_at')
            ->first();
        $enrolments = $classSession->enrolments()
            ->with('user')
            ->orderBy('role')
            ->orderBy('created_at')
            ->get();
        $chatMessages = $classSession->chatMessages()
            ->with('user')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return [
            'viewer' => [
                'id' => (string) $viewer->id,
                'name' => (string) $viewer->getName(),
                'username' => (string) $viewer->username,
                'role' => $role,
                'canManage' => $canManage,
                'canRequestHelp' => false,
                'canRequestBroadcast' => $canManage,
            ],
            'classSession' => $this->serializeClassSession($classSession),
            'workshop' => $classSession->workshop ? $this->serializeWorkshop($classSession->workshop) : null,
            'enrolments' => $this->serializeEnrolments($enrolments),
            'chatMessages' => $this->serializeChatMessages($classSession, $chatMessages),
            'helpRequests' => [
                'pending' => $pendingRequests,
                'active' => $activeRequest ? $this->serializeHelpRequest($activeRequest) : null,
                'mine' => $myRequest ? $this->serializeHelpRequest($myRequest) : null,
                'recent' => $recentRequest ? $this->serializeHelpRequest($recentRequest) : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeClassSession(ClassSession $classSession): array
    {
        return [
            'id' => (string) $classSession->id,
            'slug' => (string) $classSession->slug,
            'title' => (string) $classSession->title,
            'roomName' => (string) $classSession->room_name,
            'summary' => (string) ($classSession->summary ?? ''),
            'instructionsHtml' => (string) ($classSession->instructions_html ?? ''),
            'accessGroupSlug' => (string) ($classSession->access_group_slug ?? ''),
            'forumCategoryId' => $classSession->forum_category_id ? (string) $classSession->forum_category_id : null,
            'forumCategoryName' => $classSession->forumCategory?->name,
            'forumCategoryUrl' => $classSession->forumCategory ? route('forum.category.show', $classSession->forumCategory->slug) : null,
            'liveChatEnabled' => (bool) $classSession->live_chat_enabled,
            'startsAt' => $classSession->starts_at?->toIso8601String(),
            'endsAt' => $classSession->ends_at?->toIso8601String(),
            'broadcastSchedule' => $this->serializeClassroomSchedule(
                $classSession->broadcastSchedule() !== []
                    ? $classSession->broadcastSchedule()
                    : ($classSession->workshop?->classroomSchedule() ?? [])
            ),
            'liveBroadcastStartedAt' => $classSession->live_broadcast_started_at?->toIso8601String(),
            'liveBroadcastEndedAt' => $classSession->live_broadcast_ended_at?->toIso8601String(),
            'liveBroadcastStartedByUserId' => $classSession->live_broadcast_started_by_user_id ? (string) $classSession->live_broadcast_started_by_user_id : null,
            'liveBroadcastEndedByUserId' => $classSession->live_broadcast_ended_by_user_id ? (string) $classSession->live_broadcast_ended_by_user_id : null,
            'isLiveBroadcastOpen' => $classSession->isLiveBroadcastOpen(),
            'createdByName' => $classSession->createdBy?->getName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeWorkshop(\App\Models\Workshop $workshop): array
    {
        $forumCategory = $workshop->classSession?->forumCategory ?? $workshop->classroomForumCategory;

        return [
            'id' => (string) $workshop->id,
            'title' => (string) $workshop->title,
            'content' => (string) ($workshop->content ?? ''),
            'registration' => (string) ($workshop->registration ?? ''),
            'ticketGroupSlug' => (string) ($workshop->ticket_group_slug ?? ''),
            'classroomSessions' => $this->serializeClassroomSchedule($workshop->classroomSchedule()),
            'courseUrl' => route('workshop.show', $workshop),
            'forumCategory' => $forumCategory ? [
                'id' => (string) $forumCategory->id,
                'slug' => (string) $forumCategory->slug,
                'name' => (string) $forumCategory->name,
                'url' => route('forum.category.show', $forumCategory->slug),
            ] : null,
        ];
    }

    /**
     * @param  array<int, array{starts_at: ?string, ends_at: ?string, label: string}>  $schedule
     * @return list<array<string, mixed>>
     */
    public function serializeClassroomSchedule(array $schedule): array
    {
        return collect($schedule)
            ->map(function (array $entry): array {
                $startsAt = $entry['starts_at'] ? \Illuminate\Support\Carbon::parse((string) $entry['starts_at']) : null;
                $endsAt = $entry['ends_at'] ? \Illuminate\Support\Carbon::parse((string) $entry['ends_at']) : null;

                return [
                    'startsAt' => $startsAt?->toIso8601String(),
                    'endsAt' => $endsAt?->toIso8601String(),
                    'label' => (string) ($entry['label'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeEnrolments(Collection $enrolments): array
    {
        return $enrolments
            ->map(function (ClassEnrolment $enrolment): array {
                return [
                    'id' => (string) $enrolment->id,
                    'userId' => (string) $enrolment->user_id,
                    'username' => (string) ($enrolment->user?->username ?? $enrolment->user?->getName() ?? ''),
                    'name' => (string) ($enrolment->user?->getName() ?? ''),
                    'role' => (string) $enrolment->role,
                    'isTeacher' => $enrolment->isTeacher(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeHelpRequests(Collection $requests): array
    {
        return $requests
            ->map(fn (ClassHelpRequest $request): array => $this->serializeHelpRequest($request))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeHelpRequest(ClassHelpRequest $helpRequest): array
    {
        return [
            'id' => (string) $helpRequest->id,
            'classSessionId' => (string) $helpRequest->class_session_id,
            'userId' => $helpRequest->user_id ? (string) $helpRequest->user_id : '',
            'targetParticipantIdentity' => (string) ($helpRequest->target_participant_identity ?? ''),
            'targetUsername' => (string) ($helpRequest->target_username ?? ''),
            'targetDisplayName' => (string) ($helpRequest->target_display_name ?? ''),
            'requestedForName' => (string) ($helpRequest->user?->getName() ?? $helpRequest->target_display_name ?? $helpRequest->target_username ?? ''),
            'requestedForUsername' => (string) ($helpRequest->user?->username ?? $helpRequest->target_username ?? ''),
            'requestedByUserId' => $helpRequest->requested_by_user_id ? (string) $helpRequest->requested_by_user_id : null,
            'requestedByName' => (string) ($helpRequest->requestedBy?->getName() ?? $helpRequest->requestedBy?->username ?? ''),
            'requestedByUsername' => (string) ($helpRequest->requestedBy?->username ?? ''),
            'type' => (string) $helpRequest->type,
            'typeLabel' => $helpRequest->typeLabel(),
            'status' => (string) $helpRequest->status,
            'statusLabel' => $helpRequest->statusLabel(),
            'approvedById' => $helpRequest->approved_by_user_id ? (string) $helpRequest->approved_by_user_id : null,
            'approvedByName' => $helpRequest->approvedBy?->getName(),
            'approvedAt' => $helpRequest->approved_at?->toIso8601String(),
            'resolvedAt' => $helpRequest->resolved_at?->toIso8601String(),
            'resolutionReason' => (string) ($helpRequest->resolution_reason ?? ''),
            'createdAt' => $helpRequest->created_at?->toIso8601String(),
            'isPending' => $helpRequest->isPending(),
            'isApproved' => $helpRequest->isApproved(),
            'isDone' => $helpRequest->isDone(),
            'isRejected' => $helpRequest->isRejected(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeChatMessages(ClassSession $classSession, Collection $messages): array
    {
        return $messages
            ->map(function ($message) use ($classSession): array {
                $user = $message->user;
                $isTeacher = $user?->isAdmin()
                    || $user?->hasGroup('minecraft-org')
                    || (($classSession->roleForUser($user) ?? null) === 'teacher');

                return [
                    'id' => (string) $message->id,
                    'classSessionId' => (string) $message->class_session_id,
                    'userId' => (string) $message->user_id,
                    'name' => (string) ($message->user?->username ?? $message->user?->getName() ?? ''),
                    'username' => (string) ($message->user?->username ?? ''),
                    'role' => $isTeacher ? 'teacher' : 'student',
                    'isTeacher' => $isTeacher,
                    'message' => (string) $message->display_message,
                    'displayMessage' => (string) $message->display_message,
                    'isBlocked' => (bool) $message->is_blocked,
                    'moderationReason' => $message->moderation_reason,
                    'moderationReasonLabel' => $message->moderation_reason_label,
                    'moderationReasonDetail' => $message->moderation_reason_detail,
                    'createdAt' => $message->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
