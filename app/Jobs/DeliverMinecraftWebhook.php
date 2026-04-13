<?php

namespace App\Jobs;

use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeliverMinecraftWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const BACKOFF_SECONDS = [30, 120, 300, 900, 1800, 3600];

    public int $tries = 1000000;

    public int $timeout = 20;

    public bool $failOnTimeout = false;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $event,
        public readonly array $payload,
        public readonly string $deliveryId,
        public readonly ?int $webhookLogId = null,
    ) {
        $this->onQueue('default');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }

    public function handle(): void
    {
        $log = $this->resolveLog();
        $url = trim((string) SiteOption::value('minecraft.server-webhook-url', SiteOption::defaultValue('minecraft.server-webhook-url')));
        $secret = trim((string) SiteOption::value('minecraft.webhook-secret', SiteOption::defaultValue('minecraft.webhook-secret')));
        $event = MinecraftSyncService::normalizeOutboundEventName($this->event);
        $deliveryId = $this->resolveDeliveryIdForAttempt();
        $payload = MinecraftSyncService::ensureOccurredAt($this->payload);
        $body = json_encode(array_merge(['event' => $event], $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        if ($log) {
            $log->direction = MinecraftWebhookLog::DIRECTION_OUTBOUND;
            $log->status = MinecraftWebhookLog::STATUS_PENDING;
            $log->event = $event;
            $log->delivery_id = $deliveryId;
            $log->method = 'POST';
            $log->target_url = $url !== '' ? $url : null;
            $log->request_headers = [
                'X-Minecraft-Timestamp' => $timestamp,
                'X-Minecraft-Signature' => null,
                'X-Minecraft-Delivery-Id' => $deliveryId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            $log->payload = $payload;
            $log->raw_body = $body === false ? null : $body;
            $log->attempt_count = ((int) $log->attempt_count) + 1;
            $log->last_attempted_at = now();
            $log->error_message = null;
            $log->response_status = null;
            $log->response_body = null;
            $log->save();
        }

        try {
            if ($body === false) {
                throw new \RuntimeException('Minecraft webhook payload encoding failed.');
            }

            if ($url === '' || $secret === '') {
                throw new \RuntimeException('STEMCraft webhook URL or secret is not configured.');
            }

            $signature = MinecraftSyncService::signPayload($body, $timestamp, $secret);
            if ($log) {
                $headers = is_array($log->request_headers) ? $log->request_headers : [];
                $headers['X-Minecraft-Signature'] = $signature;
                $log->request_headers = $headers;
                $log->save();
            }

            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders([
                    'X-Minecraft-Timestamp' => $timestamp,
                    'X-Minecraft-Signature' => $signature,
                    'X-Minecraft-Delivery-Id' => $deliveryId,
                ])
                ->withBody($body, 'application/json')
                ->send('POST', $url);

            if ($log) {
                $log->response_status = $response->status();
                $log->response_body = $response->body();
            }

            if (! $response->successful()) {
                if ($log) {
                    $log->status = MinecraftWebhookLog::STATUS_FAILED;
                    $log->failed_at = now();
                    $log->processed_at = now();
                    $log->error_message = 'Remote STEMCraft webhook endpoint returned HTTP '.$response->status().'.';
                    $log->save();
                }

                throw new RequestException($response);
            }

            if ($log) {
                $log->status = MinecraftWebhookLog::STATUS_DELIVERED;
                $log->delivered_at = now();
                $log->processed_at = now();
                $log->error_message = null;
                $log->save();
            }
        } catch (\Throwable $e) {
            if ($log) {
                $willRetry = $this->attempts() < $this->tries;
                $log->status = $willRetry
                    ? MinecraftWebhookLog::STATUS_PENDING
                    : MinecraftWebhookLog::STATUS_FAILED;
                $log->failed_at = $willRetry ? null : now();
                $log->processed_at = now();
                $log->error_message = $e->getMessage();
                $log->save();
            }

            throw $e;
        }
    }

    private function resolveDeliveryIdForAttempt(): string
    {
        return $this->attempts() > 1
            ? (string) Str::uuid()
            : $this->deliveryId;
    }

    private function resolveLog(): ?MinecraftWebhookLog
    {
        if (! Schema::hasTable('minecraft_webhook_logs')) {
            return null;
        }

        if ($this->webhookLogId !== null) {
            $log = MinecraftWebhookLog::query()->find($this->webhookLogId);
            if ($log) {
                return $log;
            }
        }

        return MinecraftWebhookLog::query()->firstOrNew([
            'delivery_id' => $this->deliveryId,
        ]);
    }
}
