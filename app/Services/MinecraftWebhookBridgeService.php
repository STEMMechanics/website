<?php

namespace App\Services;

use App\Models\MinecraftPlayerStat;
use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class MinecraftWebhookBridgeService
{
    /**
     * @return array{configured: bool, target: string}
     */
    public function connectionSummary(): array
    {
        $url = trim((string) SiteOption::value('minecraft.server-webhook-url', SiteOption::defaultValue('minecraft.server-webhook-url')));
        $secret = trim((string) SiteOption::value('minecraft.webhook-secret', SiteOption::defaultValue('minecraft.webhook-secret')));

        return [
            'configured' => $url !== '' && $secret !== '',
            'target' => $url !== '' ? $url : 'Not configured',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestStatus(int $timeoutSeconds = 15): array
    {
        $response = $this->request('server.status.request', [], $timeoutSeconds);
        $status = $response['status'] ?? null;

        if (! is_array($status)) {
            throw new RuntimeException('STEMCraft server status response did not include a status payload.');
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function requestCommand(string $command): array
    {
        $command = trim($command);
        if ($command === '') {
            throw new RuntimeException('Command is required.');
        }

        $response = $this->request('server.command.request', [
            'command' => $command,
        ]);
        $result = $response['result'] ?? null;

        if (! is_array($result)) {
            throw new RuntimeException('STEMCraft server command response did not include a result payload.');
        }

        return $result;
    }

    /**
     * @return array{
     *     period: string,
     *     period_days: int|null,
     *     stats: list<array<string, mixed>>,
     *     players: list<array<string, mixed>>,
     *     count: int,
     *     timestamp: string|null
     * }
     */
    public function requestPlayerStats(?string $uuid = null, ?string $username = null, ?string $statKey = null, ?string $period = null): array
    {
        $payload = [];
        $trimmedUuid = trim((string) $uuid);
        $trimmedUsername = trim((string) $username);
        $trimmedStatKey = trim((string) $statKey);
        $trimmedPeriod = trim((string) $period);

        if ($trimmedUuid !== '') {
            $payload['uuid'] = $trimmedUuid;
        } elseif ($trimmedUsername !== '') {
            $payload['username'] = $trimmedUsername;
        }

        if ($trimmedStatKey !== '') {
            $payload['stat_key'] = $trimmedStatKey;
        }

        if ($trimmedPeriod !== '') {
            $normalizedPeriod = MinecraftPlayerStat::resolvePeriod($trimmedPeriod);

            if ($normalizedPeriod === null) {
                throw new RuntimeException('Unsupported STEMCraft player stats period.');
            }

            $payload['period'] = $normalizedPeriod;
        }

        $response = $this->request('server.player-stats.request', $payload, 15);
        $players = $response['players'] ?? null;

        if (! is_array($players)) {
            throw new RuntimeException('STEMCraft player stats response did not include a players payload.');
        }

        $normalizedPlayers = [];
        foreach ($players as $player) {
            if (is_array($player)) {
                $normalizedPlayers[] = $player;
            }
        }

        return [
            'period' => MinecraftPlayerStat::normalizePeriod(is_string($response['period'] ?? null) ? (string) $response['period'] : ($payload['period'] ?? null)),
            'period_days' => is_numeric($response['period_days'] ?? null) ? (int) $response['period_days'] : null,
            'stats' => $this->normalizeStatDefinitions($response['stats'] ?? []),
            'players' => $normalizedPlayers,
            'count' => is_numeric($response['count'] ?? null) ? (int) $response['count'] : count($normalizedPlayers),
            'timestamp' => is_string($response['timestamp'] ?? null) ? (string) $response['timestamp'] : null,
        ];
    }

    /**
     * @return list<array{key: string, title: string, description: string}>
     */
    private function normalizeStatDefinitions(mixed $stats): array
    {
        if (! is_array($stats)) {
            return [];
        }

        $definitions = [];

        foreach ($stats as $stat) {
            if (! is_array($stat)) {
                continue;
            }

            $key = trim((string) ($stat['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $definitions[] = [
                'key' => $key,
                'title' => trim((string) ($stat['title'] ?? '')),
                'description' => trim((string) ($stat['description'] ?? '')),
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $event, array $payload = [], int $timeoutSeconds = 15): array
    {
        $url = trim((string) SiteOption::value('minecraft.server-webhook-url', SiteOption::defaultValue('minecraft.server-webhook-url')));
        $secret = trim((string) SiteOption::value('minecraft.webhook-secret', SiteOption::defaultValue('minecraft.webhook-secret')));
        $payload = MinecraftSyncService::ensureOccurredAt($payload);

        if ($url === '' || $secret === '') {
            throw new RuntimeException('STEMCraft webhook URL or secret is not configured.');
        }

        $deliveryId = (string) Str::uuid();
        $timestamp = (string) time();
        $body = json_encode(array_merge(['event' => $event], $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new RuntimeException('Minecraft webhook payload encoding failed.');
        }

        $signature = MinecraftSyncService::signPayload($body, $timestamp, $secret);
        $log = $this->createOutboundLog($event, $payload, $deliveryId, $url);

        if ($log) {
            $log->status = MinecraftWebhookLog::STATUS_PENDING;
            $log->request_headers = [
                'X-Minecraft-Timestamp' => $timestamp,
                'X-Minecraft-Signature' => $signature,
                'X-Minecraft-Delivery-Id' => $deliveryId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            $log->raw_body = $body;
            $log->attempt_count = ((int) $log->attempt_count) + 1;
            $log->last_attempted_at = now();
            $log->save();
        }

        try {
            $response = Http::timeout(max(1, $timeoutSeconds))
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
                throw new RuntimeException($this->responseErrorMessage($response->status(), $response->body()));
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new RuntimeException('STEMCraft webhook response was not valid JSON.');
            }

            if (($decoded['ok'] ?? null) !== true) {
                throw new RuntimeException('STEMCraft webhook response did not report success.');
            }

            if ($log) {
                $log->status = MinecraftWebhookLog::STATUS_DELIVERED;
                $log->delivered_at = now();
                $log->processed_at = now();
                $log->error_message = null;
                $log->save();
            }

            return $decoded;
        } catch (\Throwable $exception) {
            if ($log) {
                $log->status = MinecraftWebhookLog::STATUS_FAILED;
                $log->failed_at = now();
                $log->processed_at = now();
                $log->error_message = $exception->getMessage();
                $log->save();
            }

            throw $exception;
        }
    }

    private function responseErrorMessage(int $statusCode, string $body): string
    {
        $detail = trim($body);
        if ($detail === '') {
            return 'Remote STEMCraft webhook endpoint returned HTTP '.$statusCode.'.';
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $jsonMessage = trim((string) ($decoded['message'] ?? $decoded['error'] ?? ''));
            if ($jsonMessage !== '') {
                return 'Remote STEMCraft webhook endpoint returned HTTP '.$statusCode.': '.$jsonMessage;
            }
        }

        return 'Remote STEMCraft webhook endpoint returned HTTP '.$statusCode.': '.Str::limit(preg_replace('/\s+/', ' ', $detail) ?? $detail, 240);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createOutboundLog(string $event, array $payload, string $deliveryId, string $targetUrl): ?MinecraftWebhookLog
    {
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
        ]);
    }
}
