<?php

namespace App\Services\LiveKit;

use App\Models\ClassEnrolment;
use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use RuntimeException;

class LiveKitTokenService
{
    public function __construct(
        private readonly LiveKitParticipantService $participantService
    ) {}

    /**
     * @return array{
     *     accessToken: string,
     *     roomName: string,
     *     participantIdentity: string,
     *     participantName: string,
     *     role: string,
     *     canPublish: bool,
     *     canSubscribe: bool,
     *     canPublishData: bool,
     *     canPublishSources: list<string>,
     *     helpRequest: array<string, mixed>|null
     * }
     */
    public function create(User $user, ClassSession $classSession): array
    {
        $role = $classSession->roleForUser($user);
        if ($role === null) {
            throw new RuntimeException('User is not allowed to join this class session.');
        }

        $helpRequest = $classSession->helpRequestForUser($user);
        $permissionState = $this->permissionState($role, $helpRequest);
        $participantIdentity = $this->participantService->participantIdentity($user, $classSession);
        $participantName = trim((string) $user->getName()) !== '' ? $user->getName() : 'Participant';
        $metadata = $this->participantService->participantMetadata($user, $classSession, $role, $helpRequest);
        $attributes = $this->participantService->participantAttributes($user, $classSession, $role, $helpRequest);

        $accessToken = (new AccessToken(
            config('livekit.api_key'),
            config('livekit.api_secret')
        ))->init((new AccessTokenOptions())
            ->setIdentity($participantIdentity)
            ->setName($participantName)
            ->setMetadata($metadata)
            ->setAttributes($attributes)
            ->setTtl(max(60, (int) config('livekit.token_ttl', 900)))
        );

        $grant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName((string) $classSession->room_name)
            ->setCanSubscribe(true)
            ->setCanPublishData(true);

        if ($permissionState['can_publish']) {
            $grant->setCanPublish(true);
            $grant->setCanPublishSources($this->publishSourcesForResponse($permissionState['can_publish_sources']));
        } else {
            $grant->setCanPublish(false);
            $grant->setCanPublishSources([]);
        }

        $jwt = $accessToken
            ->setGrant($grant)
            ->toJwt();

        return [
            'accessToken' => $jwt,
            'roomName' => (string) $classSession->room_name,
            'participantIdentity' => $participantIdentity,
            'participantName' => $participantName,
            'role' => $role,
            'canPublish' => (bool) $permissionState['can_publish'],
            'canSubscribe' => true,
            'canPublishData' => true,
            'canPublishSources' => $permissionState['can_publish_sources'],
            'helpRequest' => $helpRequest ? $this->serializeHelpRequest($helpRequest) : null,
        ];
    }

    /**
     * @return array{can_publish: bool, can_publish_sources: list<string>}
     */
    public function permissionState(string $role, ?ClassHelpRequest $helpRequest): array
    {
        if ($role === ClassEnrolment::ROLE_TEACHER) {
            return [
                'can_publish' => true,
                'can_publish_sources' => ['camera', 'microphone', 'screen_share', 'screen_share_audio'],
            ];
        }

        return [
            'can_publish' => true,
            'can_publish_sources' => ['camera', 'microphone', 'screen_share', 'screen_share_audio'],
        ];
    }

    /**
     * @param  list<string>  $sources
     * @return list<string>
     */
    private function publishSourcesForResponse(array $sources): array
    {
        return array_values(array_filter(array_map(static function (string $source): ?string {
            return match ($source) {
                'camera', 'microphone', 'screen_share', 'screen_share_audio' => $source,
                default => null,
            };
        }, $sources), static fn (?string $value): bool => $value !== null));
    }

    /**
     * @return list<string>
     */
    private function publishSourcesForHelpRequest(ClassHelpRequest $helpRequest): array
    {
        return match ($helpRequest->type) {
            ClassHelpRequest::TYPE_CAMERA => ['camera'],
            ClassHelpRequest::TYPE_SCREEN => ['screen_share'],
            default => ['camera'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHelpRequest(ClassHelpRequest $helpRequest): array
    {
        return [
            'id' => (string) $helpRequest->id,
            'type' => (string) $helpRequest->type,
            'typeLabel' => $helpRequest->typeLabel(),
            'status' => (string) $helpRequest->status,
            'statusLabel' => $helpRequest->statusLabel(),
            'userId' => (string) $helpRequest->user_id,
            'requestedFor' => (string) ($helpRequest->user?->getName() ?? $helpRequest->user?->username ?? ''),
            'requestedBy' => (string) ($helpRequest->requestedBy?->getName() ?? $helpRequest->requestedBy?->username ?? ''),
            'approvedById' => $helpRequest->approved_by_user_id ? (string) $helpRequest->approved_by_user_id : null,
            'approvedAt' => $helpRequest->approved_at?->toIso8601String(),
            'resolvedAt' => $helpRequest->resolved_at?->toIso8601String(),
        ];
    }
}
