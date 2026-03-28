<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassHelpRequestStoreRequest;
use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;
use App\Services\Classroom\ClassroomStateService;
use App\Services\LiveKit\LiveKitParticipantService;
use App\Services\LiveKit\LiveKitTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassHelpRequestController extends Controller
{
    public function __construct(
        private readonly ClassroomStateService $stateService,
        private readonly LiveKitTokenService $tokenService,
        private readonly LiveKitParticipantService $participantService
    ) {}

    public function index(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('view', $classSession);

        return response()->json($this->stateService->stateFor($request->user(), $classSession));
    }

    public function store(ClassHelpRequestStoreRequest $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('requestHelp', $classSession);

        $actor = $request->user();
        $type = (string) $request->validated('type');
        $targetIdentity = trim((string) $request->validated('target_participant_identity'));
        $targetUsername = trim((string) $request->validated('target_username'));
        $targetDisplayName = trim((string) $request->validated('target_display_name'));

        $targetUser = $this->resolveTargetUser($classSession, $request);
        $targetUserId = $targetUser instanceof User ? (string) $targetUser->id : null;

        if ($targetUser instanceof User && $classSession->roleForUser($targetUser) !== 'student') {
            return response()->json([
                'message' => 'That user cannot be requested for classroom broadcasting.',
                'state' => $this->stateService->stateFor($actor, $classSession),
            ], 422);
        }

        $activeRequest = $classSession->helpRequests()
            ->whereIn('status', [ClassHelpRequest::STATUS_PENDING, ClassHelpRequest::STATUS_APPROVED])
            ->orderByDesc('created_at')
            ->first();

        if ($activeRequest instanceof ClassHelpRequest && ! $this->helpRequestMatchesTarget($activeRequest, $targetUserId, $targetIdentity)) {
            return response()->json([
                'message' => 'Another broadcast request is already active.',
                'state' => $this->stateService->stateFor($actor, $classSession),
            ], 409);
        }

        if ($activeRequest instanceof ClassHelpRequest && $activeRequest->isApproved()) {
            return response()->json([
                'message' => 'That student is already broadcasting.',
                'state' => $this->stateService->stateFor($actor, $classSession),
            ], 409);
        }

        if ($activeRequest instanceof ClassHelpRequest && $activeRequest->isPending()) {
            $activeRequest->type = $type;
            $activeRequest->requested_by_user_id = $actor?->id;
            $activeRequest->target_participant_identity = $targetIdentity !== '' ? $targetIdentity : $activeRequest->target_participant_identity;
            $activeRequest->target_username = $targetUsername !== '' ? $targetUsername : $activeRequest->target_username;
            $activeRequest->target_display_name = $targetDisplayName !== '' ? $targetDisplayName : $activeRequest->target_display_name;
            if ($targetUserId !== null) {
                $activeRequest->user_id = $targetUserId;
            }
            $activeRequest->save();
            $helpRequest = $activeRequest;
        } else {
            $helpRequest = ClassHelpRequest::query()->create([
                'class_session_id' => $classSession->id,
                'user_id' => $targetUserId,
                'target_participant_identity' => $targetIdentity !== '' ? $targetIdentity : null,
                'target_username' => $targetUsername !== '' ? $targetUsername : null,
                'target_display_name' => $targetDisplayName !== '' ? $targetDisplayName : null,
                'requested_by_user_id' => $actor?->id,
                'type' => $type,
                'status' => ClassHelpRequest::STATUS_PENDING,
            ]);
        }

        return response()->json([
            'message' => sprintf(
                'Request sent to %s for %s.',
                $this->targetLabel($targetUser, $targetUsername, $targetDisplayName),
                $type === ClassHelpRequest::TYPE_CAMERA ? 'camera' : 'screen share'
            ),
            'helpRequest' => $this->stateService->serializeHelpRequest($helpRequest->fresh(['user', 'approvedBy', 'requestedBy'])),
            'state' => $this->stateService->stateFor($actor, $classSession),
        ]);
    }

    private function helpRequestMatchesTarget(ClassHelpRequest $helpRequest, ?string $targetUserId, string $targetIdentity): bool
    {
        if ($targetUserId !== null && (string) $helpRequest->user_id === $targetUserId) {
            return true;
        }

        if ($targetIdentity !== '' && trim((string) $helpRequest->target_participant_identity) !== '') {
            return strcasecmp(trim((string) $helpRequest->target_participant_identity), $targetIdentity) === 0;
        }

        return false;
    }

    private function targetLabel(?User $targetUser, string $targetUsername, string $targetDisplayName): string
    {
        if ($targetUser instanceof User) {
            return $targetUser->getName() ?: $targetUser->username;
        }

        if ($targetDisplayName !== '') {
            return $targetDisplayName;
        }

        if ($targetUsername !== '') {
            return $targetUsername;
        }

        return 'participant';
    }

    private function resolveTargetUser(ClassSession $classSession, ClassHelpRequestStoreRequest $request): ?User
    {
        $validated = $request->validated();

        $targetIdentity = trim((string) ($validated['target_participant_identity'] ?? ''));
        if ($targetIdentity !== '') {
            $resolvedUserId = $this->participantService->resolveParticipantUserId($classSession, $targetIdentity);
            if (is_string($resolvedUserId) && $resolvedUserId !== '') {
                $user = User::query()->find($resolvedUserId);
                if ($user instanceof User) {
                    return $user;
                }
            }
        }

        $targetUsername = trim((string) ($validated['target_username'] ?? ''));
        $targetDisplayName = trim((string) ($validated['target_display_name'] ?? ''));
        if ($targetUsername !== '' || $targetDisplayName !== '') {
            $resolvedUserId = $this->participantService->resolveParticipantUserIdByLabels(
                $classSession,
                $targetUsername,
                $targetDisplayName
            );

            if (is_string($resolvedUserId) && $resolvedUserId !== '') {
                $user = User::query()->find($resolvedUserId);
                if ($user instanceof User) {
                    return $user;
                }
            }
        }

        $targetUserId = trim((string) ($validated['target_user_id'] ?? ''));
        if ($targetUserId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $targetUserId)) {
            $user = User::query()->find($targetUserId);
            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }

    public function approve(Request $request, ClassSession $classSession, ClassHelpRequest $helpRequest): JsonResponse
    {
        $this->assertHelpRequestBelongsToSession($classSession, $helpRequest);

        $user = $request->user();
        $participantIdentity = $this->participantIdentityFromRequest($request);
        $canManage = $classSession->canManage($user);
        $canActAsTarget = (string) $helpRequest->user_id === (string) $user?->id
            || ($participantIdentity !== '' && strcasecmp(trim((string) $helpRequest->target_participant_identity), $participantIdentity) === 0);

        abort_unless($canManage || $canActAsTarget, 403);

        $activeRequest = $classSession->activeHelpRequest();

        if ($activeRequest instanceof ClassHelpRequest && $activeRequest->id !== $helpRequest->id) {
            $this->resolveHelpRequest($classSession, $activeRequest, $user, true);
        }

        $helpRequest->status = ClassHelpRequest::STATUS_APPROVED;
        $helpRequest->approved_by_user_id = $user?->id;
        $helpRequest->approved_at = now();
        $helpRequest->resolved_at = null;
        $helpRequest->save();

        $this->applyParticipantPermission($classSession, $helpRequest, ClassHelpRequest::STATUS_APPROVED);

        return response()->json([
            'message' => 'Broadcast request accepted.',
            'helpRequest' => $this->stateService->serializeHelpRequest($helpRequest->fresh(['user', 'approvedBy', 'requestedBy'])),
            'state' => $this->stateService->stateFor($user, $classSession),
        ]);
    }

    public function revoke(Request $request, ClassSession $classSession, ClassHelpRequest $helpRequest): JsonResponse
    {
        $this->assertHelpRequestBelongsToSession($classSession, $helpRequest);

        $user = $request->user();
        $participantIdentity = $this->participantIdentityFromRequest($request);
        $resolutionReason = trim((string) $request->input('resolution_reason', ''));
        $canManage = $classSession->canManage($user);
        $canActAsTarget = (string) $helpRequest->user_id === (string) $user?->id
            || ($participantIdentity !== '' && strcasecmp(trim((string) $helpRequest->target_participant_identity), $participantIdentity) === 0);

        if ($canManage || $canActAsTarget) {
            $wasApproved = $helpRequest->isApproved();

            $helpRequest->status = $wasApproved && $resolutionReason === ''
                ? ClassHelpRequest::STATUS_DONE
                : ClassHelpRequest::STATUS_REJECTED;
            $helpRequest->resolved_at = now();
            if (! $wasApproved) {
                $helpRequest->approved_by_user_id = null;
            }
            $helpRequest->resolution_reason = $resolutionReason !== '' ? $resolutionReason : null;
            $helpRequest->save();

            $this->applyParticipantPermission($classSession, $helpRequest, ClassHelpRequest::STATUS_DONE);

            return response()->json([
                'message' => $this->resolveRevokeMessage($wasApproved, $resolutionReason),
                'helpRequest' => $this->stateService->serializeHelpRequest($helpRequest->fresh(['user', 'approvedBy', 'requestedBy'])),
                'state' => $this->stateService->stateFor($user, $classSession),
            ]);
        }

        abort(403);

        $teacher = $user;
        $this->resolveHelpRequest($classSession, $helpRequest, $teacher, false);

        return response()->json([
            'message' => 'Broadcast request cancelled.',
            'helpRequest' => $this->stateService->serializeHelpRequest($helpRequest->fresh(['user', 'approvedBy', 'requestedBy'])),
            'state' => $this->stateService->stateFor($teacher, $classSession),
        ]);
    }

    private function resolveHelpRequest(ClassSession $classSession, ClassHelpRequest $helpRequest, ?\App\Models\User $actor, bool $replacedByAnother): void
    {
        $helpRequest->loadMissing(['user', 'approvedBy']);

        if ($helpRequest->isApproved()) {
            $helpRequest->status = ClassHelpRequest::STATUS_DONE;
            $helpRequest->resolved_at = now();
            $helpRequest->save();
            $this->applyParticipantPermission($classSession, $helpRequest, ClassHelpRequest::STATUS_DONE);
            return;
        }

        if ($helpRequest->isPending()) {
            $helpRequest->status = ClassHelpRequest::STATUS_REJECTED;
            $helpRequest->resolved_at = now();
            $helpRequest->approved_by_user_id = $actor?->id;
            $helpRequest->save();
            return;
        }

        if ($replacedByAnother) {
            return;
        }
    }

    private function applyParticipantPermission(ClassSession $classSession, ClassHelpRequest $helpRequest, string $state): void
    {
        $helpRequest->loadMissing(['user']);
        $targetUser = $helpRequest->user ?: $this->resolveHelpRequestTargetUser($classSession, $helpRequest);

        if (! $targetUser instanceof User) {
            return;
        }

        $role = $classSession->roleForUser($targetUser) ?? 'student';

        try {
            $this->participantService->syncParticipantPermissions(
                $classSession,
                $targetUser,
                $role,
                $state === ClassHelpRequest::STATUS_APPROVED ? $helpRequest : null
            );
        } catch (Throwable $throwable) {
            Log::warning('Could not update LiveKit participant permissions.', [
                'class_session_id' => (string) $classSession->id,
                'help_request_id' => (string) $helpRequest->id,
                'user_id' => (string) $targetUser->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function resolveHelpRequestTargetUser(ClassSession $classSession, ClassHelpRequest $helpRequest): ?User
    {
        $targetIdentity = trim((string) ($helpRequest->target_participant_identity ?? ''));
        if ($targetIdentity !== '') {
            $resolvedUserId = $this->participantService->resolveParticipantUserId($classSession, $targetIdentity);
            if (is_string($resolvedUserId) && $resolvedUserId !== '') {
                $user = User::query()->find($resolvedUserId);
                if ($user instanceof User) {
                    return $user;
                }
            }
        }

        $targetUserId = trim((string) ($helpRequest->user_id ?? ''));
        if ($targetUserId !== '') {
            $user = User::query()->find($targetUserId);
            if ($user instanceof User) {
                return $user;
            }
        }

        $targetUsername = trim((string) ($helpRequest->target_username ?? ''));
        $targetDisplayName = trim((string) ($helpRequest->target_display_name ?? ''));
        if ($targetUsername !== '' || $targetDisplayName !== '') {
            $resolvedUserId = $this->participantService->resolveParticipantUserIdByLabels($classSession, $targetUsername, $targetDisplayName);
            if (is_string($resolvedUserId) && $resolvedUserId !== '') {
                $user = User::query()->find($resolvedUserId);
                if ($user instanceof User) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function participantIdentityFromRequest(Request $request): string
    {
        return trim((string) $request->input('participant_identity', ''));
    }

    private function resolveRevokeMessage(bool $wasApproved, string $resolutionReason): string
    {
        if ($resolutionReason !== '') {
            return $resolutionReason;
        }

        return $wasApproved ? 'Broadcast stopped.' : 'Request rejected.';
    }

    private function assertHelpRequestBelongsToSession(ClassSession $classSession, ClassHelpRequest $helpRequest): void
    {
        abort_unless((string) $helpRequest->class_session_id === (string) $classSession->id, 404);
    }
}
