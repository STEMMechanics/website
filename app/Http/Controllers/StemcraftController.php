<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPenalty;
use App\Models\MinecraftPlayerStat;
use App\Services\MinecraftWebhookBridgeService;
use App\Support\MinecraftPlayerStatFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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
     *     worlds: list<array{name: string, raw: string}>
     * }
     */
    private function buildPublicServerInfo(MinecraftWebhookBridgeService $minecraftWebhookBridgeService): array
    {
        $connection = $minecraftWebhookBridgeService->connectionSummary();
        if (! ($connection['configured'] ?? false)) {
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
                    'label' => 'Worlds',
                    'value' => (string) count($allWorlds),
                ],
                [
                    'label' => 'Version',
                    'value' => trim((string) ($status['minecraft_version'] ?? '-')) ?: '-',
                ],
                [
                    'label' => 'TPS',
                    'value' => $this->formatPublicTps(data_get($status, 'tps.one_minute')),
                ],
            ],
            'worlds' => array_map(static fn (array $world): array => [
                'name' => (string) $world['name'],
                'raw' => (string) $world['raw'],
            ], array_slice($visibleWorlds, 0, 8)),
        ];
    }

    private function formatPublicTps(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        return number_format((float) $value, 2);
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
                'name' => (string) str($rawName)
                    ->replace(['_', '-'], ' ')
                    ->title()
                    ->trim(),
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
                'name' => (string) str($rawName)
                    ->replace(['_', '-'], ' ')
                    ->title()
                    ->trim(),
                'raw' => $rawName,
                'hidden' => false,
            ],
        };
    }
}
