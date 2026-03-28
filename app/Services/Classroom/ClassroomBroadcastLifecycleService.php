<?php

namespace App\Services\Classroom;

use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Services\LiveKit\LiveKitParticipantService;
use Illuminate\Support\Facades\Log;
use Livekit\ParticipantInfo;

class ClassroomBroadcastLifecycleService
{
    public function __construct(
        private readonly LiveKitParticipantService $participantService
    ) {}

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
