<?php

namespace App\Services\LiveKit;

use App\Models\ClassSession;
use App\Services\Classroom\ClassroomBroadcastLifecycleService;
use Illuminate\Support\Facades\Log;
use Livekit\ParticipantInfo;
use Livekit\TrackInfo;
use Livekit\TrackSource;
use Livekit\WebhookEvent;

class LiveKitWebhookService
{
    public function __construct(
        private readonly ClassroomBroadcastLifecycleService $broadcastLifecycleService,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null
    ) {}

    public function receive(string $body, ?string $authorizationHeader): WebhookEvent
    {
        $receiver = new \Agence104\LiveKit\WebhookReceiver(
            $this->apiKey ?? config('livekit.api_key'),
            $this->apiSecret ?? config('livekit.api_secret')
        );

        return $receiver->receive($body, $authorizationHeader);
    }

    public function logEvent(WebhookEvent $event): void
    {
        $participant = $event->getParticipant();
        $room = $event->getRoom();
        $context = [
            'event' => (string) $event->getEvent(),
            'room_name' => $room?->getName(),
            'participant_identity' => $participant?->getIdentity(),
            'participant_name' => $participant?->getName(),
            'participant_metadata' => $participant?->getMetadata(),
            'participant_attributes' => $participant?->getAttributes(),
            'created_at' => $event->getCreatedAt(),
        ];

        $classSession = $this->resolveClassSession($room?->getName() ?? '');
        if ($classSession) {
            $context['class_session_id'] = (string) $classSession->id;
            $context['class_session_slug'] = (string) $classSession->slug;
        }

        if ($event->getTrack() instanceof TrackInfo) {
            $track = $event->getTrack();
            $context['track_sid'] = (string) $track->getSid();
            $context['track_source'] = $this->trackSourceLabel((int) $track->getSource());
            $context['track_type'] = (int) $track->getType();
        }

        if (in_array($event->getEvent(), ['participant_joined', 'participant_left', 'participant_connection_aborted'], true)) {
            $this->handleParticipantLifecycleEvent((string) $event->getEvent(), $room?->getName() ?? '', $participant);
            Log::info('LiveKit participant event', $context);

            return;
        }

        if ($event->getEvent() === 'track_published') {
            $this->handleTrackPublishedEvent($room?->getName() ?? '', $participant, $event->getTrack());
            Log::info('LiveKit track published event', $context);

            return;
        }

        if ($event->getEvent() === 'track_unpublished') {
            Log::info('LiveKit track unpublished event', $context);

            return;
        }

        Log::debug('LiveKit webhook event', $context);
    }

    private function handleParticipantLifecycleEvent(string $eventName, string $roomName, ?ParticipantInfo $participant): void
    {
        if ($participant === null) {
            return;
        }

        if (! in_array($eventName, ['participant_left', 'participant_connection_aborted'], true)) {
            return;
        }

        $classSession = $this->resolveClassSession($roomName);
        if (! $classSession instanceof ClassSession) {
            return;
        }

        $this->broadcastLifecycleService->handleParticipantLeft($classSession, $participant);
    }

    private function handleTrackPublishedEvent(string $roomName, ?ParticipantInfo $participant, ?TrackInfo $track): void
    {
        if ($participant === null || ! $track instanceof TrackInfo) {
            return;
        }

        if ((int) $track->getSource() !== TrackSource::CAMERA) {
            return;
        }

        $classSession = $this->resolveClassSession($roomName);
        if (! $classSession instanceof ClassSession) {
            return;
        }

        $this->broadcastLifecycleService->markCameraPublished($classSession, $participant, $track);
    }

    private function resolveClassSession(string $roomName): ?ClassSession
    {
        $roomName = trim($roomName);
        if ($roomName === '') {
            return null;
        }

        return ClassSession::query()
            ->where('room_name', $roomName)
            ->orWhere('slug', $roomName)
            ->first();
    }

    private function trackSourceLabel(int $source): string
    {
        return match ($source) {
            TrackSource::CAMERA => 'CAMERA',
            TrackSource::MICROPHONE => 'MICROPHONE',
            TrackSource::SCREEN_SHARE => 'SCREEN_SHARE',
            TrackSource::SCREEN_SHARE_AUDIO => 'SCREEN_SHARE_AUDIO',
            default => 'UNKNOWN',
        };
    }
}
