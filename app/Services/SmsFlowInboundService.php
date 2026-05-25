<?php

namespace App\Services;

use App\Models\InboundSms;
use App\Models\SentSms;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class SmsFlowInboundService
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function storeIncomingPayload(array $payload, array $context = []): ?InboundSms
    {
        $topic = trim((string) ($payload['topic'] ?? ''));
        if ($topic !== 'sms.incoming') {
            return null;
        }

        $incomingId = trim((string) ($payload['incoming_id'] ?? ''));
        if ($incomingId === '') {
            throw new RuntimeException('SMSFlow inbound payload missing incoming_id.');
        }

        $originalMessageId = trim((string) ($payload['original_message_id'] ?? ''));
        $originator = trim((string) ($payload['originator'] ?? ''));
        $destination = trim((string) ($payload['destination'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        $receivedAt = $this->parseReceivedAt($payload['received_time'] ?? null);
        $optedOut = (bool) ($payload['is_opted_out'] ?? false);

        if ($originator === '') {
            throw new RuntimeException('SMSFlow inbound payload missing originator.');
        }

        if ($message === '') {
            throw new RuntimeException('SMSFlow inbound payload missing message.');
        }

        $sentSms = null;
        if ($originalMessageId !== '') {
            $sentSms = SentSms::query()
                ->where('provider_message_id', $originalMessageId)
                ->first();
        }

        $inboundSms = InboundSms::query()->firstOrNew([
            'incoming_id' => $incomingId,
        ]);

        $inboundSms->fill(array_filter([
            'provider' => 'smsflow',
            'topic' => $topic,
            'original_message_id' => $originalMessageId !== '' ? $originalMessageId : null,
            'sent_sms_id' => $sentSms?->id,
            'originator' => $originator,
            'destination' => $destination !== '' ? $destination : null,
            'message' => $message,
            'received_at' => $receivedAt,
            'opted_out' => $optedOut,
            'payload' => $payload + $context,
        ], static fn (mixed $value): bool => $value !== null));
        $inboundSms->save();

        return $inboundSms->refresh();
    }

    /**
     * @param  mixed  $value
     */
    private function parseReceivedAt(mixed $value): ?CarbonInterface
    {
        $receivedAt = trim((string) $value);
        if ($receivedAt === '') {
            return null;
        }

        return Carbon::parse($receivedAt);
    }
}
