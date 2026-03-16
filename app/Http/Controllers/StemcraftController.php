<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPenalty;
use App\Models\MinecraftPlayerStat;
use App\Models\SiteOption;
use App\Services\MinecraftWebhookBridgeService;
use App\Support\MinecraftPlayerStatFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StemcraftController extends Controller
{
    public function index(MinecraftWebhookBridgeService $minecraftWebhookBridgeService): View
    {
        return view('stemcraft.index', [
            'serverInfo' => $this->buildPublicServerInfo($minecraftWebhookBridgeService),
        ]);
    }

    public function join(): View
    {
        return view('stemcraft.join');
    }

    public function rules(): View
    {
        return view('stemcraft.rules');
    }

    public function faqs(): View
    {
        return view('stemcraft.faqs');
    }

    public function stats(Request $request): View
    {
        $selectedPeriod = MinecraftPlayerStat::resolvePeriod((string) $request->query('period')) ?? MinecraftPlayerStat::PERIOD_ALL;

        /** @var Collection<int, MinecraftPlayerStat> $playerStats */
        $playerStats = MinecraftPlayerStat::query()
            ->forPeriod($selectedPeriod)
            ->orderBy('username')
            ->get();
        $leaderboardStats = $this->buildLeaderboardStats($playerStats);
        $lastSyncedAtAnyPeriod = MinecraftPlayerStat::query()
            ->whereNotNull('fetched_at')
            ->orderByDesc('fetched_at')
            ->first()?->fetched_at;

        return view('stemcraft.leaderboards', [
            'selectedPeriod' => $selectedPeriod,
            'periodOptions' => MinecraftPlayerStat::PERIODS,
            'periodLabel' => MinecraftPlayerStat::periodLabel($selectedPeriod),
            'leaderboardStats' => $leaderboardStats,
            'trackedPlayerCount' => $playerStats->count(),
            'lastSyncedAt' => $playerStats
                ->filter(fn (MinecraftPlayerStat $playerStat): bool => $playerStat->fetched_at !== null)
                ->sortByDesc(fn (MinecraftPlayerStat $playerStat): int => (int) $playerStat->fetched_at?->timestamp)
                ->first()?->fetched_at,
            'lastCapturedAt' => $playerStats
                ->filter(fn (MinecraftPlayerStat $playerStat): bool => $playerStat->captured_at !== null)
                ->sortByDesc(fn (MinecraftPlayerStat $playerStat): int => (int) $playerStat->captured_at?->timestamp)
                ->first()?->captured_at,
            'lastSyncedAtAnyPeriod' => $lastSyncedAtAnyPeriod,
        ]);
    }

    public function punishments(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));

        $query = MinecraftPenalty::query()
            ->with('account')
            ->orderByDesc('started_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('username', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%')
                    ->orWhere('by_username', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            });
        }

        if (in_array($type, MinecraftPenalty::TYPES, true)) {
            $query->where('type', $type);
        }

        if ($status === 'active') {
            $query->where(function ($builder): void {
                $builder->whereNull('lifted_at')
                    ->where(function ($restrictionBuilder): void {
                        $restrictionBuilder->where('is_permanent', true)
                            ->orWhere('ends_at', '>', now())
                            ->orWhere('type', MinecraftPenalty::TYPE_KICK);
                    });
            });
        } elseif ($status === 'lifted') {
            $query->whereNotNull('lifted_at');
        } elseif ($status === 'expired') {
            $query->whereNull('lifted_at')
                ->where('is_permanent', false)
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now());
        }

        /** @var LengthAwarePaginator $penalties */
        $penalties = $query->paginate(25)->onEachSide(1);

        return view('stemcraft.punishments', [
            'penalties' => $penalties,
            'search' => $search,
            'selectedType' => $type,
            'selectedStatus' => $status,
        ]);
    }

    /**
     * @param  Collection<int, MinecraftPlayerStat>  $playerStats
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     rows: list<array{
     *         rank: int,
     *         username: string,
     *         platform: string,
     *         uuid: string,
     *         formatted_value: string,
     *         updated_at: string|null
     *     }>
     * }>
     */
    private function buildLeaderboardStats(Collection $playerStats): array
    {
        $statBuckets = [];

        foreach ($playerStats as $playerStat) {
            foreach ($playerStat->statRows() as $stat) {
                $value = $stat['value'];

                if (! is_numeric($value)) {
                    continue;
                }

                $key = $stat['key'];
                $statBuckets[$key] ??= [
                    'key' => $key,
                    'title' => $stat['title'],
                    'description' => $stat['description'],
                    'rows' => [],
                ];

                $statBuckets[$key]['rows'][] = [
                    'username' => (string) $playerStat->username,
                    'platform' => trim((string) ($playerStat->platform ?? 'java')) !== ''
                        ? strtolower(trim((string) $playerStat->platform))
                        : 'java',
                    'uuid' => (string) $playerStat->uuid,
                    'numeric_value' => (float) $value,
                    'formatted_value' => $stat['formatted_value'],
                    'updated_at' => $stat['updated_at'] ?? $playerStat->captured_at?->toIso8601String(),
                ];
            }
        }

        $definitions = MinecraftPlayerStatFormatter::sortStatRows(array_values(array_map(
            static fn (array $bucket): array => [
                'key' => $bucket['key'],
                'title' => $bucket['title'],
                'description' => $bucket['description'],
            ],
            $statBuckets,
        )));

        $leaderboards = [];

        foreach ($definitions as $definition) {
            $rows = $statBuckets[$definition['key']]['rows'] ?? [];
            if ($rows === []) {
                continue;
            }

            usort($rows, static function (array $left, array $right): int {
                $valueComparison = $right['numeric_value'] <=> $left['numeric_value'];

                if ($valueComparison !== 0) {
                    return $valueComparison;
                }

                return strnatcasecmp((string) $left['username'], (string) $right['username']);
            });

            $leaderboards[] = [
                'key' => $definition['key'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'rows' => array_map(static function (array $row, int $index): array {
                    return [
                        'rank' => $index + 1,
                        'username' => (string) $row['username'],
                        'platform' => (string) $row['platform'],
                        'uuid' => (string) $row['uuid'],
                        'formatted_value' => (string) $row['formatted_value'],
                        'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
                    ];
                }, array_slice($rows, 0, 10), array_keys(array_slice($rows, 0, 10))),
            ];
        }

        return $leaderboards;
    }

    /**
     * @return array{
     *     available: bool,
     *     heading: string,
     *     summary: string,
     *     refreshed_at: string|null,
     *     refreshed_at_human: string|null,
     *     cards: list<array{label: string, value: string}>,
     *     worlds: list<array{
     *         type: 'single'|'group',
     *         name: string,
     *         raw: string|null,
     *         summary: string|null,
     *         children: list<array{name: string, raw: string}>
     *     }>
     * }
     */
    private function buildPublicServerInfo(MinecraftWebhookBridgeService $minecraftWebhookBridgeService): array
    {
        $connection = $minecraftWebhookBridgeService->connectionSummary();
        if (! $connection['configured']) {
            return [
                'available' => false,
                'heading' => 'Live status unavailable',
                'summary' => 'Server status is not currently connected to the website.',
                'refreshed_at' => null,
                'refreshed_at_human' => null,
                'cards' => [],
                'worlds' => [],
            ];
        }

        $cacheKey = 'stemcraft:public-server-info:v1';
        $cachedSnapshot = Cache::get($cacheKey);
        $snapshot = is_array($cachedSnapshot) ? $cachedSnapshot : null;
        $snapshotAgeSeconds = is_array($snapshot) && is_numeric($snapshot['fetched_at_unix'] ?? null)
            ? max(0, time() - (int) $snapshot['fetched_at_unix'])
            : PHP_INT_MAX;

        if ($snapshotAgeSeconds > 60) {
            try {
                $status = $minecraftWebhookBridgeService->requestStatus(3);
                $snapshot = [
                    'status' => $status,
                    'fetched_at_unix' => time(),
                ];
                Cache::put($cacheKey, $snapshot, now()->addMinutes(20));
            } catch (\Throwable) {
            }
        }

        $status = is_array($snapshot['status'] ?? null) ? $snapshot['status'] : null;
        if (! is_array($status)) {
            return [
                'available' => false,
                'heading' => 'Live status unavailable',
                'summary' => 'Server status could not be fetched right now. Try again shortly.',
                'refreshed_at' => null,
                'refreshed_at_human' => null,
                'cards' => [],
                'worlds' => [],
            ];
        }

        $onlinePlayers = (int) data_get($status, 'players.online', 0);
        $maxPlayers = (int) data_get($status, 'players.max', 0);
        $worldRows = is_array($status['worlds'] ?? null) ? $status['worlds'] : [];
        $allWorlds = [];
        foreach ($worldRows as $world) {
            if (! is_array($world)) {
                continue;
            }

            $rawName = trim((string) ($world['name'] ?? ''));
            if ($rawName === '') {
                continue;
            }

            $allWorlds[$rawName] = $this->normalizePublicWorldDefinition($rawName);
        }
        $allWorlds = array_values($allWorlds);
        $visibleWorlds = array_values(array_filter($allWorlds, static fn (array $world): bool => $world['hidden'] !== true));

        $refreshedAt = null;
        if (is_string($status['timestamp'] ?? null) && trim((string) $status['timestamp']) !== '') {
            $refreshedAt = trim((string) $status['timestamp']);
        } elseif (is_numeric($snapshot['fetched_at_unix'] ?? null)) {
            $refreshedAt = now()->setTimestamp((int) $snapshot['fetched_at_unix'])->toIso8601String();
        }
        $refreshedAtHuman = null;
        if (is_string($refreshedAt) && trim($refreshedAt) !== '') {
            try {
                $refreshedAtHuman = \Illuminate\Support\Carbon::parse($refreshedAt)->diffForHumans();
            } catch (\Throwable) {
                $refreshedAtHuman = null;
            }
        }
        $communityTotals = $this->buildPublicCommunityTotals();

        return [
            'available' => true,
            'heading' => trim((string) ($status['server_name'] ?? 'STEMCraft')),
            'summary' => 'Public snapshot from the game server.',
            'refreshed_at' => $refreshedAt,
            'refreshed_at_human' => $refreshedAtHuman,
            'cards' => [
                [
                    'label' => 'Players online',
                    'value' => $maxPlayers > 0 ? sprintf('%d / %d', $onlinePlayers, $maxPlayers) : (string) $onlinePlayers,
                ],
                [
                    'label' => 'Version',
                    'value' => trim((string) ($status['minecraft_version'] ?? '-')) ?: '-',
                ],
                [
                    'label' => 'Hours played',
                    'value' => $communityTotals['hours_played'],
                ],
                [
                    'label' => 'Dist travelled',
                    'value' => $communityTotals['distance_travelled'],
                ],
                [
                    'label' => 'Blocks placed',
                    'value' => $communityTotals['blocks_placed'],
                ],
                [
                    'label' => 'TPS',
                    'value' => $this->formatPublicTps(data_get($status, 'tps.one_minute')),
                ],
            ],
            'worlds' => $this->buildPublicWorldEntries($visibleWorlds),
        ];
    }

    /**
     * @return array{hours_played: string, blocks_placed: string, distance_travelled: string}
     */
    private function buildPublicCommunityTotals(): array
    {
        $cacheKey = 'stemcraft:public-community-totals:v1';
        $cached = Cache::get($cacheKey);
        if (
            is_array($cached)
            && is_string($cached['hours_played'] ?? null)
            && is_string($cached['blocks_placed'] ?? null)
            && is_string($cached['distance_travelled'] ?? null)
        ) {
            return [
                'hours_played' => $cached['hours_played'],
                'blocks_placed' => $cached['blocks_placed'],
                'distance_travelled' => $cached['distance_travelled'],
            ];
        }

        $totalPlayHours = 0.0;
        $totalBlocksPlaced = 0.0;
        $totalDistanceTravelledMeters = 0.0;

        MinecraftPlayerStat::query()
            ->forPeriod(MinecraftPlayerStat::PERIOD_ALL)
            ->select(['id', 'stats'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows) use (&$totalPlayHours, &$totalBlocksPlaced, &$totalDistanceTravelledMeters): void {
                foreach ($rows as $playerStat) {
                    foreach ($playerStat->stats as $stat) {
                        if (! is_array($stat)) {
                            continue;
                        }

                        $key = trim((string) ($stat['key'] ?? ''));
                        $value = $stat['value'] ?? null;
                        if (! is_numeric($value)) {
                            continue;
                        }

                        if ($key === 'play_time') {
                            $totalPlayHours += (float) $value;

                            continue;
                        }

                        if (str_starts_with($key, 'blocks_placed_')) {
                            $totalBlocksPlaced += (float) $value;

                            continue;
                        }

                        if (in_array($key, ['distance_walked_km', 'distance_sprinted_km'], true)) {
                            $totalDistanceTravelledMeters += ((float) $value) * 1000;

                            continue;
                        }

                        if (in_array($key, ['distance_walked_m', 'distance_sprinted_m'], true)) {
                            $totalDistanceTravelledMeters += (float) $value;

                            continue;
                        }

                        if (in_array($key, ['distance_walked_cm', 'distance_sprinted_cm', 'distance_walk_one_cm', 'distance_sprint_one_cm'], true)) {
                            $totalDistanceTravelledMeters += ((float) $value) / 100;
                        }
                    }
                }
            });

        $totals = [
            'hours_played' => number_format($totalPlayHours, 2),
            'blocks_placed' => number_format((int) round($totalBlocksPlaced)),
            'distance_travelled' => $this->formatPublicDistanceMeters($totalDistanceTravelledMeters),
        ];

        Cache::put($cacheKey, $totals, now()->addMinutes(15));

        return $totals;
    }

    private function formatPublicDistanceMeters(int|float $meters): string
    {
        $absoluteMeters = abs((float) $meters);

        if ($absoluteMeters >= 1000) {
            return number_format(((float) $meters) / 1000, 2).' km';
        }

        if ($absoluteMeters >= 100) {
            return number_format((float) $meters, 0).' m';
        }

        if ($absoluteMeters >= 10) {
            return number_format((float) $meters, 1).' m';
        }

        return number_format((float) $meters, 2).' m';
    }

    private function formatPublicTps(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        return number_format((float) $value, 2);
    }

    /**
     * @param  list<array{name: string, raw: string, hidden: bool}>  $worlds
     * @return list<array{
     *     type: 'single'|'group',
     *     name: string,
     *     raw: string|null,
     *     summary: string|null,
     *     children: list<array{name: string, raw: string}>
     * }>
     */
    private function buildPublicWorldEntries(array $worlds): array
    {
        $singleWorlds = [];
        $groupBuckets = [];
        $activeGroups = $this->buildActivePublicWorldGroups($worlds);

        foreach ($worlds as $world) {
            $group = $this->resolvePublicWorldGroup($world, $activeGroups);
            if ($group === null) {
                $singleWorlds[] = $world;

                continue;
            }

            $groupBuckets[$group['key']] ??= [
                'key' => $group['key'],
                'name' => $group['name'],
                'item_label' => $group['item_label'],
                'members' => [],
            ];
            $groupBuckets[$group['key']]['members'][] = [
                'world' => $world,
                'child_token' => $group['child_token'],
            ];
        }

        foreach ($groupBuckets as $group) {
            if (count($group['members']) < 2) {
                foreach ($group['members'] as $member) {
                    $singleWorlds[] = $member['world'];
                }
            }
        }

        $singleWorlds = $this->deduplicatePublicWorldsByName($singleWorlds);
        usort($singleWorlds, function (array $left, array $right): int {
            $priorityComparison = $this->publicWorldPriority($left) <=> $this->publicWorldPriority($right);

            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return strnatcasecmp((string) $left['name'], (string) $right['name']);
        });

        $entries = array_map(static fn (array $world): array => [
            'type' => 'single',
            'name' => (string) $world['name'],
            'raw' => (string) $world['raw'],
            'summary' => null,
            'children' => [],
        ], $singleWorlds);

        foreach ($groupBuckets as $group) {
            if (count($group['members']) < 2) {
                continue;
            }

            $childTokens = array_values(array_map(
                static fn (array $member): string => trim((string) $member['child_token']),
                $group['members'],
            ));
            $usesDimensionLabels = $this->publicWorldGroupUsesDimensionLabels($childTokens);
            $children = [];
            foreach ($group['members'] as $member) {
                $childName = $this->formatPublicWorldGroupChildName(
                    (string) $group['name'],
                    trim((string) $member['child_token']),
                    $childTokens,
                );
                if ($childName === '') {
                    $childName = (string) $member['world']['name'];
                }

                $children[strtolower($childName)] = [
                    'name' => $childName,
                    'raw' => (string) $member['world']['raw'],
                ];
            }

            $children = array_values($children);
            usort($children, function (array $left, array $right) use ($usesDimensionLabels): int {
                $priorityComparison = $this->publicWorldGroupChildPriority((string) $left['name'], $usesDimensionLabels)
                    <=> $this->publicWorldGroupChildPriority((string) $right['name'], $usesDimensionLabels);
                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return strnatcasecmp((string) $left['name'], (string) $right['name']);
            });

            $entries[] = [
                'type' => 'group',
                'name' => (string) $group['name'],
                'raw' => null,
                'summary' => $this->formatPublicWorldGroupSummary(
                    (string) $group['name'],
                    count($children),
                    (string) ($group['item_label'] ?? 'world'),
                ),
                'children' => $children,
            ];
        }

        usort($entries, function (array $left, array $right): int {
            $priorityComparison = $this->publicWorldEntryPriority($left) <=> $this->publicWorldEntryPriority($right);
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return strnatcasecmp((string) $left['name'], (string) $right['name']);
        });

        return $entries;
    }

    /**
     * @param  list<array{name: string, raw: string, hidden: bool}>  $worlds
     * @return array<string, array{key: string, name: string, item_label: string}>
     */
    private function buildActivePublicWorldGroups(array $worlds): array
    {
        $candidateKeys = [];

        foreach ($worlds as $world) {
            $rawName = trim((string) ($world['raw'] ?? ''));
            foreach ($this->publicWorldGroupCandidateKeys($rawName) as $candidateKey) {
                $candidateKeys[$candidateKey] = true;
            }
        }

        $activeGroups = [];
        foreach (array_keys($candidateKeys) as $candidateKey) {
            $memberCount = 0;

            foreach ($worlds as $world) {
                if ($this->worldMatchesPublicGroupKey((string) ($world['raw'] ?? ''), $candidateKey)) {
                    $memberCount++;
                }
            }

            if ($memberCount < 2) {
                continue;
            }

            $definition = $this->publicWorldGroupDefinitions()[$candidateKey] ?? null;
            $activeGroups[$candidateKey] = [
                'key' => $candidateKey,
                'name' => is_array($definition)
                    ? (string) ($definition['name'] ?? $this->formatPublicWorldLabel($candidateKey))
                    : $this->formatPublicWorldLabel($candidateKey),
                'item_label' => $this->publicWorldGroupItemLabel($candidateKey),
            ];
        }

        return $activeGroups;
    }

    /**
     * @param  array{name: string, raw: string, hidden: bool}  $world
     * @return array{key: string, name: string, item_label: string, child_token: string}|null
     */
    private function resolvePublicWorldGroup(array $world, array $activeGroups): ?array
    {
        $rawName = trim((string) ($world['raw'] ?? ''));
        $matchingGroups = array_values(array_filter(
            $activeGroups,
            fn (array $group): bool => $this->worldMatchesPublicGroupKey($rawName, (string) ($group['key'] ?? '')),
        ));

        if ($matchingGroups === []) {
            return null;
        }

        usort($matchingGroups, static function (array $left, array $right): int {
            $lengthComparison = mb_strlen((string) ($right['key'] ?? '')) <=> mb_strlen((string) ($left['key'] ?? ''));

            if ($lengthComparison !== 0) {
                return $lengthComparison;
            }

            return strnatcasecmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? ''));
        });

        $group = $matchingGroups[0];

        return [
            'key' => (string) $group['key'],
            'name' => (string) $group['name'],
            'item_label' => (string) $group['item_label'],
            'child_token' => $this->extractPublicWorldGroupChildToken($rawName, (string) $group['key']),
        ];
    }

    /**
     * @return list<string>
     */
    private function publicWorldGroupCandidateKeys(string $rawName): array
    {
        $normalizedRawName = strtolower(trim($rawName));
        if ($normalizedRawName === '') {
            return [];
        }

        $candidates = [];
        foreach (array_keys($this->publicWorldGroupDefinitions()) as $knownKey) {
            if ($this->worldMatchesPublicGroupKey($normalizedRawName, $knownKey)) {
                $candidates[$knownKey] = true;
            }
        }

        $parts = preg_split('/[\s:_-]+/', $normalizedRawName, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return array_keys($candidates);
        }

        for ($index = 1; $index < count($parts); $index++) {
            $candidateKey = implode('_', array_slice($parts, 0, $index));

            if ($this->shouldSkipPublicWorldGroupKey($candidateKey)) {
                continue;
            }

            $candidates[$candidateKey] = true;
        }

        return array_keys($candidates);
    }

    private function shouldSkipPublicWorldGroupKey(string $candidateKey): bool
    {
        return in_array(strtolower(trim($candidateKey)), ['world'], true);
    }

    private function worldMatchesPublicGroupKey(string $rawName, string $groupKey): bool
    {
        $normalizedRawName = strtolower(trim($rawName));
        $normalizedGroupKey = strtolower(trim($groupKey));

        if ($normalizedRawName === '' || $normalizedGroupKey === '') {
            return false;
        }

        if ($normalizedRawName === $normalizedGroupKey) {
            return true;
        }

        return preg_match('/^'.preg_quote($normalizedGroupKey, '/').'[\s:_-]+.+$/', $normalizedRawName) === 1;
    }

    private function extractPublicWorldGroupChildToken(string $rawName, string $groupKey): string
    {
        if (strcasecmp(trim($rawName), trim($groupKey)) === 0) {
            return '';
        }

        if (preg_match('/^'.preg_quote(trim($groupKey), '/').'[\s:_-]+(.+)$/i', trim($rawName), $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return '';
    }

    /**
     * @return array<string, array{name: string}>
     */
    private function publicWorldGroupDefinitions(): array
    {
        return [
            'bridge' => [
                'name' => 'Bridge',
            ],
        ];
    }

    private function publicWorldGroupItemLabel(string $groupKey): string
    {
        return in_array($this->normalizePublicWorldGroupKey($groupKey), $this->publicWorldArenaGroupKeys(), true)
            ? 'arena'
            : 'world';
    }

    /**
     * @return list<string>
     */
    private function publicWorldArenaGroupKeys(): array
    {
        $default = SiteOption::defaultValue('minecraft.public-status-arena-groups') ?? '';
        $rawValue = trim((string) SiteOption::value('minecraft.public-status-arena-groups', $default));

        if ($rawValue === '') {
            return [];
        }

        $keys = [];
        foreach (explode(',', $rawValue) as $value) {
            $normalized = $this->normalizePublicWorldGroupKey($value);
            if ($normalized === '') {
                continue;
            }

            $keys[$normalized] = $normalized;
        }

        return array_values($keys);
    }

    /**
     * @param  list<array{name: string, raw: string, hidden: bool}>  $worlds
     * @return list<array{name: string, raw: string, hidden: bool}>
     */
    private function deduplicatePublicWorldsByName(array $worlds): array
    {
        $uniqueWorlds = [];

        foreach ($worlds as $world) {
            $name = trim((string) ($world['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $uniqueWorlds[strtolower($name)] ??= $world;
        }

        return array_values($uniqueWorlds);
    }

    /**
     * @param  array{
     *     type: 'single'|'group',
     *     name: string,
     *     raw: string|null,
     *     summary: string|null,
     *     children: list<array{name: string, raw: string}>
     * }  $entry
     */
    private function publicWorldEntryPriority(array $entry): int
    {
        return match (strtolower(trim((string) ($entry['name'] ?? '')))) {
            'lobby' => 0,
            'survival' => 10,
            'overworld' => 10,
            'nether' => 20,
            'the end' => 30,
            default => 100,
        };
    }

    /**
     * @param  array{name: string, raw: string, hidden: bool}  $world
     */
    private function publicWorldPriority(array $world): int
    {
        return $this->publicWorldEntryPriority([
            'type' => 'single',
            'name' => (string) ($world['name'] ?? ''),
            'raw' => (string) ($world['raw'] ?? ''),
            'summary' => null,
            'children' => [],
        ]);
    }

    /**
     * @param  list<string>  $allChildTokens
     */
    private function formatPublicWorldGroupChildName(string $groupName, string $childToken, array $allChildTokens): string
    {
        $normalizedToken = $this->normalizePublicWorldGroupChildToken($childToken);

        if ($this->publicWorldGroupUsesDimensionLabels($allChildTokens)) {
            return match ($normalizedToken) {
                '', 'overworld' => 'Overworld',
                'nether' => 'Nether',
                'the_end' => 'The End',
                default => $this->formatPublicWorldLabel($childToken),
            };
        }

        if ($normalizedToken === '') {
            return trim($groupName);
        }

        return $this->formatPublicWorldLabel($childToken);
    }

    /**
     * @param  list<string>  $allChildTokens
     */
    private function publicWorldGroupUsesDimensionLabels(array $allChildTokens): bool
    {
        $normalizedTokens = array_values(array_unique(array_map(
            fn (string $token): string => $this->normalizePublicWorldGroupChildToken($token),
            $allChildTokens,
        )));

        if (
            ! in_array('overworld', $normalizedTokens, true)
            && ! in_array('nether', $normalizedTokens, true)
            && ! in_array('the_end', $normalizedTokens, true)
            && ! in_array('', $normalizedTokens, true)
        ) {
            return false;
        }

        return array_diff($normalizedTokens, ['', 'overworld', 'nether', 'the_end']) === [];
    }

    private function publicWorldGroupChildPriority(string $childName, bool $usesDimensionLabels): int
    {
        if (! $usesDimensionLabels) {
            return 100;
        }

        return match (strtolower(trim($childName))) {
            'overworld' => 0,
            'nether' => 10,
            'the end' => 20,
            default => 100,
        };
    }

    private function normalizePublicWorldGroupChildToken(string $value): string
    {
        return match (strtolower(trim($value))) {
            'end', 'the_end', 'the-end', 'the end' => 'the_end',
            default => strtolower(trim($value)),
        };
    }

    private function normalizePublicWorldGroupKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[\s:-]+/', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }

    private function formatPublicWorldGroupSummary(string $groupName, int $count, string $itemLabel): string
    {
        $pluralizedLabel = $count === 1 ? $itemLabel : str($itemLabel)->plural($count)->toString();

        return sprintf('%s (%d %s)', $groupName, $count, $pluralizedLabel);
    }

    private function formatPublicWorldLabel(string $value): string
    {
        return (string) str($value)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->trim();
    }

    /**
     * @return array{name: string, raw: string, hidden: bool}
     */
    private function normalizePublicWorldDefinition(string $rawName): array
    {
        $normalized = strtolower(trim($rawName));

        return match ($normalized) {
            'world' => [
                'name' => 'Lobby',
                'raw' => $rawName,
                'hidden' => false,
            ],
            'world_nether', 'world_the_end' => [
                'name' => $this->formatPublicWorldLabel($rawName),
                'raw' => $rawName,
                'hidden' => true,
            ],
            'overworld', 'world_overworld' => [
                'name' => 'Overworld',
                'raw' => $rawName,
                'hidden' => false,
            ],
            'nether' => [
                'name' => 'Nether',
                'raw' => $rawName,
                'hidden' => false,
            ],
            'the_end', 'end' => [
                'name' => 'The End',
                'raw' => $rawName,
                'hidden' => false,
            ],
            default => [
                'name' => $this->formatPublicWorldLabel($rawName),
                'raw' => $rawName,
                'hidden' => false,
            ],
        };
    }
}
