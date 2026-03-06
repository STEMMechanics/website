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

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">See how you rank on the STEMCraft leaderboards.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">Cached STEMCraft player stats come from the Minecraft server and are updated every few hours, so check back to see if you’ve moved up the rankings.</p>
                </div>

                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach($periodOptions as $period)
                        <a
                            href="{{ route('stemcraft.leaderboards', ['period' => $period === MinecraftPlayerStat::PERIOD_ALL ? null : $period]) }}"
                            class="{{ $selectedPeriod === $period ? 'bg-primary-color text-white border-primary-color' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400' }} inline-flex rounded-full border px-4 py-2 text-sm font-semibold transition"
                        >
                            {{ MinecraftPlayerStat::periodLabel($period) }}
                        </a>
                    @endforeach
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tracked players</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $trackedPlayerCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last updated</div>
                        <div class="mt-2 text-sm font-semibold text-gray-900">{{ $lastSyncedAtAnyPeriod?->format('j M Y g:i a') ?? 'No sync has completed yet' }}</div>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Need your own stats?</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If your website account is linked to a STEMCraft player account, you can also see your all-time cached player stats on the STEMCraft page inside your account.</p>
                    <div class="mt-5 flex flex-col gap-3">
                        <x-ui.button href="{{ route('account.stemcraft.index') }}">My STEMCraft page</x-ui.button>
                        <x-ui.button href="{{ route('stemcraft.join') }}" color="primary-outline">How to join</x-ui.button>
                    </div>
                </section>
            </div>
        </div>

        @if($leaderboardStats === [])
            <section class="mt-6 rounded-3xl border border-dashed border-gray-300 bg-white p-6">
                <h3 class="text-lg font-semibold text-gray-900">No player stats available yet</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">The website has not cached any player stats from the plugin yet for {{ strtolower($periodLabel) }}. Once a sync has run, the leaderboards will appear here.</p>
            </section>
        @else
            <div class="mt-6 grid gap-6 xl:grid-cols-2">
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
                                <div class="flex items-center justify-between gap-4 rounded-2xl bg-gray-50 px-4 py-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary-color text-xs font-semibold text-white">{{ $row['rank'] }}</span>
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-semibold text-gray-900">{{ $row['username'] }}<span class="truncate text-xs font-mono text-gray-500 font-normal ml-2">({{ strtolower((string) ($row['platform'] ?: '-')) }})</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right text-sm font-semibold text-gray-900">{{ $row['formatted_value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </x-container>
</x-layout>
