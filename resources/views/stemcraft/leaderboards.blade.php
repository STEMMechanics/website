@php
    use App\Models\MinecraftPlayerStat;

    $tabs = [
        ['title' => 'Overview', 'route' => route('stemcraft.index')],
        ['title' => 'Join', 'route' => route('stemcraft.join')],
        ['title' => 'Rules', 'route' => route('stemcraft.rules')],
        ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ['title' => 'Leaderboard', 'route' => route('stemcraft.leaderboards')],
        ['title' => 'Punishments', 'route' => route('stemcraft.punishments')],
    ];
@endphp

<x-layout>
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">Player Stats</x-mast>

    @php
        $isDetailMode = $selectedPlayerStat instanceof MinecraftPlayerStat;
        $selectedPlayer = $isDetailMode ? $selectedPlayerStat : null;
        $backUrl = route('stemcraft.leaderboards', array_filter([
            'period' => $selectedPeriod === MinecraftPlayerStat::PERIOD_ALL ? null : $selectedPeriod,
            'search' => $search !== '' ? $search : null,
        ], fn ($value) => $value !== null && $value !== ''));
        $periodLinkParameters = fn (string $period) => array_filter([
            'period' => $period === MinecraftPlayerStat::PERIOD_ALL ? null : $period,
            'search' => $search !== '' ? $search : null,
            'player' => $isDetailMode ? (string) $selectedPlayer?->uuid : ($playerLookup !== '' ? $playerLookup : null),
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <x-container inner-class="max-w-6xl" class="py-8">
        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            @if($isDetailMode)
                @php
                    $selectedStatRows = $selectedPlayer?->statRows() ?? [];
                    $account = $selectedPlayer?->account;
                @endphp
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <h2 class="mt-4 text-3xl font-semibold text-gray-900">{{ $selectedPlayer?->username }}</h2>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <x-ui.button href="{{ $backUrl }}" color="primary-outline">Back to rankings</x-ui.button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach($periodOptions as $period)
                            <a
                                href="{{ route('stemcraft.leaderboards', $periodLinkParameters($period)) }}"
                                class="{{ $selectedPeriod === $period ? 'bg-primary-color text-white border-primary-color' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400' }} inline-flex rounded-full border px-4 py-2 text-sm font-semibold transition"
                            >
                                {{ MinecraftPlayerStat::periodLabel($period) }}
                            </a>
                        @endforeach
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-gray-100 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Platform</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ ucfirst((string) ($selectedPlayer?->platform ?: '-')) }}</div>
                        </div>
                        <div class="rounded-2xl bg-gray-100 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Period</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ MinecraftPlayerStat::periodLabel($selectedPlayer?->period ?? MinecraftPlayerStat::PERIOD_ALL) }}</div>
                        </div>
                        <div class="rounded-2xl bg-gray-100 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">UUID</div>
                            <div class="mt-2 break-all text-sm font-mono font-semibold text-gray-900">{{ $selectedPlayer?->uuid }}</div>
                        </div>
                        <div class="rounded-2xl bg-gray-100 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Updated</div>
                            <div class="mt-2 text-sm font-semibold text-gray-900">{{ $selectedPlayer?->captured_at?->format('j M Y g:i a') ?? '-' }}</div>
                            @if($account)
                                <div class="mt-2 text-sm text-gray-600">
                                    Linked account: <span class="font-semibold text-gray-900">{{ $account->user?->username ?: $account->user?->getName() ?: 'Linked profile' }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($selectedStatRows !== [])
                        <div>
                            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">All stats</div>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($selectedStatRows as $stat)
                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $stat['title'] }}</div>
                                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $stat['formatted_value'] }}</div>
                                        @if($stat['description'] !== '')
                                            <div class="mt-2 text-xs leading-5 text-gray-500">{{ $stat['description'] }}</div>
                                        @endif
                                        @if($stat['updated_at'])
                                            <div class="mt-2 text-xs text-gray-400">Updated {{ \Carbon\Carbon::parse($stat['updated_at'])->format('j M Y g:i a') }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(($selectedPlayerUsernameHistory ?? []) !== [])
                        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-1">
                                <h3 class="text-lg font-semibold text-gray-900">Previous usernames</h3>
                                <p class="text-sm leading-6 text-gray-600">Username changes recorded for this player.</p>
                            </div>

                            <div class="mt-4 space-y-3">
                                @foreach($selectedPlayerUsernameHistory as $change)
                                    <div class="rounded-2xl bg-gray-100 px-4 py-3">
                                        <div class="text-sm font-semibold text-gray-900">{{ $change['old_username'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500">Changed to {{ $change['new_username'] }} on {{ $change['occurred_at'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @else
                @php
                    $showSearchResults = $search !== '';
                    $showRankingsPanels = ! $showSearchResults;
                @endphp
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                    <div class="min-w-0">
                        <form method="GET" action="{{ route('stemcraft.leaderboards') }}" class="mt-6 flex gap-3 flex-row items-center">
                            <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                            <x-ui.input class="mb-0 flex-1" floating name="search" label="Player search" value="{{ $search }}" :suggestions="$playerSuggestions" show-suggestions-on-focus="true" />
                            <x-ui.button type="submit" class="h-12">Search</x-ui.button>
                        </form>

                        <div class="mt-8 flex flex-wrap gap-2">
                            @foreach($periodOptions as $period)
                                <a
                                    href="{{ route('stemcraft.leaderboards', $periodLinkParameters($period)) }}"
                                    class="{{ $selectedPeriod === $period ? 'bg-primary-color text-white border-primary-color' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400' }} inline-flex rounded-full border px-4 py-2 text-sm font-semibold transition"
                                >
                                    {{ MinecraftPlayerStat::periodLabel($period) }}
                                </a>
                            @endforeach
                        </div>

                        @if($showRankingsPanels)
                            <div class="mt-8 grid gap-4 md:grid-cols-2">
                                <div class="rounded-2xl bg-gray-100 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tracked players</div>
                                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $trackedPlayerCount }}</div>
                                </div>
                                <div class="rounded-2xl bg-gray-100 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last updated</div>
                                    <div class="mt-2 text-sm font-semibold text-gray-900">{{ $lastSyncedAtAnyPeriod?->format('j M Y g:i a') ?? 'No sync has completed yet' }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($showRankingsPanels)
                        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Server info</h2>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-1">
                                @foreach($serverInfo['cards'] ?? [] as $card)
                                    <div class="rounded-2xl bg-gray-100 px-3 py-3">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $card['label'] ?? '-' }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $card['value'] ?? '-' }}</dd>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @endif
        </section>

        @unless($isDetailMode)
            @if($search !== '')
                <section class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Search results</div>
                    @if($matchingPlayers->isEmpty())
                        <p class="mt-3 text-sm text-gray-600">No cached players matched "{{ $search }}".</p>
                    @else
                        <div class="mt-3 space-y-2">
                            @foreach($matchingPlayers->take(8) as $match)
                                @php
                                    $matchRoute = route('stemcraft.leaderboards', [
                                        'period' => $selectedPeriod === MinecraftPlayerStat::PERIOD_ALL ? null : $selectedPeriod,
                                        'search' => $search,
                                        'player' => $match->uuid,
                                    ]);
                                @endphp
                                <a href="{{ $matchRoute }}" class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-gray-300 hover:bg-white">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900">{{ $match->username }}</div>
                                        <div class="truncate text-xs text-gray-500">{{ ucfirst((string) ($match->platform ?: '-')) }} · {{ $match->uuid }}</div>
                                    </div>
                                    <span class="shrink-0 text-sm font-semibold text-primary-color">View</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @else
                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                    @if($leaderboardStats === [])
                        <section class="rounded-3xl border border-dashed border-gray-300 bg-white p-6">
                            <h3 class="text-lg font-semibold text-gray-900">No player stats available yet</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600">The website has not cached any player stats from the plugin yet for {{ strtolower($periodLabel) }}. Once a sync has run, the leaderboards will appear here.</p>
                        </section>
                    @else
                        @foreach($leaderboardStats as $stat)
                            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                                <div class="flex flex-col gap-1">
                                    <h2 class="text-lg font-semibold text-gray-900">{{ $stat['title'] }}</h2>
                                    @if($stat['description'] !== '')
                                        <p class="text-sm leading-6 text-gray-600">{{ $stat['description'] }}</p>
                                    @endif
                                </div>

                                <div class="mt-4 space-y-3">
                                    @foreach($stat['rows'] as $row)
                                        @php
                                            $rowRouteParameters = ['period' => $selectedPeriod, 'player' => $row['uuid']];
                                            $rowUrl = route('stemcraft.leaderboards', $rowRouteParameters);
                                        @endphp
                                        <a
                                            href="{{ $rowUrl }}"
                                            class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-100 px-4 py-3 transition hover:border-gray-300 hover:bg-white"
                                        >
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary-color text-xs font-semibold text-white">{{ $row['rank'] }}</span>
                                                    <div class="min-w-0">
                                                        <div class="truncate text-sm font-semibold text-gray-900">{{ $row['username'] }}<span class="truncate text-xs font-mono text-gray-500 font-normal ml-2">({{ ucfirst((string) ($row['platform'] ?: '-')) }})</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="shrink-0 text-right text-sm font-semibold text-gray-900">{{ $row['formatted_value'] }}</div>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    @endif
                </div>
            @endif
        @endunless
    </x-container>
</x-layout>
