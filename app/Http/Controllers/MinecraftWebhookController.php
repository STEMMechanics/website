<?php

namespace App\Http\Controllers;

use App\Models\MinecraftAccount;
use App\Models\MinecraftBlacklistEntry;
use App\Models\MinecraftEventLog;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftSession;
use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MinecraftWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $decodedPayload = json_decode($rawBody, true);
        $timestamp = trim((string) $request->header('X-Minecraft-Timestamp', ''));
        $signature = trim((string) $request->header('X-Minecraft-Signature', ''));
        $deliveryId = trim((string) $request->header('X-Minecraft-Delivery-Id', ''));
        $secret = trim((string) SiteOption::value('minecraft.webhook-secret', SiteOption::defaultValue('minecraft.webhook-secret')));
        $log = $this->createInboundLog($request, $deliveryId, is_array($decodedPayload) ? $decodedPayload : null, $rawBody);

        try {
            if ($secret === '' || ! $this->signatureIsValid($rawBody, $timestamp, $signature, $secret)) {
                return $this->finalizeInboundLog(
                    $log,
                    response()->json(['ok' => false, 'error' => 'invalid_signature'], 403),
                    MinecraftWebhookLog::STATUS_REJECTED,
                    'Invalid or missing STEMCraft webhook signature.',
                );
            }

            if (! $this->deliveryIsFresh($deliveryId, $rawBody, $timestamp)) {
                return $this->finalizeInboundLog(
                    $log,
                    response()->json(['ok' => false, 'error' => 'replay_detected'], 409),
                    MinecraftWebhookLog::STATUS_DUPLICATE,
                    'Replay detected or delivery id missing.',
                );
            }

            $validated = $request->validate([
                'event' => ['required', 'string', Rule::in([
                    'player.login',
                    'player.logout',
                    'player.profile.updated',
                    'player.teleport',
                    'player.gamemode.changed',
                    'player.chat',
                    'player.penalty.created',
                    'player.penalty.lifted',
                    'server.sync.request',
                ])],
            ]);
            if ($log) {
                $log->event = (string) $validated['event'];
                $log->save();
            }

            $response = match ((string) $validated['event']) {
                'player.login' => $this->handleLogin($request),
                'player.logout' => $this->handleLogout($request),
                'player.profile.updated' => $this->handleProfileUpdated($request),
                'player.teleport', 'player.gamemode.changed' => $this->handleGameplayEvent($request),
                'player.chat' => $this->handleChatEvent($request),
                'player.penalty.created' => $this->handlePenaltyCreated($request),
                'player.penalty.lifted' => $this->handlePenaltyLifted($request),
                'server.sync.request' => $this->handleServerSyncRequest($request),
                default => response()->json(['ok' => false], 422),
            };
            $this->storeInboundEvent(
                event: (string) $validated['event'],
                payload: is_array($decodedPayload) ? $decodedPayload : null,
            );

            $status = $response->getData(true)['ignored'] ?? false
                ? MinecraftWebhookLog::STATUS_IGNORED
                : MinecraftWebhookLog::STATUS_RECEIVED;

            return $this->finalizeInboundLog($log, $response, $status);
        } catch (ValidationException $e) {
            $response = response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

            $this->finalizeInboundLog($log, $response, MinecraftWebhookLog::STATUS_REJECTED, 'Validation failed.');

            throw $e;
        } catch (\Throwable $e) {
            if ($log) {
                $log->status = MinecraftWebhookLog::STATUS_FAILED;
                $log->error_message = $e->getMessage();
                $log->response_status = 500;
                $log->response_body = $e->getMessage();
                $log->processed_at = now();
                $log->failed_at = now();
                $log->save();
            }

            throw $e;
        }
    }

    private function handleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'occurred_at' => ['nullable', 'date'],
            'session_uuid' => ['nullable', 'uuid'],
            'server_name' => ['nullable', 'string', 'max:100'],
        ]);

        $account = $this->findAccountForPlayer(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
        );
        if (! $account) {
            Log::info('Minecraft login webhook received for unknown UUID.', ['uuid' => $validated['uuid']]);

            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $account->platform = strtolower((string) $validated['platform']);
        $account->uuid = strtolower((string) $validated['uuid']);
        $account->username = trim((string) $validated['username']);
        $account->last_login_at = $occurredAt;
        $account->last_seen_at = $occurredAt;
        $account->save();

        $session = null;
        $sessionUuid = trim((string) ($validated['session_uuid'] ?? ''));
        if ($sessionUuid !== '') {
            $session = MinecraftSession::query()->firstOrNew(['session_uuid' => $sessionUuid]);
        }

        $session ??= new MinecraftSession();
        $session->minecraft_account_id = (int) $account->id;
        $session->session_uuid = $sessionUuid !== '' ? $sessionUuid : null;
        $session->server_name = trim((string) ($validated['server_name'] ?? '')) ?: null;
        $session->logged_in_at = $occurredAt;
        $session->logged_out_at = null;
        $session->duration_seconds = null;
        $session->save();

        return response()->json(['ok' => true]);
    }

    private function handleLogout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'occurred_at' => ['nullable', 'date'],
            'session_uuid' => ['nullable', 'uuid'],
            'server_name' => ['nullable', 'string', 'max:100'],
        ]);

        $account = $this->findAccountForPlayer(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
        );
        if (! $account) {
            Log::info('Minecraft logout webhook received for unknown UUID.', ['uuid' => $validated['uuid']]);

            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $account->platform = strtolower((string) $validated['platform']);
        $account->uuid = strtolower((string) $validated['uuid']);
        $account->username = trim((string) $validated['username']);
        $account->last_logout_at = $occurredAt;
        $account->last_seen_at = $occurredAt;
        $account->save();

        $sessionUuid = trim((string) ($validated['session_uuid'] ?? ''));
        $sessionQuery = MinecraftSession::query()->where('minecraft_account_id', $account->id)->whereNull('logged_out_at');
        if ($sessionUuid !== '') {
            $sessionQuery->where('session_uuid', $sessionUuid);
        }

        $session = $sessionQuery->orderByDesc('logged_in_at')->first();
        if ($session) {
            $session->logged_out_at = $occurredAt;
            $duration = max(0, $session->logged_in_at->diffInSeconds($occurredAt));
            $session->duration_seconds = $duration;
            if (trim((string) ($validated['server_name'] ?? '')) !== '') {
                $session->server_name = trim((string) $validated['server_name']);
            }
            $session->save();
        }

        return response()->json(['ok' => true]);
    }

    private function handleProfileUpdated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $account = $this->findAccountForPlayer(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
        );
        if (! $account) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $account->platform = strtolower((string) $validated['platform']);
        $account->uuid = strtolower((string) $validated['uuid']);
        $account->username = trim((string) $validated['username']);
        $account->last_seen_at = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $account->save();

        return response()->json(['ok' => true]);
    }

    private function handleGameplayEvent(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
            'username' => ['nullable', 'string', 'max:80'],
            'platform' => ['nullable', Rule::in(MinecraftAccount::PLATFORMS)],
            'occurred_at' => ['nullable', 'date'],
            'server_name' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['ok' => true]);
    }

    private function handleChatEvent(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
            'username' => ['nullable', 'string', 'max:80'],
            'platform' => ['nullable', Rule::in(MinecraftAccount::PLATFORMS)],
            'message' => ['required', 'string'],
            'occurred_at' => ['nullable', 'date'],
            'server_name' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['ok' => true]);
    }

    private function handlePenaltyCreated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['nullable', 'string', 'max:120'],
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(MinecraftPenalty::TYPES)],
            'reason' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'occurred_at' => ['nullable', 'date'],
            'is_permanent' => ['nullable', 'boolean'],
            'by_uuid' => ['nullable', 'string', 'max:64'],
            'by_username' => ['nullable', 'string', 'max:80'],
        ]);

        $uuid = strtolower((string) $validated['uuid']);
        $account = MinecraftAccount::query()->where('uuid', $uuid)->first();
        $startedAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $type = (string) $validated['type'];
        $durationSeconds = isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null;
        $isPermanent = (bool) ($validated['is_permanent'] ?? false);

        $penalty = ! empty($validated['external_id'])
            ? MinecraftPenalty::query()->firstOrNew(['external_id' => (string) $validated['external_id']])
            : new MinecraftPenalty();

        if ($penalty->exists) {
            $existingStartedAt = $penalty->started_at instanceof Carbon ? $penalty->started_at : null;
            $existingLiftedAt = $penalty->lifted_at instanceof Carbon ? $penalty->lifted_at : null;

            if ($existingLiftedAt !== null && ! $startedAt->isAfter($existingLiftedAt)) {
                return response()->json(['ok' => true, 'ignored' => true]);
            }

            if ($existingStartedAt !== null && $startedAt->isBefore($existingStartedAt)) {
                return response()->json(['ok' => true, 'ignored' => true]);
            }
        }

        $penalty->minecraft_account_id = $account?->id;
        $penalty->uuid = $uuid;
        $penalty->username = trim((string) $validated['username']);
        $penalty->type = $type;
        $penalty->reason = trim((string) ($validated['reason'] ?? '')) ?: null;
        $penalty->duration_seconds = $durationSeconds;
        $penalty->started_at = $startedAt;
        $penalty->is_permanent = $isPermanent;
        $penalty->by_uuid = trim((string) ($validated['by_uuid'] ?? '')) ?: null;
        $penalty->by_username = trim((string) ($validated['by_username'] ?? '')) ?: null;
        $penalty->lifted_at = null;
        $penalty->lifted_by_uuid = null;
        $penalty->lifted_by_username = null;
        $penalty->ends_at = match ($type) {
            MinecraftPenalty::TYPE_KICK => $startedAt,
            default => $isPermanent || $durationSeconds === null ? null : $startedAt->copy()->addSeconds($durationSeconds),
        };
        $penalty->save();

        return response()->json(['ok' => true]);
    }

    private function handlePenaltyLifted(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['nullable', 'string', 'max:120'],
            'uuid' => ['required_without:external_id', 'string', 'max:64'],
            'type' => ['required_without:external_id', Rule::in(MinecraftPenalty::TYPES)],
            'occurred_at' => ['nullable', 'date'],
            'by_uuid' => ['nullable', 'string', 'max:64'],
            'by_username' => ['nullable', 'string', 'max:80'],
        ]);

        $query = MinecraftPenalty::query();
        if (! empty($validated['external_id'])) {
            $query->where('external_id', (string) $validated['external_id']);
        } else {
            $query->where('uuid', strtolower((string) $validated['uuid']))
                ->where('type', (string) $validated['type'])
                ->whereNull('lifted_at')
                ->orderByDesc('started_at');
        }

        $penalty = $query->first();
        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        if (! $penalty) {
            if (empty($validated['external_id'])) {
                return response()->json(['ok' => true, 'ignored' => true]);
            }

            $penalty = new MinecraftPenalty();
            $penalty->external_id = (string) $validated['external_id'];
            $penalty->uuid = trim(strtolower((string) ($validated['uuid'] ?? ''))) ?: null;
            $penalty->username = '';
            $penalty->type = (string) ($validated['type'] ?? MinecraftPenalty::TYPE_BAN);
            $penalty->started_at = $occurredAt;
            $penalty->is_permanent = false;
        }

        if ($penalty->lifted_at instanceof Carbon && ! $occurredAt->isAfter($penalty->lifted_at)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $penalty->lifted_at = $occurredAt;
        $penalty->lifted_by_uuid = trim((string) ($validated['by_uuid'] ?? '')) ?: null;
        $penalty->lifted_by_username = trim((string) ($validated['by_username'] ?? '')) ?: null;
        $penalty->save();

        return response()->json(['ok' => true]);
    }

    private function handleServerSyncRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_name' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:120'],
            'plugin_version' => ['nullable', 'string', 'max:80'],
        ]);

        $asOf = now();

        $accounts = MinecraftAccount::query()
            ->where('is_whitelisted', true)
            ->orderBy('id')
            ->get()
            ->map(function (MinecraftAccount $account): array {
                return [
                    'uuid' => $account->uuid !== null ? strtolower((string) $account->uuid) : null,
                    'username' => (string) $account->username,
                    'platform' => (string) $account->platform,
                    'user_id' => $account->user_id !== null ? (string) $account->user_id : null,
                    'is_whitelisted' => true,
                ];
            })
            ->values()
            ->all();

        $penalties = MinecraftPenalty::query()
            ->whereIn('type', [MinecraftPenalty::TYPE_BAN, MinecraftPenalty::TYPE_MUTE])
            ->whereNull('lifted_at')
            ->where(function ($query) use ($asOf): void {
                $query
                    ->where('is_permanent', true)
                    ->orWhere('ends_at', '>', $asOf);
            })
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->map(function (MinecraftPenalty $penalty): array {
                return [
                    'external_id' => $penalty->external_id !== null ? (string) $penalty->external_id : null,
                    'uuid' => $penalty->uuid !== null ? strtolower((string) $penalty->uuid) : null,
                    'username' => trim((string) ($penalty->username ?? '')) !== '' ? (string) $penalty->username : null,
                    'type' => (string) $penalty->type,
                    'reason' => (string) ($penalty->reason ?? ''),
                    'duration_seconds' => $penalty->duration_seconds !== null ? (int) $penalty->duration_seconds : null,
                    'started_at' => $penalty->started_at?->toIso8601String(),
                    'ends_at' => $penalty->ends_at?->toIso8601String(),
                    'is_permanent' => (bool) $penalty->is_permanent,
                    'by_uuid' => $penalty->by_uuid !== null ? (string) $penalty->by_uuid : null,
                    'by_username' => $penalty->by_username !== null ? (string) $penalty->by_username : null,
                ];
            })
            ->values()
            ->all();

        $legacyBlacklist = MinecraftBlacklistEntry::query()
            ->whereNull('lifted_at')
            ->where(function ($query) use ($asOf): void {
                $query
                    ->where('is_permanent', true)
                    ->orWhereNull('ends_at')
                    ->orWhere('ends_at', '>', $asOf);
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(function (MinecraftBlacklistEntry $entry): array {
                return [
                    'uuid' => $entry->uuid !== null ? strtolower((string) $entry->uuid) : null,
                    'username' => (string) $entry->username,
                    'reason' => (string) ($entry->reason ?? ''),
                    'starts_at' => $entry->starts_at?->toIso8601String(),
                    'ends_at' => $entry->ends_at?->toIso8601String(),
                    'is_permanent' => (bool) $entry->is_permanent,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'as_of' => $asOf->toIso8601String(),
                'request' => [
                    'server_name' => trim((string) ($validated['server_name'] ?? '')) ?: null,
                    'reason' => trim((string) ($validated['reason'] ?? '')) ?: null,
                    'plugin_version' => trim((string) ($validated['plugin_version'] ?? '')) ?: null,
                ],
                'counts' => [
                    'whitelisted_accounts' => count($accounts),
                    'active_penalties' => count($penalties),
                    'active_legacy_blacklist' => count($legacyBlacklist),
                ],
                'accounts' => $accounts,
                'penalties' => $penalties,
                'legacy_blacklist' => $legacyBlacklist,
            ],
        ]);
    }

    private function parseOccurredAt(mixed $value): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return now();
    }

    private function signatureIsValid(string $body, string $timestamp, string $signature, string $secret): bool
    {
        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $timestampInt = (int) $timestamp;
        if (abs(time() - $timestampInt) > 300) {
            return false;
        }

        $expected = MinecraftSyncService::signPayload($body, $timestamp, $secret);

        return hash_equals($expected, $signature);
    }

    private function deliveryIsFresh(string $deliveryId, string $body, string $timestamp): bool
    {
        if ($deliveryId === '' || ! preg_match('/^[a-f0-9-]{36}$/i', $deliveryId)) {
            return false;
        }

        $cacheKey = 'minecraft-webhook-delivery:'.$deliveryId;
        $fingerprint = hash('sha256', $timestamp."\n".$body);

        return Cache::add($cacheKey, $fingerprint, now()->addMinutes(10));
    }

    private function findAccountForPlayer(string $uuid, string $username, string $platform): ?MinecraftAccount
    {
        $account = MinecraftAccount::query()->where('uuid', $uuid)->first();
        if ($account) {
            return $account;
        }

        return MinecraftAccount::query()
            ->where('platform', $platform)
            ->where('username', $username)
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function storeInboundEvent(string $event, ?array $payload): void
    {
        if ($event === 'server.sync.request') {
            return;
        }

        if (! Schema::hasTable('minecraft_event_logs') || ! is_array($payload)) {
            return;
        }

        $uuid = trim(strtolower((string) ($payload['uuid'] ?? '')));
        $username = trim((string) ($payload['username'] ?? ''));
        $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
        if (! in_array($platform, MinecraftAccount::PLATFORMS, true)) {
            $platform = '';
        }

        $account = $this->resolveEventAccount(
            uuid: $uuid !== '' ? $uuid : null,
            username: $username !== '' ? $username : null,
            platform: $platform !== '' ? $platform : null,
        );

        MinecraftEventLog::query()->create([
            'minecraft_account_id' => $account?->id,
            'event' => $event,
            'occurred_at' => $this->parseOccurredAt($payload['occurred_at'] ?? null),
            'platform' => $platform !== '' ? $platform : null,
            'uuid' => $uuid !== '' ? $uuid : null,
            'username' => $username !== '' ? $username : null,
            'server_name' => trim((string) ($payload['server_name'] ?? '')) ?: null,
            'message' => trim((string) ($payload['message'] ?? '')) ?: null,
            'payload' => $payload,
        ]);
    }

    private function resolveEventAccount(?string $uuid, ?string $username, ?string $platform): ?MinecraftAccount
    {
        if ($uuid !== null && trim($uuid) !== '') {
            $account = MinecraftAccount::query()->where('uuid', trim(strtolower($uuid)))->first();
            if ($account) {
                return $account;
            }
        }

        if ($username === null || trim($username) === '') {
            return null;
        }

        $query = MinecraftAccount::query()->where('username', trim($username));
        if ($platform !== null && trim($platform) !== '') {
            $query->where('platform', trim(strtolower($platform)));
        }

        return $query
            ->orderByRaw('user_id IS NULL')
            ->orderByDesc('is_whitelisted')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function createInboundLog(Request $request, string $deliveryId, ?array $payload, string $rawBody): ?MinecraftWebhookLog
    {
        if (! Schema::hasTable('minecraft_webhook_logs')) {
            return null;
        }

        return MinecraftWebhookLog::query()->create([
            'direction' => MinecraftWebhookLog::DIRECTION_INBOUND,
            'status' => MinecraftWebhookLog::STATUS_RECEIVED,
            'event' => is_array($payload) ? trim((string) ($payload['event'] ?? '')) ?: null : null,
            'delivery_id' => $deliveryId !== '' ? $deliveryId : null,
            'method' => strtoupper($request->method()),
            'target_url' => $request->fullUrl(),
            'request_headers' => [
                'X-Minecraft-Timestamp' => trim((string) $request->header('X-Minecraft-Timestamp', '')),
                'X-Minecraft-Signature' => trim((string) $request->header('X-Minecraft-Signature', '')),
                'X-Minecraft-Delivery-Id' => trim((string) $request->header('X-Minecraft-Delivery-Id', '')),
                'Content-Type' => trim((string) $request->header('Content-Type', '')),
            ],
            'payload' => $payload,
            'raw_body' => $rawBody !== '' ? $rawBody : null,
            'attempt_count' => 1,
            'last_attempted_at' => now(),
        ]);
    }

    private function finalizeInboundLog(
        ?MinecraftWebhookLog $log,
        JsonResponse $response,
        string $status,
        ?string $errorMessage = null,
    ): JsonResponse {
        if ($log) {
            $log->status = $status;
            $log->error_message = $errorMessage;
            $log->response_status = $response->getStatusCode();
            $log->response_body = $response->getContent();
            $log->processed_at = now();
            $log->failed_at = in_array($status, [MinecraftWebhookLog::STATUS_REJECTED, MinecraftWebhookLog::STATUS_FAILED], true) ? now() : null;
            $log->save();
        }

        return $response;
    }
}
