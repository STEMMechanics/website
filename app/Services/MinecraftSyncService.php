<?php

namespace App\Services;

use Carbon\CarbonInterface;
use App\Jobs\DeliverMinecraftWebhook;
use App\Models\MinecraftAccount;
use App\Models\MinecraftBlacklistEntry;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MinecraftSyncService
{
    public function syncAccountState(MinecraftAccount $account): void
    {
        if (! $account->is_whitelisted) {
            $this->removeAccount($account);

            return;
        }

        $this->dispatch('player.profile.created', [
            'updated_at' => $account->updated_at?->copy()->utc()->toIso8601ZuluString() ?? now('UTC')->toIso8601ZuluString(),
            'player' => [
                'uuid' => $account->uuid !== null ? (string) $account->uuid : null,
                'username' => (string) $account->username,
                'platform' => (string) $account->platform,
                'user_id' => $account->user_id !== null ? (string) $account->user_id : null,
                'is_whitelisted' => (bool) $account->is_whitelisted,
                'updated_at' => $account->updated_at?->copy()->utc()->toIso8601ZuluString() ?? now('UTC')->toIso8601ZuluString(),
            ],
        ]);
    }

    public function removeAccount(MinecraftAccount $account): void
    {
        $this->dispatch('player.profile.deleted', [
            'updated_at' => $account->updated_at?->copy()->utc()->toIso8601ZuluString() ?? now('UTC')->toIso8601ZuluString(),
            'player' => [
                'uuid' => $account->uuid !== null ? (string) $account->uuid : null,
                'username' => (string) $account->username,
                'platform' => (string) $account->platform,
                'updated_at' => $account->updated_at?->copy()->utc()->toIso8601ZuluString() ?? now('UTC')->toIso8601ZuluString(),
            ],
        ]);
    }

    public function syncBlacklist(MinecraftBlacklistEntry $entry): void
    {
        $occurredAt = $entry->starts_at instanceof CarbonInterface
            ? $entry->starts_at
            : now();

        $this->dispatch('player.penalty.created', $this->legacyBlacklistPenaltyPayload($entry, $occurredAt));
    }

    public function removeBlacklist(MinecraftBlacklistEntry $entry): void
    {
        $occurredAt = $entry->lifted_at instanceof CarbonInterface
            ? $entry->lifted_at
            : now();

        $this->dispatch('player.penalty.updated', $this->legacyBlacklistPenaltyPayload(
            entry: $entry,
            occurredAt: $occurredAt,
            includeLiftedDetails: true,
        ));
    }

    public function syncPenalty(MinecraftPenalty $penalty): void
    {
        $this->dispatch('player.penalty.created', $this->penaltyPayload($penalty, $penalty->started_at ?? now()));
    }

    public function liftPenalty(MinecraftPenalty $penalty): void
    {
        $this->dispatch('player.penalty.updated', $this->penaltyPayload($penalty, $penalty->lifted_at ?? now()));
    }

    public function deletePenalty(MinecraftPenalty $penalty, ?CarbonInterface $occurredAt = null): void
    {
        $startedAt = $penalty->started_at instanceof CarbonInterface
            ? $penalty->started_at
            : now();
        $normalizedStartedAt = $startedAt->copy()->utc()->toIso8601ZuluString();
        $normalizedOccurredAt = ($occurredAt ?? now())->copy()->utc()->toIso8601ZuluString();
        $uuid = trim(strtolower((string) ($penalty->uuid ?? '')));

        $this->dispatch('player.penalty.deleted', [
            'penalty_key' => self::penaltyKey($uuid !== '' ? $uuid : null, $normalizedStartedAt),
            'uuid' => $uuid !== '' ? $uuid : null,
            'started_at' => $normalizedStartedAt,
            'type' => (string) $penalty->type,
            'occurred_at' => $normalizedOccurredAt,
            'updated_at' => $normalizedOccurredAt,
        ]);
    }

    public function redeliverLog(MinecraftWebhookLog $log): ?MinecraftWebhookLog
    {
        if ($log->direction !== MinecraftWebhookLog::DIRECTION_OUTBOUND) {
            return null;
        }

        return $this->queueDelivery(
            event: (string) $log->event,
            payload: is_array($log->payload) ? $log->payload : [],
            retriedFromId: $log->id,
        );
    }

    public static function signPayload(string $body, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp."\n".$body, $secret);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function ensureOccurredAt(array $payload): array
    {
        $eventId = trim((string) ($payload['event_id'] ?? ''));
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $eventId)) {
            $payload['event_id'] = (string) Str::uuid();
        }

        $occurredAt = self::normalizeOccurredAt($payload['occurred_at'] ?? null);
        $payload['occurred_at'] = $occurredAt;

        foreach (['player', 'account', 'blacklist'] as $nestedKey) {
            $nested = $payload[$nestedKey] ?? null;
            if (! is_array($nested)) {
                continue;
            }

            $nested['occurred_at'] = self::normalizeOccurredAt($nested['occurred_at'] ?? $occurredAt);
            $payload[$nestedKey] = $nested;
        }

        return $payload;
    }

    public static function penaltyKey(?string $uuid, CarbonInterface|string $startedAt): ?string
    {
        $normalizedUuid = trim(strtolower((string) $uuid));
        if ($normalizedUuid === '') {
            return null;
        }

        $normalizedStartedAt = $startedAt instanceof CarbonInterface
            ? $startedAt->copy()->utc()->toIso8601ZuluString()
            : self::normalizeOccurredAt($startedAt);

        return $normalizedUuid.'|'.$normalizedStartedAt;
    }

    private static function normalizeOccurredAt(mixed $value): string
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->utc()->toIso8601ZuluString();
            } catch (\Throwable) {
            }
        }

        return now('UTC')->toIso8601ZuluString();
    }

    /**
     * @return array<string, mixed>
     */
    private function penaltyPayload(MinecraftPenalty $penalty, CarbonInterface $occurredAt): array
    {
        $startedAt = $penalty->started_at instanceof CarbonInterface
            ? $penalty->started_at
            : now();
        $normalizedStartedAt = $startedAt->copy()->utc()->toIso8601ZuluString();
        $normalizedOccurredAt = $occurredAt->copy()->utc()->toIso8601ZuluString();
        $normalizedUuid = trim(strtolower((string) ($penalty->uuid ?? '')));

        return [
            'penalty_key' => self::penaltyKey($normalizedUuid !== '' ? $normalizedUuid : null, $normalizedStartedAt),
            'uuid' => $normalizedUuid !== '' ? $normalizedUuid : null,
            'username' => trim((string) ($penalty->username ?? '')) !== '' ? (string) $penalty->username : null,
            'type' => (string) $penalty->type,
            'reason' => (string) ($penalty->reason ?? ''),
            'duration_seconds' => $penalty->duration_seconds,
            'started_at' => $normalizedStartedAt,
            'occurred_at' => $normalizedOccurredAt,
            'is_permanent' => (bool) $penalty->is_permanent,
            'by_uuid' => $penalty->by_uuid !== null ? (string) $penalty->by_uuid : null,
            'by_username' => $penalty->by_username !== null ? (string) $penalty->by_username : null,
            'lifted_at' => $penalty->lifted_at?->copy()->utc()->toIso8601ZuluString(),
            'lifted_by_uuid' => $penalty->lifted_by_uuid !== null ? (string) $penalty->lifted_by_uuid : null,
            'lifted_by_username' => $penalty->lifted_by_username !== null ? (string) $penalty->lifted_by_username : null,
            'lift_reason' => $penalty->lift_reason !== null ? (string) $penalty->lift_reason : null,
            'deleted_at' => $penalty->deleted_at?->copy()->utc()->toIso8601ZuluString(),
            'updated_at' => $penalty->updated_at?->copy()->utc()->toIso8601ZuluString() ?? $normalizedOccurredAt,
        ];
    }

    /**
     * Emit modern penalty events for legacy blacklist records so plugin integrations
     * only need to support penalty contracts.
     *
     * @return array<string, mixed>
     */
    private function legacyBlacklistPenaltyPayload(
        MinecraftBlacklistEntry $entry,
        CarbonInterface $occurredAt,
        bool $includeLiftedDetails = false,
    ): array {
        $startedAt = $entry->starts_at instanceof CarbonInterface
            ? $entry->starts_at
            : now();
        $normalizedStartedAt = $startedAt->copy()->utc()->toIso8601ZuluString();
        $normalizedOccurredAt = $occurredAt->copy()->utc()->toIso8601ZuluString();
        $normalizedUuid = trim(strtolower((string) ($entry->uuid ?? $entry->account?->uuid ?? '')));
        $durationSeconds = null;

        if (
            ! $entry->is_permanent
            && $entry->starts_at instanceof CarbonInterface
            && $entry->ends_at instanceof CarbonInterface
            && $entry->ends_at->isAfter($entry->starts_at)
        ) {
            $durationSeconds = $entry->starts_at->diffInSeconds($entry->ends_at);
        }

        $liftedAt = null;
        if ($includeLiftedDetails && $entry->lifted_at instanceof CarbonInterface) {
            $liftedAt = $entry->lifted_at->copy()->utc()->toIso8601ZuluString();
        }

        return [
            'penalty_key' => self::penaltyKey($normalizedUuid !== '' ? $normalizedUuid : null, $normalizedStartedAt),
            'uuid' => $normalizedUuid !== '' ? $normalizedUuid : null,
            'username' => trim((string) $entry->username) !== '' ? (string) $entry->username : null,
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => (string) ($entry->reason ?? ''),
            'duration_seconds' => $durationSeconds,
            'started_at' => $normalizedStartedAt,
            'occurred_at' => $normalizedOccurredAt,
            'is_permanent' => (bool) $entry->is_permanent,
            'by_uuid' => null,
            'by_username' => null,
            'lifted_at' => $liftedAt,
            'lifted_by_uuid' => null,
            'lifted_by_username' => null,
            'lift_reason' => null,
            'deleted_at' => null,
            'updated_at' => $entry->updated_at?->copy()->utc()->toIso8601ZuluString() ?? $normalizedOccurredAt,
        ];
    }

    private function dispatch(string $event, array $payload): void
    {
        $this->queueDelivery($event, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function queueDelivery(string $event, array $payload, ?int $retriedFromId = null): ?MinecraftWebhookLog
    {
        $payload = self::ensureOccurredAt($payload);
        $url = trim((string) SiteOption::value('minecraft.server-webhook-url', SiteOption::defaultValue('minecraft.server-webhook-url')));
        $deliveryId = (string) Str::uuid();
        $log = $this->createOutboundLog(
            event: $event,
            payload: $payload,
            deliveryId: $deliveryId,
            targetUrl: $url !== '' ? $url : null,
            retriedFromId: $retriedFromId,
        );

        try {
            DeliverMinecraftWebhook::dispatch($event, $payload, $deliveryId, $log?->id);
        } catch (\Throwable $e) {
            if ($log) {
                $log->status = MinecraftWebhookLog::STATUS_FAILED;
                $log->error_message = $e->getMessage();
                $log->failed_at = now();
                $log->processed_at = now();
                $log->save();
            }

            Log::warning('Minecraft webhook sync exception.', [
                'event' => $event,
                'message' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createOutboundLog(
        string $event,
        array $payload,
        string $deliveryId,
        ?string $targetUrl,
        ?int $retriedFromId = null,
    ): ?MinecraftWebhookLog {
        if (! Schema::hasTable('minecraft_webhook_logs')) {
            return null;
        }

        return MinecraftWebhookLog::query()->create([
            'direction' => MinecraftWebhookLog::DIRECTION_OUTBOUND,
            'status' => MinecraftWebhookLog::STATUS_QUEUED,
            'event' => $event,
            'delivery_id' => $deliveryId,
            'method' => 'POST',
            'target_url' => $targetUrl,
            'payload' => $payload,
            'retried_from_id' => $retriedFromId,
        ]);
    }
}
