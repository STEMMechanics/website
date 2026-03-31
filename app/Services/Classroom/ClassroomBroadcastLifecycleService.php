<?php

namespace App\Services\Classroom;

use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;
use App\Services\LiveKit\LiveKitParticipantService;
use Illuminate\Support\Facades\Log;
use Livekit\DataPacket\Kind as DataPacketKind;
use Livekit\ParticipantInfo;
use Livekit\TrackInfo;
use Livekit\TrackSource;
use RuntimeException;

class ClassroomBroadcastLifecycleService
{
    public function __construct(
        private readonly LiveKitParticipantService $participantService,
        private readonly ClassroomStateService $stateService,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null
    ) {}

    public function startBroadcast(ClassSession $classSession, User $startedBy): void
    {
        $classSession->forceFill([
            'live_broadcast_started_at' => now(),
            'live_broadcast_camera_started_at' => null,
            'live_broadcast_ended_at' => null,
            'live_broadcast_started_by_user_id' => (string) $startedBy->id,
            'live_broadcast_ended_by_user_id' => null,
        ])->save();

        $this->pushBroadcastState($classSession);
    }

    public function endBroadcast(ClassSession $classSession, ?User $endedBy = null): void
    {
        if (! $classSession->isLiveBroadcastOpen()) {
            return;
        }

        $classSession->forceFill([
            'live_broadcast_ended_at' => now(),
            'live_broadcast_ended_by_user_id' => $endedBy ? (string) $endedBy->id : null,
        ])->save();

        $this->pushBroadcastState($classSession);
    }

    public function markCameraPublished(ClassSession $classSession, ParticipantInfo $participant, TrackInfo $track): bool
    {
        if (! $classSession->isLiveBroadcastOpen()) {
            return false;
        }

        if (! $this->participantCanManageBroadcast($classSession, $participant)) {
            return false;
        }

        if ((int) $track->getSource() !== TrackSource::CAMERA) {
            return false;
        }

        if ($classSession->live_broadcast_camera_started_at !== null) {
            return false;
        }

        $classSession->forceFill([
            'live_broadcast_camera_started_at' => now(),
        ])->save();

        $this->pushBroadcastState($classSession);

        return true;
    }

    public function expireStaleBroadcasts(int $timeoutMinutes = 10): int
    {
        $timeoutMinutes = max(1, $timeoutMinutes);
        $threshold = now()->subMinutes($timeoutMinutes);
        $endedCount = 0;

        ClassSession::query()
            ->whereNotNull('live_broadcast_started_at')
            ->whereNull('live_broadcast_ended_at')
            ->whereNull('live_broadcast_camera_started_at')
            ->where('live_broadcast_started_at', '<=', $threshold)
            ->orderBy('live_broadcast_started_at')
            ->get()
            ->each(function (ClassSession $classSession) use (&$endedCount, $timeoutMinutes): void {
                $endedCount++;
                $this->endBroadcast($classSession, null);
                Log::info('Classroom livestream expired because no camera was published in time.', [
                    'class_session_id' => (string) $classSession->id,
                    'class_session_slug' => (string) $classSession->slug,
                    'started_at' => $classSession->live_broadcast_started_at?->toIso8601String(),
                    'timeout_minutes' => $timeoutMinutes,
                ]);
            });

        return $endedCount;
    }

    public function handleParticipantLeft(ClassSession $classSession, ParticipantInfo $participant): void
    {
        $participantIdentity = trim((string) $participant->getIdentity());
        $participantUserId = $this->participantService->extractParticipantUserIdFromInfo($participant);
        $participantName = trim((string) $participant->getName());
        $participantUsername = $this->participantUsername($participant);

        $request = $classSession->helpRequests()
            ->whereIn('status', [ClassHelpRequest::STATUS_PENDING, ClassHelpRequest::STATUS_APPROVED])
            ->orderByDesc('created_at')
            ->get()
            ->first(function (ClassHelpRequest $helpRequest) use ($participantIdentity, $participantUserId, $participantName, $participantUsername): bool {
                return $this->requestMatchesParticipant(
                    $helpRequest,
                    $participantIdentity,
                    $participantUserId,
                    $participantName,
                    $participantUsername
                );
            });

        if (! $request instanceof ClassHelpRequest) {
            return;
        }

        $wasPending = $request->isPending();
        $wasApproved = $request->isApproved();

        $request->status = $wasApproved ? ClassHelpRequest::STATUS_DONE : ClassHelpRequest::STATUS_REJECTED;
        $request->resolved_at = now();
        if ($wasPending) {
            $request->approved_by_user_id = null;
        }
        $request->save();

        Log::info('Classroom broadcast request closed after participant left.', [
            'class_session_id' => (string) $classSession->id,
            'class_session_slug' => (string) $classSession->slug,
            'help_request_id' => (string) $request->id,
            'participant_identity' => $participantIdentity,
            'participant_user_id' => $participantUserId,
            'participant_name' => $participantName,
            'participant_username' => $participantUsername,
            'status' => $request->status,
        ]);
    }

    private function participantCanManageBroadcast(ClassSession $classSession, ParticipantInfo $participant): bool
    {
        $participantUserId = $this->participantService->extractParticipantUserIdFromInfo($participant);
        if ($participantUserId === null || $participantUserId === '') {
            return false;
        }

        $user = User::query()->find($participantUserId);
        if (! $user instanceof User) {
            return false;
        }

        return $classSession->canManage($user);
    }

    private function pushBroadcastState(ClassSession $classSession): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $url = trim((string) config('livekit.url'));
        $apiKey = trim((string) ($this->apiKey ?? config('livekit.api_key')));
        $apiSecret = trim((string) ($this->apiSecret ?? config('livekit.api_secret')));

        if ($url === '' || $apiKey === '' || $apiSecret === '') {
            return;
        }

        try {
            $classSession->loadMissing(['forumCategory', 'createdBy', 'duplicatedFrom', 'workshop.classSession.forumCategory']);
            $payload = json_encode([
                'type' => 'classroom-state',
                'state' => [
                    'classSession' => $this->stateService->serializeClassSession($classSession),
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->roomServiceClient()->sendData(
                (string) $classSession->room_name,
                $payload,
                DataPacketKind::RELIABLE,
                [],
                'classroom-state'
            );
        } catch (\Throwable $throwable) {
            Log::warning('Failed to push classroom broadcast state update.', [
                'class_session_id' => (string) $classSession->id,
                'class_session_slug' => (string) $classSession->slug,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function roomServiceClient(): \Agence104\LiveKit\RoomServiceClient
    {
        $url = trim((string) config('livekit.url'));
        $apiHost = $this->apiHostFromUrl($url);
        $apiKey = trim((string) ($this->apiKey ?? config('livekit.api_key')));
        $apiSecret = trim((string) ($this->apiSecret ?? config('livekit.api_secret')));

        if ($apiHost === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('LiveKit is not configured.');
        }

        return new \Agence104\LiveKit\RoomServiceClient($apiHost, $apiKey, $apiSecret);
    }

    private function apiHostFromUrl(string $url): string
    {
        $normalized = trim($url);
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'wss://')) {
            return 'https://'.substr($normalized, 6);
        }

        if (str_starts_with($normalized, 'ws://')) {
            return 'http://'.substr($normalized, 5);
        }

        return $normalized;
    }

    private function requestMatchesParticipant(
        ClassHelpRequest $helpRequest,
        string $participantIdentity,
        ?string $participantUserId,
        string $participantName,
        string $participantUsername
    ): bool {
        if ($participantIdentity !== '' && strcasecmp(trim((string) $helpRequest->target_participant_identity), $participantIdentity) === 0) {
            return true;
        }

        if ($participantUserId !== null && $participantUserId !== '' && (string) $helpRequest->user_id === $participantUserId) {
            return true;
        }

        if ($participantUsername !== '' && strcasecmp(trim((string) $helpRequest->target_username), $participantUsername) === 0) {
            return true;
        }

        if ($participantName !== '' && strcasecmp(trim((string) $helpRequest->target_display_name), $participantName) === 0) {
            return true;
        }

        return false;
    }

    private function participantUsername(ParticipantInfo $participant): string
    {
        $attributes = $participant->getAttributes();
        $metadata = trim((string) $participant->getMetadata());
        $decodedMetadata = [];

        if ($metadata !== '') {
            try {
                $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $decodedMetadata = $decoded;
                }
            } catch (\Throwable) {
                $decodedMetadata = [];
            }
        }

        $attributesArray = [];
        if ($attributes instanceof \Traversable) {
            $attributesArray = iterator_to_array($attributes);
        } elseif (is_array($attributes)) {
            $attributesArray = $attributes;
        }

        foreach ([
            $attributesArray['app_user_username'] ?? null,
            $attributesArray['username'] ?? null,
            $decodedMetadata['username'] ?? null,
        ] as $candidate) {
            $candidate = trim((string) ($candidate ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return trim((string) $participant->getName());
    }
}
