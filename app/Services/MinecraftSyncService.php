<?php

namespace App\Services;

use App\Jobs\DeliverMinecraftWebhook;
use App\Models\MinecraftAccount;
use App\Models\MinecraftBlacklistEntry;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
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

        $this->dispatch('account.sync', [
            'account' => [
                'uuid' => $account->uuid !== null ? (string) $account->uuid : null,
                'username' => (string) $account->username,
                'platform' => (string) $account->platform,
                'user_id' => $account->user_id !== null ? (string) $account->user_id : null,
                'is_whitelisted' => (bool) $account->is_whitelisted,
            ],
        ]);
    }

    public function removeAccount(MinecraftAccount $account): void
    {
        $this->dispatch('account.remove', [
            'account' => [
                'uuid' => $account->uuid !== null ? (string) $account->uuid : null,
                'username' => (string) $account->username,
                'platform' => (string) $account->platform,
            ],
        ]);
    }

    public function syncBlacklist(MinecraftBlacklistEntry $entry): void
    {
        $this->dispatch('blacklist.sync', [
            'blacklist' => [
                'uuid' => $entry->uuid !== null ? (string) $entry->uuid : null,
                'username' => (string) $entry->username,
                'reason' => (string) ($entry->reason ?? ''),
                'starts_at' => $entry->starts_at->toIso8601String(),
                'ends_at' => $entry->ends_at?->toIso8601String(),
                'is_permanent' => (bool) $entry->is_permanent,
            ],
        ]);
    }

    public function removeBlacklist(MinecraftBlacklistEntry $entry): void
    {
        $this->dispatch('blacklist.remove', [
            'blacklist' => [
                'uuid' => $entry->uuid !== null ? (string) $entry->uuid : null,
                'username' => (string) $entry->username,
            ],
        ]);
    }

    public function syncPenalty(MinecraftPenalty $penalty): void
    {
        $this->dispatch('player.penalty.created', [
            'external_id' => $penalty->external_id !== null ? (string) $penalty->external_id : null,
            'uuid' => (string) $penalty->uuid,
            'username' => (string) $penalty->username,
            'type' => (string) $penalty->type,
            'reason' => (string) ($penalty->reason ?? ''),
            'duration_seconds' => $penalty->duration_seconds,
            'occurred_at' => $penalty->started_at->toIso8601String(),
            'is_permanent' => (bool) $penalty->is_permanent,
            'by_uuid' => $penalty->by_uuid !== null ? (string) $penalty->by_uuid : null,
            'by_username' => $penalty->by_username !== null ? (string) $penalty->by_username : null,
        ]);
    }

    public function liftPenalty(MinecraftPenalty $penalty): void
    {
        $this->dispatch('player.penalty.lifted', [
            'external_id' => $penalty->external_id !== null ? (string) $penalty->external_id : null,
            'uuid' => (string) $penalty->uuid,
            'type' => (string) $penalty->type,
            'occurred_at' => ($penalty->lifted_at ?? now())->toIso8601String(),
            'by_uuid' => $penalty->lifted_by_uuid !== null ? (string) $penalty->lifted_by_uuid : null,
            'by_username' => $penalty->lifted_by_username !== null ? (string) $penalty->lifted_by_username : null,
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

    private function dispatch(string $event, array $payload): void
    {
        $this->queueDelivery($event, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function queueDelivery(string $event, array $payload, ?int $retriedFromId = null): ?MinecraftWebhookLog
    {
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
