<?php

namespace App\Http\Controllers;

use App\Models\MinecraftAccount;
use App\Models\MinecraftEventLog;
use App\Models\MinecraftMessage;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftPlayerStat;
use App\Models\MinecraftSession;
use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
use App\Services\MinecraftMessageModerationService;
use App\Services\MinecraftPlayerStatsSyncService;
use App\Services\MinecraftSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MinecraftWebhookController extends Controller
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_EVENTS = [
        'player.login',
        'player.logout',
        'player.profile.updated',
        'player.teleport',
        'player.gamemode.changed',
        'player.message',
        'player.penalty.created',
        'player.penalty.updated',
        'player.penalty.deleted',
        'server.health.ping',
        'server.sync.players',
        'server.sync.penalties',
        'server.sync.players.stats',
    ];

    public function __construct(
        private readonly MinecraftMessageModerationService $minecraftMessageModerationService,
        private readonly MinecraftPlayerStatsSyncService $minecraftPlayerStatsSyncService,
    ) {}

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
                'event' => ['required', 'string'],
                'event_id' => ['nullable', 'uuid'],
            ]);
            $event = trim((string) $validated['event']);
            $eventId = trim((string) ($validated['event_id'] ?? ''));
            if ($log) {
                $log->event = $event;
                $log->save();
            }

            if (! in_array($event, self::SUPPORTED_EVENTS, true)) {
                return $this->finalizeInboundLog(
                    $log,
                    response()->json(['ok' => false, 'error' => 'unknown_event'], 422),
                    MinecraftWebhookLog::STATUS_REJECTED,
                    'Unknown STEMCraft webhook event.',
                );
            }

            if ($eventId !== '' && ! $this->eventIdIsFresh($event, $eventId)) {
                return $this->finalizeInboundLog(
                    $log,
                    response()->json([
                        'ok' => true,
                        'ignored' => true,
                        'reason' => 'duplicate_event_id',
                    ]),
                    MinecraftWebhookLog::STATUS_DUPLICATE,
                    'Duplicate STEMCraft event id.',
                );
            }

            $response = match ($event) {
                'player.login' => $this->handleLogin($request),
                'player.logout' => $this->handleLogout($request),
                'player.profile.updated' => $this->handleProfileUpdated($request),
                'player.teleport', 'player.gamemode.changed' => $this->handleGameplayEvent($request),
                'player.message' => $this->handleMessageEvent($request),
                'player.penalty.created' => $this->handlePenaltyCreated($request),
                'player.penalty.updated' => $this->handlePenaltyUpdated($request),
                'player.penalty.deleted' => $this->handlePenaltyDeleted($request),
                'server.health.ping' => $this->handleServerHealthPing($request),
                'server.sync.players' => $this->handleServerSyncPlayers($request),
                'server.sync.penalties' => $this->handleServerSyncPenalties($request),
                default => $this->handleServerPlayerStatsSync($request),
            };
            $this->storeInboundEvent(
                event: $event,
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

        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $account = $this->syncKnownAccountIdentity(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
            occurredAt: $occurredAt,
            sourceEvent: 'player.login',
            serverName: trim((string) ($validated['server_name'] ?? '')),
        );
        if (! $account) {
            Log::info('Minecraft login webhook received for unknown UUID.', ['uuid' => $validated['uuid']]);

            return response()->json(['ok' => true, 'ignored' => true]);
        }

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

        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $account = $this->syncKnownAccountIdentity(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
            occurredAt: $occurredAt,
            sourceEvent: 'player.logout',
            serverName: trim((string) ($validated['server_name'] ?? '')),
        );
        if (! $account) {
            Log::info('Minecraft logout webhook received for unknown UUID.', ['uuid' => $validated['uuid']]);

            return response()->json(['ok' => true, 'ignored' => true]);
        }

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

        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $account = $this->syncKnownAccountIdentity(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
            occurredAt: $occurredAt,
            sourceEvent: 'player.profile.updated',
            serverName: null,
        );
        if (! $account) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $account->last_seen_at = $occurredAt;
        $account->save();

        return response()->json(['ok' => true]);
    }

    private function handleGameplayEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
            'username' => ['nullable', 'string', 'max:80'],
            'platform' => ['nullable', Rule::in(MinecraftAccount::PLATFORMS)],
            'occurred_at' => ['nullable', 'date'],
            'server_name' => ['nullable', 'string', 'max:100'],
        ]);

        $uuid = trim(strtolower((string) ($validated['uuid'] ?? '')));
        $username = trim((string) ($validated['username'] ?? ''));
        $platform = trim(strtolower((string) ($validated['platform'] ?? '')));
        if ($uuid !== '' && $username !== '' && in_array($platform, MinecraftAccount::PLATFORMS, true)) {
            $this->syncKnownAccountIdentity(
                uuid: $uuid,
                username: $username,
                platform: $platform,
                occurredAt: $this->parseOccurredAt($validated['occurred_at'] ?? null),
                sourceEvent: (string) $request->input('event', 'player.gameplay'),
                serverName: trim((string) ($validated['server_name'] ?? '')) ?: null,
            );
        }

        return response()->json(['ok' => true]);
    }

    private function handleMessageEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'message_type' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string'],
            'occurred_at' => ['required', 'date'],
            'server_name' => ['required', 'string', 'max:100'],
            'world' => ['required', 'string', 'max:100'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
            'yaw' => ['nullable', 'numeric'],
            'pitch' => ['nullable', 'numeric'],
            'context' => ['nullable', 'array'],
        ]);

        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $payload = json_decode((string) $request->getContent(), true);
        $account = $this->syncKnownAccountIdentity(
            uuid: strtolower((string) $validated['uuid']),
            username: trim((string) $validated['username']),
            platform: strtolower((string) $validated['platform']),
            occurredAt: $occurredAt,
            sourceEvent: 'player.message',
            serverName: trim((string) $validated['server_name']),
        );
        $moderation = $this->minecraftMessageModerationService->inspect((string) $validated['message']);

        MinecraftMessage::query()->create([
            'minecraft_account_id' => $account?->id,
            'occurred_at' => $occurredAt,
            'message_type' => trim((string) $validated['message_type']),
            'platform' => strtolower((string) $validated['platform']),
            'uuid' => strtolower((string) $validated['uuid']),
            'username' => trim((string) $validated['username']),
            'server_name' => trim((string) $validated['server_name']),
            'world' => trim((string) $validated['world']),
            'x' => (float) $validated['x'],
            'y' => (float) $validated['y'],
            'z' => (float) $validated['z'],
            'yaw' => isset($validated['yaw']) ? (float) $validated['yaw'] : null,
            'pitch' => isset($validated['pitch']) ? (float) $validated['pitch'] : null,
            'raw_message' => (string) $validated['message'],
            'filtered_message' => $moderation->filteredMessage,
            'passed' => $moderation->pass,
            'failure_reason' => $moderation->reason,
            'failure_detail' => $moderation->reasonDetail,
            'context' => is_array($validated['context'] ?? null) ? $validated['context'] : null,
            'payload' => is_array($payload) ? $payload : null,
        ]);

        return response()->json([
            'pass' => $moderation->pass,
            'filtered_message' => $moderation->filteredMessage,
            'reason' => $moderation->reason,
            'reason_detail' => $moderation->reasonDetail,
        ]);
    }

    private function handlePenaltyCreated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(MinecraftPenalty::TYPES)],
            'reason' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'started_at' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date'],
            'is_permanent' => ['nullable', 'boolean'],
            'by_uuid' => ['nullable', 'string', 'max:64'],
            'by_username' => ['nullable', 'string', 'max:80'],
            'lifted_at' => ['nullable', 'date'],
            'lifted_by_uuid' => ['nullable', 'string', 'max:64'],
            'lifted_by_username' => ['nullable', 'string', 'max:80'],
            'lift_reason' => ['nullable', 'string'],
            'updated_at' => ['nullable', 'date'],
        ]);

        return $this->upsertPenaltyFromPayload($validated, ignoreStaleCreate: true);
    }

    private function handlePenaltyUpdated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(MinecraftPenalty::TYPES)],
            'reason' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'started_at' => ['required', 'date'],
            'is_permanent' => ['nullable', 'boolean'],
            'by_uuid' => ['nullable', 'string', 'max:64'],
            'by_username' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
            'lifted_at' => ['nullable', 'date'],
            'lifted_by_uuid' => ['nullable', 'string', 'max:64'],
            'lifted_by_username' => ['nullable', 'string', 'max:80'],
            'lift_reason' => ['nullable', 'string'],
            'updated_at' => ['nullable', 'date'],
        ]);

        return $this->upsertPenaltyFromPayload($validated, ignoreStaleCreate: false);
    }

    private function handlePenaltyDeleted(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
            'started_at' => ['required', 'date'],
            'occurred_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
        ]);

        $uuid = strtolower((string) $validated['uuid']);
        $startedAt = $this->parseOccurredAt($validated['started_at']);
        $penalty = $this->findPenaltyByKey($uuid, $startedAt, includeDeleted: true);
        if (! $penalty) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        if ($penalty->deleted_at instanceof Carbon) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $incomingUpdatedAt = $this->parseOccurredAt($validated['updated_at'] ?? $validated['occurred_at'] ?? null);
        if ($penalty->updated_at instanceof Carbon && $penalty->updated_at->isAfter($incomingUpdatedAt)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $penalty->deleted_at = $this->parseOccurredAt($validated['occurred_at'] ?? null);
        $this->savePenaltyWithUpdatedAt($penalty, $incomingUpdatedAt);

        return response()->json(['ok' => true]);
    }

    private function handleServerHealthPing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_name' => ['nullable', 'string', 'max:100'],
            'plugin_version' => ['nullable', 'string', 'max:80'],
            'queue_depth' => ['nullable', 'integer', 'min:0'],
            'last_error_at' => ['nullable', 'date'],
            'last_sync_at' => ['nullable', 'date'],
        ]);

        $syncEvents = [
            'players' => 'server.sync.players',
            'penalties' => 'server.sync.penalties',
            'player_stats' => 'server.sync.players.stats',
        ];
        $lastInboundSyncAt = [];
        $requiredSync = [];

        foreach ($syncEvents as $label => $eventName) {
            $lastSyncedAt = $this->latestInboundProcessedAt($eventName);
            $lastInboundSyncAt[$label] = $lastSyncedAt;
            if ($lastSyncedAt === null) {
                $requiredSync[] = $eventName;
            }
        }

        return response()->json([
            'ok' => true,
            'event' => 'server.health.pong',
            'server_time' => now()->toIso8601String(),
            'capabilities' => [
                'supports_event_id' => true,
                'recommended_reconnect_sequence' => [
                    'server.sync.players',
                    'server.sync.penalties',
                    'server.sync.players.stats',
                ],
            ],
            'sync' => [
                'last_inbound_sync_at' => $lastInboundSyncAt,
                'required' => $requiredSync,
            ],
            'request' => [
                'server_name' => trim((string) ($validated['server_name'] ?? '')) ?: null,
                'plugin_version' => trim((string) ($validated['plugin_version'] ?? '')) ?: null,
                'queue_depth' => isset($validated['queue_depth']) ? (int) $validated['queue_depth'] : null,
                'last_error_at' => is_string($validated['last_error_at'] ?? null) ? (string) $validated['last_error_at'] : null,
                'last_sync_at' => is_string($validated['last_sync_at'] ?? null) ? (string) $validated['last_sync_at'] : null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function upsertPenaltyFromPayload(array $validated, bool $ignoreStaleCreate): JsonResponse
    {
        $uuid = strtolower((string) $validated['uuid']);
        $startedAt = $this->parseOccurredAt($validated['started_at'] ?? $validated['occurred_at'] ?? null);
        $occurredAt = $this->parseOccurredAt($validated['occurred_at'] ?? $validated['started_at'] ?? null);
        $incomingUpdatedAt = $this->parseOccurredAt($validated['updated_at'] ?? $validated['occurred_at'] ?? $validated['started_at'] ?? null);
        $incomingLiftedAt = isset($validated['lifted_at']) ? $this->parseOccurredAt($validated['lifted_at']) : null;
        $penalty = $this->findPenaltyByKey($uuid, $startedAt, includeDeleted: true) ?? new MinecraftPenalty();

        if ($penalty->updated_at instanceof Carbon && $penalty->updated_at->isAfter($incomingUpdatedAt)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        if (
            $ignoreStaleCreate
            && $penalty->exists
            && $penalty->lifted_at instanceof Carbon
            && $incomingLiftedAt === null
            && ! $occurredAt->isAfter($penalty->lifted_at)
        ) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $account = MinecraftAccount::query()->where('uuid', $uuid)->first();
        $type = (string) $validated['type'];
        $durationSeconds = isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null;
        $isPermanent = (bool) ($validated['is_permanent'] ?? false);

        $penalty->minecraft_account_id = $account?->id;
        $penalty->uuid = $uuid;
        $penalty->username = trim((string) $validated['username']);
        $penalty->type = $type;
        $penalty->reason = trim((string) ($validated['reason'] ?? '')) ?: null;
        $penalty->duration_seconds = $durationSeconds;
        $penalty->started_at = $startedAt;
        $penalty->is_permanent = $isPermanent;
        $penalty->by_uuid = trim((string) ($validated['by_uuid'] ?? '')) ?: null;
        $penalty->by_user_id = null;
        $penalty->by_username = trim((string) ($validated['by_username'] ?? '')) ?: null;
        $penalty->lifted_at = $incomingLiftedAt;
        $penalty->lifted_by_uuid = trim((string) ($validated['lifted_by_uuid'] ?? '')) ?: null;
        $penalty->lifted_by_user_id = null;
        $penalty->lifted_by_username = trim((string) ($validated['lifted_by_username'] ?? '')) ?: null;
        $penalty->lift_reason = trim((string) ($validated['lift_reason'] ?? '')) ?: null;
        $penalty->deleted_at = null;
        $penalty->ends_at = match ($type) {
            MinecraftPenalty::TYPE_KICK => $startedAt,
            default => $isPermanent || $durationSeconds === null ? null : $startedAt->copy()->addSeconds($durationSeconds),
        };
        $this->savePenaltyWithUpdatedAt($penalty, $incomingUpdatedAt);

        return response()->json(['ok' => true]);
    }

    private function findPenaltyByKey(string $uuid, Carbon $startedAt, bool $includeDeleted = false): ?MinecraftPenalty
    {
        $query = MinecraftPenalty::query();
        if ($includeDeleted) {
            $query->withTrashed();
        }

        return $query
            ->where('uuid', strtolower(trim($uuid)))
            ->whereBetween('started_at', [
                $startedAt->copy()->startOfSecond(),
                $startedAt->copy()->endOfSecond(),
            ])
            ->latest('id')
            ->first();
    }

    private function handleServerSyncPlayers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_name' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:120'],
            'plugin_version' => ['nullable', 'string', 'max:80'],
            'players' => ['present', 'array'],
            'players.*' => ['array'],
            'players.*.uuid' => ['nullable', 'string', 'max:64'],
            'players.*.username' => ['required', 'string', 'max:80'],
            'players.*.platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'players.*.is_whitelisted' => ['nullable', 'boolean'],
            'players.*.updated_at' => ['nullable', 'date'],
        ]);

        $players = is_array($validated['players'] ?? null) ? $validated['players'] : [];
        $playersReceived = 0;

        foreach ($players as $player) {
            if (! is_array($player)) {
                continue;
            }

            $this->upsertAccountFromPlayerSync($player);
            $playersReceived++;
        }

        $allPlayers = MinecraftAccount::query()
            ->orderBy('username')
            ->orderBy('id')
            ->get()
            ->map(fn (MinecraftAccount $account): array => $this->formatPlayerSyncRecord($account))
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'as_of' => now()->toIso8601String(),
                'request' => [
                    'server_name' => trim((string) ($validated['server_name'] ?? '')) ?: null,
                    'reason' => trim((string) ($validated['reason'] ?? '')) ?: null,
                    'plugin_version' => trim((string) ($validated['plugin_version'] ?? '')) ?: null,
                ],
                'counts' => [
                    'players_received' => $playersReceived,
                    'players_total' => count($allPlayers),
                    'whitelisted_players' => collect($allPlayers)->where('is_whitelisted', true)->count(),
                ],
                'players' => $allPlayers,
            ],
        ]);
    }

    private function handleServerSyncPenalties(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'starting_from' => ['nullable', 'date'],
            'penalties' => ['present', 'array'],
            'penalties.*' => ['array'],
            'penalties.*.uuid' => ['required', 'string', 'max:64'],
            'penalties.*.username' => ['required', 'string', 'max:80'],
            'penalties.*.type' => ['required', Rule::in(MinecraftPenalty::TYPES)],
            'penalties.*.reason' => ['nullable', 'string'],
            'penalties.*.duration_seconds' => ['nullable', 'integer', 'min:1'],
            'penalties.*.started_at' => ['required', 'date'],
            'penalties.*.is_permanent' => ['nullable', 'boolean'],
            'penalties.*.by_uuid' => ['nullable', 'string', 'max:64'],
            'penalties.*.by_username' => ['nullable', 'string', 'max:80'],
            'penalties.*.lifted_at' => ['nullable', 'date'],
            'penalties.*.lifted_by_uuid' => ['nullable', 'string', 'max:64'],
            'penalties.*.lifted_by_username' => ['nullable', 'string', 'max:80'],
            'penalties.*.lift_reason' => ['nullable', 'string'],
            'penalties.*.deleted_at' => ['nullable', 'date'],
            'penalties.*.updated_at' => ['required', 'date'],
        ]);

        $startingFrom = isset($validated['starting_from'])
            ? $this->parseOccurredAt($validated['starting_from'])
            : null;
        $incomingRows = is_array($validated['penalties'] ?? null) ? $validated['penalties'] : [];
        $added = 0;
        $updated = 0;
        $ignored = 0;

        foreach ($incomingRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $startedAt = $this->parseOccurredAt($row['started_at'] ?? null);
            if ($startingFrom instanceof Carbon && $startedAt->lt($startingFrom)) {
                continue;
            }

            $uuid = strtolower((string) $row['uuid']);
            $incomingUpdatedAt = $this->parseOccurredAt($row['updated_at'] ?? null);
            $penalty = $this->findPenaltyByKey($uuid, $startedAt, includeDeleted: true);

            if ($penalty?->updated_at instanceof Carbon && $penalty->updated_at->isAfter($incomingUpdatedAt)) {
                $ignored++;

                continue;
            }

            $isNew = ! $penalty?->exists;
            $penalty ??= new MinecraftPenalty();
            $this->applyPenaltySyncRecord($penalty, $row, $uuid, $startedAt);
            $this->savePenaltyWithUpdatedAt($penalty, $incomingUpdatedAt);

            if ($isNew) {
                $added++;
            } else {
                $updated++;
            }
        }

        $penalties = $this->buildPenaltySyncSnapshot($startingFrom);

        return response()->json([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'as_of' => now()->toIso8601String(),
                'starting_from' => $startingFrom?->toIso8601String(),
                'counts' => [
                    'penalties_received' => count($incomingRows),
                    'penalties_added' => $added,
                    'penalties_updated' => $updated,
                    'penalties_ignored' => $ignored,
                    'penalties_returned' => count($penalties),
                ],
                'penalties' => $penalties,
            ],
        ]);
    }

    private function handleServerPlayerStatsSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timestamp' => ['nullable', 'date'],
            'stats' => ['present', 'array'],
            'stats.*' => ['array'],
            'stats.*.key' => ['required', 'string', 'max:120'],
            'stats.*.title' => ['nullable', 'string', 'max:120'],
            'stats.*.description' => ['nullable', 'string', 'max:500'],
            'periods' => ['present', 'array'],
            'periods.*' => ['array'],
            'periods.*.period' => ['required', 'string', 'max:16'],
            'periods.*.period_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'periods.*.timestamp' => ['nullable', 'date'],
            'periods.*.players' => ['present', 'array'],
            'periods.*.players.*' => ['array'],
            'periods.*.players.*.uuid' => ['required', 'string', 'max:64'],
            'periods.*.players.*.username' => ['required', 'string', 'max:80'],
            'periods.*.players.*.platform' => ['nullable', Rule::in(MinecraftAccount::PLATFORMS)],
            'periods.*.players.*.updated_at' => ['nullable', 'date'],
            'periods.*.players.*.stats' => ['nullable', 'array'],
            'periods.*.players.*.stats.*' => ['array'],
            'periods.*.players.*.stats.*.key' => ['required', 'string', 'max:120'],
            'periods.*.players.*.stats.*.title' => ['nullable', 'string', 'max:120'],
            'periods.*.players.*.stats.*.description' => ['nullable', 'string', 'max:500'],
            'periods.*.players.*.stats.*.value' => ['nullable'],
            'periods.*.players.*.stats.*.updated_at' => ['nullable', 'date'],
        ]);

        $snapshots = [];
        $rootTimestamp = is_string($validated['timestamp'] ?? null)
            ? (string) $validated['timestamp']
            : null;

        foreach ($validated['periods'] as $periodSnapshot) {
            if (! is_array($periodSnapshot)) {
                continue;
            }

            $period = trim((string) ($periodSnapshot['period'] ?? ''));
            if (MinecraftPlayerStat::resolvePeriod($period) === null) {
                throw ValidationException::withMessages([
                    'periods' => ['Unsupported STEMCraft player stats period.'],
                ]);
            }

            $players = is_array($periodSnapshot['players'] ?? null) ? $periodSnapshot['players'] : [];
            $snapshots[] = [
                'period' => $period,
                'period_days' => isset($periodSnapshot['period_days']) ? (int) $periodSnapshot['period_days'] : null,
                'players' => $players,
                'count' => count($players),
                'timestamp' => is_string($periodSnapshot['timestamp'] ?? null)
                    ? (string) $periodSnapshot['timestamp']
                    : $rootTimestamp,
            ];
        }

        $this->minecraftPlayerStatsSyncService->storeSnapshots(
            snapshots: $snapshots,
            fallbackTimestamp: $rootTimestamp,
            statDefinitions: is_array($validated['stats'] ?? null) ? $validated['stats'] : [],
        );

        return response()->json([
            'ok' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertAccountFromPlayerSync(array $payload): void
    {
        $uuid = strtolower(trim((string) ($payload['uuid'] ?? '')));
        $username = trim((string) ($payload['username'] ?? ''));
        $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
        $seenAt = $this->parseNullableOccurredAt($payload['updated_at'] ?? null);

        if ($username === '' || ! in_array($platform, MinecraftAccount::PLATFORMS, true)) {
            return;
        }

        $account = null;
        if ($uuid !== '') {
            $account = MinecraftAccount::query()->where('uuid', $uuid)->first();
        }

        if (! $account) {
            $account = MinecraftAccount::query()
                ->where('platform', $platform)
                ->where('username', $username)
                ->first();
        }

        if (! $account) {
            $account = new MinecraftAccount();
            $account->is_whitelisted = false;
        } else {
            if (
                $seenAt instanceof Carbon
                && $account->last_seen_at instanceof Carbon
                && $seenAt->isBefore($account->last_seen_at)
            ) {
                return;
            }

            if (! ($seenAt instanceof Carbon) && $account->last_seen_at instanceof Carbon) {
                return;
            }
        }

        $conflictingAccount = MinecraftAccount::query()
            ->where('platform', $platform)
            ->where('username', $username)
            ->when($account->exists, fn ($query) => $query->where('id', '!=', $account->id))
            ->first();

        $account->platform = $platform;
        if ($uuid !== '') {
            $account->uuid = $uuid;
        }
        if (! $conflictingAccount) {
            $account->username = $username;
        }
        if ($seenAt instanceof Carbon && (! ($account->last_seen_at instanceof Carbon) || $seenAt->isAfter($account->last_seen_at))) {
            $account->last_seen_at = $seenAt;
        } elseif (! ($account->last_seen_at instanceof Carbon)) {
            $account->last_seen_at = now();
        }
        $account->save();
    }

    /**
     * @return array{
     *     uuid: string|null,
     *     username: string,
     *     platform: string,
     *     user_id: string|null,
     *     is_whitelisted: bool,
     *     updated_at: string|null
     * }
     */
    private function formatPlayerSyncRecord(MinecraftAccount $account): array
    {
        return [
            'uuid' => $account->uuid !== null ? strtolower((string) $account->uuid) : null,
            'username' => (string) $account->username,
            'platform' => (string) $account->platform,
            'user_id' => $account->user_id !== null ? (string) $account->user_id : null,
            'is_whitelisted' => (bool) $account->is_whitelisted,
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyPenaltySyncRecord(MinecraftPenalty $penalty, array $payload, string $uuid, Carbon $startedAt): void
    {
        $account = MinecraftAccount::query()->where('uuid', $uuid)->first();
        $type = (string) ($payload['type'] ?? '');
        $durationSeconds = isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null;
        $isPermanent = (bool) ($payload['is_permanent'] ?? false);
        $deletedAt = isset($payload['deleted_at']) ? $this->parseOccurredAt($payload['deleted_at']) : null;
        $liftedAt = isset($payload['lifted_at']) ? $this->parseOccurredAt($payload['lifted_at']) : null;

        $penalty->minecraft_account_id = $account?->id;
        $penalty->uuid = $uuid;
        $penalty->username = trim((string) ($payload['username'] ?? ''));
        $penalty->type = $type;
        $penalty->reason = trim((string) ($payload['reason'] ?? '')) ?: null;
        $penalty->duration_seconds = $durationSeconds;
        $penalty->started_at = $startedAt;
        $penalty->is_permanent = $isPermanent;
        $penalty->by_uuid = trim((string) ($payload['by_uuid'] ?? '')) ?: null;
        $penalty->by_user_id = null;
        $penalty->by_username = trim((string) ($payload['by_username'] ?? '')) ?: null;
        $penalty->lifted_at = $liftedAt;
        $penalty->lifted_by_uuid = trim((string) ($payload['lifted_by_uuid'] ?? '')) ?: null;
        $penalty->lifted_by_user_id = null;
        $penalty->lifted_by_username = trim((string) ($payload['lifted_by_username'] ?? '')) ?: null;
        $penalty->lift_reason = trim((string) ($payload['lift_reason'] ?? '')) ?: null;
        $penalty->deleted_at = $deletedAt;
        $penalty->ends_at = match ($type) {
            MinecraftPenalty::TYPE_KICK => $startedAt,
            default => $isPermanent || $durationSeconds === null ? null : $startedAt->copy()->addSeconds($durationSeconds),
        };
    }

    private function savePenaltyWithUpdatedAt(MinecraftPenalty $penalty, Carbon $updatedAt): void
    {
        $timestamps = $penalty->timestamps;
        $penalty->timestamps = false;
        if (! $penalty->exists) {
            $penalty->created_at = $updatedAt;
        }
        $penalty->updated_at = $updatedAt;
        $penalty->save();
        $penalty->timestamps = $timestamps;
    }

    /**
     * @return list<array{
     *     penalty_key: string|null,
     *     uuid: string|null,
     *     username: string|null,
     *     type: string,
     *     reason: string,
     *     duration_seconds: int|null,
     *     started_at: string,
     *     ends_at: string|null,
     *     is_permanent: bool,
     *     by_uuid: string|null,
     *     by_username: string|null,
     *     lifted_at: string|null,
     *     lifted_by_uuid: string|null,
     *     lifted_by_username: string|null,
     *     lift_reason: string|null,
     *     deleted_at: string|null,
     *     updated_at: string|null
     * }>
     */
    private function buildPenaltySyncSnapshot(?Carbon $startingFrom): array
    {
        return MinecraftPenalty::query()
            ->withTrashed()
            ->when(
                $startingFrom instanceof Carbon,
                fn ($query) => $query->where('started_at', '>=', $startingFrom->copy()->startOfSecond())
            )
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->map(fn (MinecraftPenalty $penalty): array => $this->formatPenaltySyncRecord($penalty))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     penalty_key: string|null,
     *     uuid: string|null,
     *     username: string|null,
     *     type: string,
     *     reason: string,
     *     duration_seconds: int|null,
     *     started_at: string,
     *     ends_at: string|null,
     *     is_permanent: bool,
     *     by_uuid: string|null,
     *     by_username: string|null,
     *     lifted_at: string|null,
     *     lifted_by_uuid: string|null,
     *     lifted_by_username: string|null,
     *     lift_reason: string|null,
     *     deleted_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function formatPenaltySyncRecord(MinecraftPenalty $penalty): array
    {
        $normalizedUuid = $penalty->uuid !== null ? strtolower((string) $penalty->uuid) : null;
        $startedAt = $penalty->started_at instanceof Carbon
            ? $penalty->started_at->toIso8601String()
            : now()->toIso8601String();

        return [
            'penalty_key' => MinecraftSyncService::penaltyKey($normalizedUuid, $startedAt),
            'uuid' => $normalizedUuid,
            'username' => trim((string) ($penalty->username ?? '')) !== '' ? (string) $penalty->username : null,
            'type' => (string) $penalty->type,
            'reason' => (string) ($penalty->reason ?? ''),
            'duration_seconds' => $penalty->duration_seconds !== null ? (int) $penalty->duration_seconds : null,
            'started_at' => $startedAt,
            'ends_at' => $penalty->ends_at?->toIso8601String(),
            'is_permanent' => (bool) $penalty->is_permanent,
            'by_uuid' => $penalty->by_uuid !== null ? (string) $penalty->by_uuid : null,
            'by_username' => $penalty->by_username !== null ? (string) $penalty->by_username : null,
            'lifted_at' => $penalty->lifted_at?->toIso8601String(),
            'lifted_by_uuid' => $penalty->lifted_by_uuid !== null ? (string) $penalty->lifted_by_uuid : null,
            'lifted_by_username' => $penalty->lifted_by_username !== null ? (string) $penalty->lifted_by_username : null,
            'lift_reason' => $penalty->lift_reason !== null ? (string) $penalty->lift_reason : null,
            'deleted_at' => $penalty->deleted_at?->toIso8601String(),
            'updated_at' => $penalty->updated_at?->toIso8601String(),
        ];
    }

    private function parseOccurredAt(mixed $value): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
        }

        return now();
    }

    private function parseNullableOccurredAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
    }

    private function eventIdIsFresh(string $event, string $eventId): bool
    {
        $cacheKey = sprintf('minecraft-webhook-event:%s:%s', $event, strtolower(trim($eventId)));

        return Cache::add($cacheKey, true, now()->addDays(30));
    }

    private function latestInboundProcessedAt(string $event): ?string
    {
        if (! Schema::hasTable('minecraft_webhook_logs')) {
            return null;
        }

        return MinecraftWebhookLog::query()
            ->where('direction', MinecraftWebhookLog::DIRECTION_INBOUND)
            ->where('event', $event)
            ->whereIn('status', [
                MinecraftWebhookLog::STATUS_RECEIVED,
                MinecraftWebhookLog::STATUS_IGNORED,
            ])
            ->whereNotNull('processed_at')
            ->orderByDesc('processed_at')
            ->first()?->processed_at?->toIso8601String();
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
        $uuidAccount = MinecraftAccount::query()->where('uuid', $uuid)->first();
        $identityAccount = MinecraftAccount::query()
            ->where('platform', $platform)
            ->where('username', $username)
            ->first();

        if (
            $uuidAccount instanceof MinecraftAccount
            && $identityAccount instanceof MinecraftAccount
            && $uuidAccount->id !== $identityAccount->id
            && $this->shouldPreferIdentityAccount($uuidAccount, $identityAccount, $uuid)
        ) {
            return $this->reconcileSplitIdentityAccounts(
                uuidAccount: $uuidAccount,
                identityAccount: $identityAccount,
                uuid: $uuid,
            );
        }

        if ($uuidAccount instanceof MinecraftAccount) {
            return $uuidAccount;
        }

        return $identityAccount instanceof MinecraftAccount ? $identityAccount : null;
    }

    private function shouldPreferIdentityAccount(MinecraftAccount $uuidAccount, MinecraftAccount $identityAccount, string $incomingUuid): bool
    {
        $identityUuid = trim(strtolower((string) ($identityAccount->uuid ?? '')));
        if ($identityUuid !== '' && $identityUuid !== strtolower(trim($incomingUuid))) {
            return false;
        }

        $uuidAccountUserId = trim((string) ($uuidAccount->user_id ?? ''));
        $identityAccountUserId = trim((string) ($identityAccount->user_id ?? ''));
        if (
            $uuidAccountUserId !== ''
            && $identityAccountUserId !== ''
            && $uuidAccountUserId !== $identityAccountUserId
        ) {
            return false;
        }

        if ($identityAccountUserId !== '' && $uuidAccountUserId === '') {
            return true;
        }

        if ($identityAccountUserId === $uuidAccountUserId) {
            return true;
        }

        return $identityUuid === '';
    }

    private function reconcileSplitIdentityAccounts(MinecraftAccount $uuidAccount, MinecraftAccount $identityAccount, string $uuid): MinecraftAccount
    {
        $incomingUuid = strtolower(trim($uuid));

        return DB::transaction(function () use ($uuidAccount, $identityAccount, $incomingUuid): MinecraftAccount {
            /** @var MinecraftAccount $uuidAccountRow */
            $uuidAccountRow = MinecraftAccount::query()->lockForUpdate()->findOrFail($uuidAccount->id);
            /** @var MinecraftAccount $identityAccountRow */
            $identityAccountRow = MinecraftAccount::query()->lockForUpdate()->findOrFail($identityAccount->id);

            $uuidAccountLastSeen = $uuidAccountRow->last_seen_at instanceof Carbon ? $uuidAccountRow->last_seen_at : null;
            $uuidAccountLastLogin = $uuidAccountRow->last_login_at instanceof Carbon ? $uuidAccountRow->last_login_at : null;
            $uuidAccountLastLogout = $uuidAccountRow->last_logout_at instanceof Carbon ? $uuidAccountRow->last_logout_at : null;
            $identityAccountLastSeen = $identityAccountRow->last_seen_at instanceof Carbon ? $identityAccountRow->last_seen_at : null;
            $identityAccountLastLogin = $identityAccountRow->last_login_at instanceof Carbon ? $identityAccountRow->last_login_at : null;
            $identityAccountLastLogout = $identityAccountRow->last_logout_at instanceof Carbon ? $identityAccountRow->last_logout_at : null;

            $uuidAccountRow->uuid = null;
            $uuidAccountRow->save();

            $identityAccountRow->uuid = $incomingUuid;
            if ($identityAccountLastSeen === null || ($uuidAccountLastSeen instanceof Carbon && $uuidAccountLastSeen->gt($identityAccountLastSeen))) {
                $identityAccountRow->last_seen_at = $uuidAccountLastSeen;
            }
            if ($identityAccountLastLogin === null || ($uuidAccountLastLogin instanceof Carbon && $uuidAccountLastLogin->gt($identityAccountLastLogin))) {
                $identityAccountRow->last_login_at = $uuidAccountLastLogin;
            }
            if ($identityAccountLastLogout === null || ($uuidAccountLastLogout instanceof Carbon && $uuidAccountLastLogout->gt($identityAccountLastLogout))) {
                $identityAccountRow->last_logout_at = $uuidAccountLastLogout;
            }
            $identityAccountRow->save();

            $this->migrateAccountReferences($uuidAccountRow->id, $identityAccountRow->id);

            if ($uuidAccountRow->user_id === null) {
                $uuidAccountRow->delete();
            }

            return $identityAccountRow->fresh() ?? $identityAccountRow;
        });
    }

    private function migrateAccountReferences(int $fromAccountId, int $toAccountId): void
    {
        if ($fromAccountId === $toAccountId) {
            return;
        }

        foreach ([
            'minecraft_sessions',
            'minecraft_penalties',
            'minecraft_blacklist_entries',
            'minecraft_event_logs',
            'minecraft_messages',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('minecraft_account_id', $fromAccountId)
                ->update(['minecraft_account_id' => $toAccountId]);
        }
    }

    private function syncKnownAccountIdentity(
        string $uuid,
        string $username,
        string $platform,
        Carbon $occurredAt,
        string $sourceEvent,
        ?string $serverName,
    ): ?MinecraftAccount {
        $account = $this->findAccountForPlayer($uuid, $username, $platform);
        if (! $account) {
            return null;
        }

        $oldUsername = (string) $account->username;
        $conflictingAccount = null;

        if ($oldUsername !== $username) {
            $conflictingAccount = MinecraftAccount::query()
                ->where('platform', $platform)
                ->where('username', $username)
                ->where('id', '!=', $account->id)
                ->first();
        }

        $account->platform = $platform;
        $account->uuid = $uuid;
        $account->last_seen_at = $occurredAt;

        if (! $conflictingAccount) {
            $account->username = $username;
        }

        $account->save();

        if ($oldUsername !== $username) {
            $this->storeUsernameChangeAuditEvent(
                account: $account,
                occurredAt: $occurredAt,
                oldUsername: $oldUsername,
                newUsername: $username,
                sourceEvent: $sourceEvent,
                serverName: $serverName,
                conflictingAccount: $conflictingAccount,
            );
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function storeInboundEvent(string $event, ?array $payload): void
    {
        if (in_array($event, ['player.message', 'server.health.ping', 'server.sync.players', 'server.sync.penalties', 'server.sync.players.stats'], true)) {
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

    private function storeUsernameChangeAuditEvent(
        MinecraftAccount $account,
        Carbon $occurredAt,
        string $oldUsername,
        string $newUsername,
        string $sourceEvent,
        ?string $serverName,
        ?MinecraftAccount $conflictingAccount = null,
    ): void {
        if (! Schema::hasTable('minecraft_event_logs')) {
            return;
        }

        $event = $conflictingAccount
            ? 'player.username.change_conflict'
            : 'player.username.changed';
        $message = $conflictingAccount
            ? 'Username change detected but could not be applied because another account already uses '.$newUsername.'.'
            : 'Username changed from '.$oldUsername.' to '.$newUsername.'.';

        MinecraftEventLog::query()->create([
            'minecraft_account_id' => $account->id,
            'event' => $event,
            'occurred_at' => $occurredAt,
            'platform' => (string) $account->platform,
            'uuid' => $account->uuid !== null ? strtolower((string) $account->uuid) : null,
            'username' => $newUsername,
            'server_name' => $serverName !== null && trim($serverName) !== '' ? trim($serverName) : null,
            'message' => $message,
            'payload' => [
                'source_event' => $sourceEvent,
                'old_username' => $oldUsername,
                'new_username' => $newUsername,
                'applied' => ! $conflictingAccount,
                'conflicting_account_id' => $conflictingAccount?->id,
            ],
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
