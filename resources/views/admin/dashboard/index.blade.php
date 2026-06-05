<x-layout>
    <x-mast>Dashboard</x-mast>

    <x-container>
        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" class="flex flex-col gap-4">
                <div class="grid w-full gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <div class="w-full min-w-0">
                        <x-ui.select label="Period" name="period" onchange="this.form.submit()">
                            <option value="day" {{ $period === 'day' ? 'selected' : '' }}>This day</option>
                            <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This week</option>
                            <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This month</option>
                            <option value="quarter" {{ $period === 'quarter' ? 'selected' : '' }}>This quarter</option>
                            <option value="year" {{ $period === 'year' ? 'selected' : '' }}>This year</option>
                        </x-ui.select>
                    </div>
                    <div class="w-full min-w-0">
                        <div class="h-full rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            <div class="font-semibold">Selected range</div>
                            <div class="mt-1">
                                {{ $periodLabel }}: {{ $periodStart->format('d M Y') }} to {{ $periodEnd->format('d M Y') }}
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            @foreach($cards as $card)
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $card['title'] }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ $card['description'] }}</p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            @foreach(($card['links'] ?? []) as $link)
                                <x-ui.button type="link" href="{{ $link['route'] }}" color="secondary" class="!px-3 !py-1 !text-xs">
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
                                <div class="mt-2 text-xs text-gray-500">
                                    <span class="font-semibold {{ $metric['tone'] === 'emerald' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $metric['change'] }}</span>
                                    <span class="ml-1">vs previous period</span>
                                </div>
                                <div class="mt-1 text-xs text-gray-400">Previous: {{ $metric['previous'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Top Workshop Ticket Sales</h2>
                    <p class="mt-1 text-sm text-gray-500">Internal ticketed workshops with sales in the selected period.</p>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                <x-ui.table>
                    <x-slot:header>
                        <th>Workshop</th>
                        <th class="hidden md:table-cell">Start</th>
                        <th>Tickets</th>
                        <th class="hidden md:table-cell">Early Bird</th>
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
                                <td class="hidden md:table-cell">
                                    {{ $startsAt ? $startsAt->format('M j, Y g:ia') : '-' }}
                                </td>
                                <td>
                                    <div class="font-semibold text-gray-900">{{ number_format((int) $row['tickets_sold']) }}</div>
                                    <div class="mt-1 text-xs text-gray-500 md:hidden">Early bird: {{ number_format((int) $row['early_bird_tickets']) }}</div>
                                </td>
                                <td class="hidden md:table-cell">{{ number_format((int) $row['early_bird_tickets']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-gray-500">No ticket sales in this period.</td>
                            </tr>
                        @endforelse
                    </x-slot:body>
                </x-ui.table>
            </div>
        </div>
    </x-container>
</x-layout>
