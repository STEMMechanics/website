<x-layout>
    <x-mast title="Dashboard">
        <x-slot:actions><x-online-visitors /></x-slot:actions>
    </x-mast>

    <x-container>
        <x-ui.dynamic-list name="admin-dashboard-index">

        <div class="mt-4 flex flex-col items-start gap-4">
            @php
                $actionCards = is_array($actionCards ?? null) ? $actionCards : [];
                $actionColumns = min(4, max(2, (int) ceil(count($actionCards) / 2)));
                $actionIconTones = [
                    'sky' => 'bg-sky-50 text-sky-700 group-hover:bg-sky-100',
                    'pink' => 'bg-pink-50 text-pink-700 group-hover:bg-pink-100',
                    'violet' => 'bg-violet-50 text-violet-700 group-hover:bg-violet-100',
                    'emerald' => 'bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100',
                    'amber' => 'bg-amber-50 text-amber-700 group-hover:bg-amber-100',
                ];
            @endphp
            @if($actionCards !== [])
            <section class="w-full" aria-label="Suggested actions">
                <div data-dashboard-action-cards data-refresh-url="{{ route('admin.dashboard.actions') }}" data-dismiss-url="{{ route('admin.dashboard.actions.dismiss') }}" style="--sm-dashboard-action-columns: {{ $actionColumns }}" class="sm-dashboard-action-grid w-full">
                    @foreach($actionCards as $action)
                        <article data-action-card class="group relative flex h-full min-w-0 flex-col rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-primary-color/40 hover:shadow-md">
                            <a href="{{ $action['url'] }}" class="flex min-h-24 min-w-0 flex-1 cursor-pointer items-start gap-3 rounded-2xl p-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-color focus-visible:ring-offset-2">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl text-lg transition {{ $actionIconTones[$action['tone'] ?? 'sky'] ?? $actionIconTones['sky'] }}" aria-hidden="true">
                                    <i class="{{ $action['icon'] }}"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-base font-semibold leading-snug text-gray-900 {{ !empty($action['title_no_wrap']) ? 'whitespace-nowrap' : '' }}">{{ $action['title'] }}</span>
                                    @if(is_array($action['attendance_details'] ?? null))
                                        @php
                                            $attendanceDetails = $action['attendance_details'];
                                        @endphp
                                        <span class="mt-1.5 block text-sm font-semibold leading-snug text-gray-900">{{ $attendanceDetails['workshop'] }}</span>
                                        <span class="mt-1.5 block text-sm leading-snug text-gray-700">{{ $attendanceDetails['schedule'] }}</span>
                                        <span class="mt-1 block text-sm leading-snug text-gray-700">{{ $attendanceDetails['location'] }}</span>
                                    @else
                                        <span class="mt-1.5 block text-sm leading-snug text-gray-700">{{ $action['description'] }}</span>
                                    @endif
                                </span>
                            </a>
                            @if(!empty($action['dismiss_key']))
                                <div class="flex justify-end border-t border-gray-100 px-3 py-1.5">
                                    <button type="button" data-dismiss-dashboard-action data-action-key="{{ $action['dismiss_key'] }}" class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-color" aria-label="Hide this BAS action">
                                        <i class="fa-solid fa-eye-slash" aria-hidden="true"></i><span>Hide action</span>
                                    </button>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
            @endif

            @include('admin.dashboard.partials.weekly-workplan', ['workplan' => $workplan])

            <div class="w-full">
                <x-ui.period-presets name="period" :value="$period" :showFilter="false" :options="['overview' => 'Last 12 Months', 'all' => 'All time', 'day' => 'This day', 'week' => 'This week', 'month' => 'This month', 'quarter' => 'This quarter', 'year' => 'This year']">
                    @isset($snapshotAt)
                        <x-slot:actions>
                            <p class="whitespace-nowrap text-xs text-gray-500">Figures updated {{ \Illuminate\Support\Carbon::parse($snapshotAt)->format('g:ia') }}.</p>
                        </x-slot:actions>
                    @endisset
                    <span class="text-xs text-gray-600">{{ $periodStart->format('d M Y') }} to {{ $periodEnd->format('d M Y') }}</span>
                </x-ui.period-presets>
            </div>
        </div>

        <div data-list-results class="mt-4 grid gap-4 xl:grid-cols-2">
            @foreach($cards as $card)
                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $card['title'] }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ $card['description'] }}</p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            @foreach(($card['links'] ?? []) as $link)
                                <x-ui.button type="link" href="{{ $link['route'] }}" color="secondary" class="px-3! py-1! text-xs!">
                                    <i class="{{ $link['icon'] }} mr-2"></i>{{ $link['label'] }}
                                </x-ui.button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach($card['metrics'] as $metric)
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $metric['label'] }}</div>
                                <div class="mt-2 text-2xl font-bold text-gray-900">{{ $metric['current'] }}</div>
                                @if($period !== 'all' && ($metric['compare'] ?? true))
                                <div class="mt-2 text-xs text-gray-500">
                                    <span class="font-semibold {{ $metric['tone'] === 'emerald' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $metric['change'] }}</span>
                                    <span class="ml-1">vs previous period</span>
                                </div>
                                <div class="mt-1 text-xs text-gray-400">Previous: {{ $metric['previous'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @php $cardChart = collect($charts)->firstWhere('card', $card['title']); @endphp
                    @if($cardChart)
                        <x-ui.trend-chart :chart="$cardChart" />
                    @endif
                </section>
            @endforeach
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Top 10 Traffic Sources</h2>
                    <p class="mt-1 text-sm text-gray-500">First-touch sources for sessions in the selected period.</p>
                </div>
                <a href="{{ route('admin.analytics.index') }}" class="text-sm font-semibold text-primary-color hover:underline">View full analytics report</a>
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading label="Source" />
                        <x-ui.list-heading label="Medium" />
                        <x-ui.list-heading class="text-right" label="Sessions" />
                        <x-ui.list-heading class="text-right" label="Percentage" />
                    </x-slot:header>
                    <x-slot:body>
                        @forelse($trafficSourceRows as $source)
                            <tr>
                                <td>
                                    <div class="font-semibold text-gray-900">{{ $source->source }}</div>
                                    @if($source->source_urls !== [])
                                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs font-normal">
                                            @foreach($source->source_urls as $sourceHost)
                                                <a href="https://{{ $sourceHost }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:underline">{{ $sourceHost }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $source->medium }}</td>
                                <td class="text-right">{{ number_format((int) $source->sessions) }}</td>
                                <td class="text-right font-semibold text-gray-900">{{ number_format((float) $source->percentage, 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-gray-500">No traffic source data yet.</td>
                            </tr>
                        @endforelse
                    </x-slot:body>
                </x-ui.table>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Top 10 Media Downloads</h2>
                    <p class="mt-1 text-sm text-gray-500">Files with the most explicit download requests in the selected period.</p>
                </div>
                <a href="{{ route('admin.media.downloads', ['from' => $periodStart->toDateString(), 'to' => $periodEnd->copy()->subSecond()->toDateString(), 'limit' => 100]) }}" class="text-sm font-semibold text-primary-color hover:underline">View top 100</a>
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading label="File" />
                        <x-ui.list-heading label="Type" />
                        <x-ui.list-heading class="text-right" label="Requests" />
                    </x-slot:header>
                    <x-slot:body>
                        @forelse($mediaDownloadRows as $row)
                            <tr>
                                <td><a href="{{ route('admin.media.edit', ['media' => $row->media_name]) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $row->title }}</a><div class="text-xs text-gray-500">{{ $row->media_name }}</div></td>
                                <td>{{ $row->mime_type }}</td>
                                <td class="text-right font-semibold">{{ number_format((int) $row->downloads) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-gray-500">No media downloads in this period.</td></tr>
                        @endforelse
                    </x-slot:body>
                </x-ui.table>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Top 10 Workshop Activity</h2>
                    <p class="mt-1 text-sm text-gray-500">Workshop views and registration activity in the selected period.</p>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading label="Workshop" />
                        <x-ui.list-heading class="text-center" label="Views" />
                        <x-ui.list-heading class="hidden md:table-cell" label="Start" />
                        <x-ui.list-heading class="text-center" label="Registrations" />
                    </x-slot:header>
                    <x-slot:body>
                        @forelse($workshopSalesRows as $row)
                            @php
                                $startsAt = trim((string) ($row['workshop_starts_at'] ?? '')) !== ''
                                    ? \Carbon\Carbon::parse($row['workshop_starts_at'])
                                    : null;
                                $location = trim((string) ($row['location_name'] ?? ''));
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.workshop.tickets', ['workshop' => $row['workshop_id']]) }}" class="font-semibold text-gray-900 hover:text-primary-color">
                                        {{ $row['workshop_title'] }}
                                    </a>
                                    @if($location !== '')
                                        <div class="mt-1 text-xs text-gray-500">{{ $location }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="font-semibold text-gray-900">{{ number_format((int) $row['views']) }}</div>
                                </td>
                                <td class="hidden md:table-cell">
                                    <x-ui.date-time>{{ $startsAt ? $startsAt->format('M j, Y g:ia') : '-' }}</x-ui.date-time>
                                </td>
                                <td class="text-center">
                                    @if($row['registration_count'] !== null)
                                        <div class="whitespace-nowrap">
                                            <span class="font-semibold text-gray-900">{{ number_format((int) $row['registration_count']) }}</span>
                                            <span class="text-xs text-gray-500">{{ $row['registration_label'] }}</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-gray-500">No workshop activity in this period.</td>
                            </tr>
                        @endforelse
                    </x-slot:body>
                </x-ui.table>
            </div>
        </div>

        <div class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Top 10 Store Item Views and Sales</h2>
                        <p class="mt-1 text-sm text-gray-500">Store item views and item sales in the selected period.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                    <x-ui.table variant="listing">
                        <x-slot:header>
                            <x-ui.list-heading label="Item" />
                            <x-ui.list-heading label="Views" />
                            <x-ui.list-heading label="Items Sold" />
                        </x-slot:header>
                        <x-slot:body>
                            @forelse($storeSalesRows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.shop.product.edit', ['product' => $row['product_id']]) }}" class="font-semibold text-gray-900 hover:text-primary-color">
                                            {{ $row['product_title'] }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-gray-900">{{ number_format((int) $row['views']) }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-gray-900">{{ number_format((int) $row['items_sold']) }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-gray-500">No item views or sales in this period.</td>
                                </tr>
                            @endforelse
                        </x-slot:body>
                    </x-ui.table>
                </div>
            </div>

            @isset($checkoutActivity)
                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-gray-900">Store checkouts</h2>
                        <a href="{{ route('admin.analytics.checkout', ['from' => $periodStart->toDateString(), 'to' => $periodEnd->toDateString()]) }}" class="text-sm font-semibold text-primary-color hover:underline">View report</a>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @foreach(['completed' => ['Successful checkouts', 'Orders completed'], 'inactive' => ['Abandoned carts', 'Inactive carts']] as $key => [$label, $countKey])
                            <div class="min-w-0 rounded-xl bg-gray-50 p-3">
                                <p class="text-xs text-gray-600">{{ $label }}</p>
                                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($checkoutActivity[$countKey]) }}</p>
                                <dl class="mt-3 space-y-1 border-t border-gray-200 pt-2 text-xs">
                                    @foreach(['lowest' => 'Lowest', 'highest' => 'Highest', 'median' => 'Median'] as $stat => $statLabel)
                                        <div class="flex flex-wrap justify-between gap-x-2">
                                            <dt class="text-gray-500">{{ $statLabel }}</dt>
                                            <dd class="font-medium text-gray-900">{{ $checkoutValues[$key][$stat] === null ? '—' : '$'.number_format($checkoutValues[$key][$stat], 2) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Carts started in the selected period. Abandoned means no activity for 24 hours without completing an order. Values are item subtotals before discounts and delivery.</p>
                </section>
            @endisset
        </div>

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
