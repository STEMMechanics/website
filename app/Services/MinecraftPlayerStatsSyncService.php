<?php

namespace App\Services;

use App\Models\MinecraftPlayerStat;
use App\Support\MinecraftPlayerStatFormatter;
use Illuminate\Support\Carbon;

class MinecraftPlayerStatsSyncService
{
    public function __construct(
        private readonly MinecraftWebhookBridgeService $minecraftWebhookBridgeService
    ) {}

    /**
     * @return array{
     *     periods_synced: list<string>,
     *     unique_players_received: int,
     *     unique_players_saved: int,
     *     snapshots_received: int,
     *     snapshots_saved: int,
     *     timestamp: string|null
     * }
     */
    public function syncAll(?string $period = null): array
    {
        return $this->syncPeriods(
            $this->periodsToSync($period),
            fn (string $resolvedPeriod): array => $this->minecraftWebhookBridgeService->requestPlayerStats(period: $resolvedPeriod),
        );
    }

    public function syncUuid(string $uuid, ?string $period = null): ?MinecraftPlayerStat
    {
        $trimmedUuid = trim($uuid);
        if ($trimmedUuid === '') {
            return null;
        }

        $periods = $this->periodsToSync($period);
        $this->syncPeriods(
            $periods,
            fn (string $resolvedPeriod): array => $this->minecraftWebhookBridgeService->requestPlayerStats(uuid: $trimmedUuid, period: $resolvedPeriod),
        );

        return MinecraftPlayerStat::query()
            ->where('uuid', $trimmedUuid)
            ->forPeriod($periods[0])
            ->first();
    }

    public function syncUsername(string $username, ?string $period = null): ?MinecraftPlayerStat
    {
        $trimmedUsername = trim($username);
        if ($trimmedUsername === '') {
            return null;
        }

        $periods = $this->periodsToSync($period);
        $this->syncPeriods(
            $periods,
            fn (string $resolvedPeriod): array => $this->minecraftWebhookBridgeService->requestPlayerStats(username: $trimmedUsername, period: $resolvedPeriod),
        );

        return MinecraftPlayerStat::query()
            ->where('username', $trimmedUsername)
            ->forPeriod($periods[0])
            ->orderByDesc('captured_at')
            ->first();
    }

    /**
     * Store a single inbound player-stats snapshot pushed by the Minecraft server.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     periods_synced: list<string>,
     *     unique_players_received: int,
     *     unique_players_saved: int,
     *     snapshots_received: int,
     *     snapshots_saved: int,
     *     timestamp: string|null
     * }
     */
    public function storeSnapshot(array $snapshot): array
    {
        $result = $this->storeSnapshotDetailed($snapshot, []);

        return [
            'periods_synced' => [$result['period']],
            'unique_players_received' => count($result['received_uuids']),
            'unique_players_saved' => count($result['saved_uuids']),
            'snapshots_received' => $result['snapshots_received'],
            'snapshots_saved' => $result['snapshots_saved'],
            'timestamp' => $result['timestamp'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @param  list<array{key: string, title?: string|null, description?: string|null}>  $statDefinitions
     * @return array{
     *     periods_synced: list<string>,
     *     unique_players_received: int,
     *     unique_players_saved: int,
     *     snapshots_received: int,
     *     snapshots_saved: int,
     *     timestamp: string|null
     * }
     */
    public function storeSnapshots(array $snapshots, ?string $fallbackTimestamp = null, array $statDefinitions = []): array
    {
        $definitionMap = $this->buildStatDefinitionMap($statDefinitions);
        $periods = [];
        $receivedUuids = [];
        $savedUuids = [];
        $snapshotsReceived = 0;
        $snapshotsSaved = 0;
        $lastTimestamp = $this->parseTimestamp($fallbackTimestamp)?->toIso8601String();

        foreach ($snapshots as $snapshot) {
            if (! is_string($snapshot['timestamp'] ?? null) && $fallbackTimestamp !== null) {
                $snapshot['timestamp'] = $fallbackTimestamp;
            }

            $result = $this->storeSnapshotDetailed($snapshot, $definitionMap);
            $periods[] = $result['period'];
            $receivedUuids = array_values(array_unique(array_merge($receivedUuids, $result['received_uuids'])));
            $savedUuids = array_values(array_unique(array_merge($savedUuids, $result['saved_uuids'])));
            $snapshotsReceived += $result['snapshots_received'];
            $snapshotsSaved += $result['snapshots_saved'];
            $lastTimestamp = $result['timestamp'] ?? $lastTimestamp;
        }

        $normalizedPeriods = array_values(array_unique($periods));
        if ($normalizedPeriods !== []) {
            MinecraftPlayerStat::query()
                ->whereNotIn('period', $normalizedPeriods)
                ->delete();
        }

        return [
            'periods_synced' => $normalizedPeriods,
            'unique_players_received' => count($receivedUuids),
            'unique_players_saved' => count($savedUuids),
            'snapshots_received' => $snapshotsReceived,
            'snapshots_saved' => $snapshotsSaved,
            'timestamp' => $lastTimestamp,
        ];
    }

    /**
     * @param  callable(string): array<string, mixed>  $resolver
     * @return array{
     *     periods_synced: list<string>,
     *     unique_players_received: int,
     *     unique_players_saved: int,
     *     snapshots_received: int,
     *     snapshots_saved: int,
     *     timestamp: string|null
     * }
     */
    private function syncPeriods(array $periods, callable $resolver): array
    {
        $snapshotsReceived = 0;
        $snapshotsSaved = 0;
        $receivedUuids = [];
        $savedUuids = [];
        $lastTimestamp = null;

        foreach ($periods as $period) {
            $result = $this->storeResponse($resolver($period));
            $snapshotsReceived += $result['snapshots_received'];
            $snapshotsSaved += $result['snapshots_saved'];
            $receivedUuids = array_values(array_unique(array_merge($receivedUuids, $result['received_uuids'])));
            $savedUuids = array_values(array_unique(array_merge($savedUuids, $result['saved_uuids'])));
            $lastTimestamp = $result['timestamp'] ?? $lastTimestamp;
        }

        return [
            'periods_synced' => $periods,
            'unique_players_received' => count($receivedUuids),
            'unique_players_saved' => count($savedUuids),
            'snapshots_received' => $snapshotsReceived,
            'snapshots_saved' => $snapshotsSaved,
            'timestamp' => $lastTimestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, array{title: string, description: string}>  $definitionMap
     * @return array{
     *     snapshots_received: int,
     *     snapshots_saved: int,
     *     received_uuids: list<string>,
     *     saved_uuids: list<string>,
     *     timestamp: string|null
     * }
     */
    private function storeResponse(array $response, array $definitionMap = []): array
    {
        $playerRows = is_array($response['players'] ?? null) ? $response['players'] : [];
        $period = MinecraftPlayerStat::normalizePeriod($response['period'] ?? null);
        $periodDays = is_numeric($response['period_days'] ?? null) ? (int) $response['period_days'] : null;
        $responseTimestamp = $this->parseTimestamp($response['timestamp'] ?? null);
        $fetchedAt = $responseTimestamp ?? now();
        $savedCount = 0;
        $receivedUuids = [];
        $savedUuids = [];

        foreach ($playerRows as $player) {
            if (! is_array($player)) {
                continue;
            }

            $uuid = trim((string) ($player['uuid'] ?? ''));
            if ($uuid === '') {
                continue;
            }

            $receivedUuids[] = $uuid;
            $username = trim((string) ($player['username'] ?? ''));
            $stats = $this->normalizeStats($player['stats'] ?? [], $definitionMap);
            $capturedAt = $this->parseTimestamp($player['updated_at'] ?? null);

            MinecraftPlayerStat::query()->updateOrCreate(
                [
                    'uuid' => $uuid,
                    'period' => $period,
                ],
                [
                    'username' => $username !== '' ? $username : $uuid,
                    'period_days' => $periodDays,
                    'captured_at' => $capturedAt,
                    'fetched_at' => $fetchedAt,
                    'stats' => $stats,
                ],
            );

            $savedCount++;
            $savedUuids[] = $uuid;
        }

        if ($receivedUuids === []) {
            MinecraftPlayerStat::query()
                ->forPeriod($period)
                ->delete();
        } else {
            MinecraftPlayerStat::query()
                ->forPeriod($period)
                ->whereNotIn('uuid', array_values(array_unique($receivedUuids)))
                ->delete();
        }

        return [
            'snapshots_received' => count($receivedUuids),
            'snapshots_saved' => $savedCount,
            'received_uuids' => array_values(array_unique($receivedUuids)),
            'saved_uuids' => array_values(array_unique($savedUuids)),
            'timestamp' => $responseTimestamp?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, array{title: string, description: string}>  $definitionMap
     * @return array{
     *     period: string,
     *     snapshots_received: int,
     *     snapshots_saved: int,
     *     received_uuids: list<string>,
     *     saved_uuids: list<string>,
     *     timestamp: string|null
     * }
     */
    private function storeSnapshotDetailed(array $snapshot, array $definitionMap): array
    {
        $period = MinecraftPlayerStat::normalizePeriod(is_string($snapshot['period'] ?? null) ? (string) $snapshot['period'] : null);
        $players = $snapshot['players'] ?? null;

        if (is_array($players) && $players === []) {
            MinecraftPlayerStat::query()
                ->forPeriod($period)
                ->delete();

            return [
                'period' => $period,
                'snapshots_received' => 0,
                'snapshots_saved' => 0,
                'received_uuids' => [],
                'saved_uuids' => [],
                'timestamp' => $this->parseTimestamp($snapshot['timestamp'] ?? null)?->toIso8601String(),
            ];
        }

        $result = $this->storeResponse($snapshot, $definitionMap);

        return [
            'period' => $period,
            'snapshots_received' => $result['snapshots_received'],
            'snapshots_saved' => $result['snapshots_saved'],
            'received_uuids' => $result['received_uuids'],
            'saved_uuids' => $result['saved_uuids'],
            'timestamp' => $result['timestamp'],
        ];
    }

    /**
     * @param  array<string, array{title: string, description: string}>  $definitionMap
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     value: int|float|string|bool|null,
     *     updated_at: string|null
     * }>
     */
    private function normalizeStats(mixed $stats, array $definitionMap = []): array
    {
        if (! is_array($stats)) {
            return [];
        }

        $normalized = [];

        foreach ($stats as $stat) {
            if (! is_array($stat)) {
                continue;
            }

            $key = trim((string) ($stat['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $definition = $definitionMap[$key] ?? ['title' => '', 'description' => ''];
            $title = trim((string) ($stat['title'] ?? $definition['title']));
            $description = trim((string) ($stat['description'] ?? $definition['description']));
            $value = $this->normalizeStatValue($stat['value'] ?? null);
            $updatedAt = $this->parseTimestamp($stat['updated_at'] ?? null);

            $normalized[] = [
                'key' => $key,
                'title' => $title !== '' ? $title : MinecraftPlayerStatFormatter::fallbackTitle($key),
                'description' => $description,
                'value' => $value,
                'updated_at' => $updatedAt?->toIso8601String(),
            ];
        }

        return MinecraftPlayerStatFormatter::sortStatRows($normalized);
    }

    /**
     * @param  list<array{key: string, title?: string|null, description?: string|null}>  $definitions
     * @return array<string, array{title: string, description: string}>
     */
    private function buildStatDefinitionMap(array $definitions): array
    {
        $map = [];

        foreach ($definitions as $definition) {
            $key = trim((string) $definition['key']);
            if ($key === '') {
                continue;
            }

            $map[$key] = [
                'title' => trim((string) ($definition['title'] ?? '')),
                'description' => trim((string) ($definition['description'] ?? '')),
            ];
        }

        return $map;
    }

    private function normalizeStatValue(mixed $value): int|float|string|bool|null
    {
        if (is_numeric($value)) {
            return $value + 0;
        }

        if (is_bool($value) || is_string($value) || $value === null) {
            return $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function periodsToSync(?string $period): array
    {
        $trimmed = trim((string) $period);

        if ($trimmed === '') {
            return MinecraftPlayerStat::PERIODS;
        }

        $resolved = MinecraftPlayerStat::resolvePeriod($trimmed);

        if ($resolved === null) {
            throw new \RuntimeException('Unsupported STEMCraft player stats period.');
        }

        return [$resolved];
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
