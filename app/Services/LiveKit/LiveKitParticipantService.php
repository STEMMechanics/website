<?php

namespace App\Services\LiveKit;

use App\Models\ClassHelpRequest;
use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\User;
use Agence104\LiveKit\RoomServiceClient;
use Livekit\ParticipantPermission;
use Livekit\ParticipantInfo;
use Livekit\TrackSource;
use RuntimeException;
use Throwable;

class LiveKitParticipantService
{
    public function participantIdentity(User $user, ClassSession $classSession): string
    {
        return 'class-'.$classSession->id.'-user-'.$user->id;
    }

    /**
     * @return array<string, string>
     */
    public function participantAttributes(User $user, ClassSession $classSession, string $role, ?ClassHelpRequest $helpRequest = null): array
    {
        $attributes = [
            'class_session_id' => (string) $classSession->id,
            'class_session_slug' => (string) $classSession->slug,
            'class_session_room' => (string) $classSession->room_name,
            'app_user_id' => (string) $user->id,
            'app_user_username' => (string) $user->username,
            'app_user_name' => (string) $user->getName(),
            'app_user_role' => $role,
            'app_user_avatar_url' => (string) ($user->avatarImageUrl() ?? ''),
            'app_user_avatar_letters' => (string) $user->resolvedAvatarLetters(),
            'app_user_avatar_icon_class' => (string) ($user->resolvedAvatarIconClass() ?? ''),
            'app_user_avatar_background_color' => (string) $user->resolvedAvatarBackgroundColor(),
            'app_user_avatar_mode' => (string) $user->resolvedAvatarMode(),
        ];

        if ($helpRequest instanceof ClassHelpRequest) {
            $attributes['help_request_id'] = (string) $helpRequest->id;
            $attributes['help_request_type'] = (string) $helpRequest->type;
            $attributes['help_request_status'] = (string) $helpRequest->status;
            $attributes['help_request_requested_by_user_id'] = (string) ($helpRequest->requested_by_user_id ?? '');
        }

        return $attributes;
    }

    public function participantMetadata(User $user, ClassSession $classSession, string $role, ?ClassHelpRequest $helpRequest = null): string
    {
        return json_encode([
            'class_session_id' => (string) $classSession->id,
            'class_session_slug' => (string) $classSession->slug,
            'class_session_room' => (string) $classSession->room_name,
            'user_id' => (string) $user->id,
            'username' => (string) $user->username,
            'name' => (string) $user->getName(),
            'role' => $role,
            'avatar' => [
                'url' => (string) ($user->avatarImageUrl() ?? ''),
                'letters' => (string) $user->resolvedAvatarLetters(),
                'iconClass' => (string) ($user->resolvedAvatarIconClass() ?? ''),
                'backgroundColor' => (string) $user->resolvedAvatarBackgroundColor(),
                'mode' => (string) $user->resolvedAvatarMode(),
            ],
            'help_request' => $helpRequest instanceof ClassHelpRequest
                ? [
                    'id' => (string) $helpRequest->id,
                    'type' => (string) $helpRequest->type,
                    'status' => (string) $helpRequest->status,
                    'requested_by_user_id' => $helpRequest->requested_by_user_id ? (string) $helpRequest->requested_by_user_id : null,
                ]
                : null,
        ], JSON_THROW_ON_ERROR);
    }

    public function syncParticipantPermissions(
        ClassSession $classSession,
        User $user,
        string $role,
        ?ClassHelpRequest $helpRequest = null
    ): void {
        $permission = $this->buildPermission($role, $helpRequest);

        $this->roomServiceClient()->updateParticipant(
            (string) $classSession->room_name,
            $this->participantIdentity($user, $classSession),
            metadata: $this->participantMetadata($user, $classSession, $role, $helpRequest),
            permission: $permission,
            name: trim((string) $user->getName()) !== '' ? $user->getName() : null,
            attributes: $this->participantAttributes($user, $classSession, $role, $helpRequest)
        );
    }

    public function revokePublishPermission(ClassSession $classSession, User $user, string $role): void
    {
        $this->syncParticipantPermissions($classSession, $user, $role, null);
    }

    public function resolveParticipantUserId(ClassSession $classSession, string $participantIdentity): ?string
    {
        $identity = trim($participantIdentity);
        if ($identity === '') {
            return null;
        }

        try {
            $participant = $this->roomServiceClient()->getParticipant((string) $classSession->room_name, $identity);
        } catch (Throwable) {
            $participant = null;
        }

        if ($participant instanceof ParticipantInfo) {
            $resolved = $this->extractParticipantUserId($participant);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->findParticipantUserIdInRoom($classSession, function (ParticipantInfo $participant) use ($identity): bool {
            return $this->participantMatchesIdentity($participant, $identity);
        });
    }

    public function extractParticipantUserIdFromInfo(ParticipantInfo $participant): ?string
    {
        return $this->extractParticipantUserId($participant);
    }

    public function resolveParticipantUserIdByLabels(ClassSession $classSession, string $username, string $displayName = ''): ?string
    {
        $normalizedUsername = trim($username);
        $normalizedDisplayName = trim($displayName);

        if ($normalizedUsername === '' && $normalizedDisplayName === '') {
            return null;
        }

        return $this->findParticipantUserIdInRoom($classSession, function (ParticipantInfo $participant) use ($normalizedUsername, $normalizedDisplayName): bool {
            return $this->participantMatchesLabels($participant, $normalizedUsername, $normalizedDisplayName);
        });
    }

    private function buildPermission(string $role, ?ClassHelpRequest $helpRequest): ParticipantPermission
    {
        $permission = new ParticipantPermission();
        $permission->setCanSubscribe(true);
        $permission->setCanPublishData(true);
        $permission->setCanUpdateMetadata(true);

        if ($role === ClassEnrolment::ROLE_TEACHER) {
            $permission->setCanPublish(true);
            $permission->setCanPublishSources([
                TrackSource::CAMERA,
                TrackSource::MICROPHONE,
                TrackSource::SCREEN_SHARE,
                TrackSource::SCREEN_SHARE_AUDIO,
            ]);

            return $permission;
        }

        if ($helpRequest instanceof ClassHelpRequest && $helpRequest->isApproved()) {
            $permission->setCanPublish(true);
            $permission->setCanPublishSources(match ($helpRequest->type) {
                ClassHelpRequest::TYPE_CAMERA => [TrackSource::CAMERA],
                ClassHelpRequest::TYPE_SCREEN => [TrackSource::SCREEN_SHARE],
                default => [TrackSource::CAMERA],
            });
        } else {
            $permission->setCanPublish(false);
            $permission->setCanPublishSources([]);
        }

        return $permission;
    }

    /**
     * @return array<string, string>
     */
    private function participantInfoAttributes(ParticipantInfo $participant): array
    {
        $attributes = $participant->getAttributes();
        if ($attributes instanceof \Traversable) {
            return iterator_to_array($attributes);
        }

        if (is_array($attributes)) {
            return $attributes;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantInfoMetadata(ParticipantInfo $participant): array
    {
        $metadata = trim((string) $participant->getMetadata());
        if ($metadata === '') {
            return [];
        }

        try {
            $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function extractParticipantUserId(ParticipantInfo $participant): ?string
    {
        $attributes = $this->participantInfoAttributes($participant);
        $metadata = $this->participantInfoMetadata($participant);

        foreach ([
            $attributes['app_user_id'] ?? null,
            $attributes['user_id'] ?? null,
            $metadata['user_id'] ?? null,
        ] as $candidate) {
            $candidate = trim((string) ($candidate ?? ''));
            if ($candidate !== '' && preg_match('/^[0-9a-f-]{36}$/i', $candidate)) {
                return $candidate;
            }
        }

        $identity = trim((string) $participant->getIdentity());
        if ($identity !== '') {
            $pattern = '/-user-([0-9a-f-]{36})$/i';
            if (preg_match($pattern, $identity, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function participantMatchesIdentity(ParticipantInfo $participant, string $identity): bool
    {
        $participantIdentity = trim((string) $participant->getIdentity());
        if ($participantIdentity !== '' && strcasecmp($participantIdentity, $identity) === 0) {
            return true;
        }

        return false;
    }

    private function participantMatchesLabels(ParticipantInfo $participant, string $username, string $displayName): bool
    {
        $attributes = $this->participantInfoAttributes($participant);
        $metadata = $this->participantInfoMetadata($participant);

        $candidates = array_filter([
            $attributes['app_user_username'] ?? null,
            $attributes['username'] ?? null,
            $metadata['username'] ?? null,
            $attributes['app_user_name'] ?? null,
            $attributes['name'] ?? null,
            $metadata['name'] ?? null,
            method_exists($participant, 'getName') ? $participant->getName() : null,
        ], static fn ($value): bool => trim((string) $value) !== '');

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($username !== '' && strcasecmp($candidate, $username) === 0) {
                return true;
            }

            if ($displayName !== '' && strcasecmp($candidate, $displayName) === 0) {
                return true;
            }
        }

        return false;
    }

    private function findParticipantUserIdInRoom(ClassSession $classSession, callable $matches): ?string
    {
        try {
            $response = $this->roomServiceClient()->listParticipants((string) $classSession->room_name);
        } catch (Throwable) {
            return null;
        }

        foreach ($response->getParticipants() as $participant) {
            if (! $participant instanceof ParticipantInfo) {
                continue;
            }

            if (! $matches($participant)) {
                continue;
            }

            $resolved = $this->extractParticipantUserId($participant);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function roomServiceClient(): RoomServiceClient
    {
        $url = trim((string) config('livekit.url'));
        $apiHost = $this->apiHostFromUrl($url);
        $apiKey = trim((string) config('livekit.api_key'));
        $apiSecret = trim((string) config('livekit.api_secret'));

        if ($apiHost === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('LiveKit is not configured.');
        }

        return new RoomServiceClient($apiHost, $apiKey, $apiSecret);
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
}
