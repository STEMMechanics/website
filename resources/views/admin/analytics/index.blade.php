<x-layout>
    <x-mast>Analytics</x-mast>

    <x-container>
        <form method="GET" class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-ui.select label="Date Range" name="days">
                    <option value="7" {{ $days === 7 ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ $days === 30 ? 'selected' : '' }}>Last 30 days</option>
                    <option value="90" {{ $days === 90 ? 'selected' : '' }}>Last 90 days</option>
                    <option value="365" {{ $days === 365 ? 'selected' : '' }}>Last 365 days</option>
                </x-ui.select>
            </div>
            <div class="mb-4">
                <x-ui.button type="submit" color="outline">Update</x-ui.button>
            </div>
            <div class="ml-auto grid grid-cols-1 md:grid-cols-3 gap-3 text-xs text-gray-600">
                <div>
                    <div class="font-semibold text-gray-700">Analytics Table Size</div>
                    <div>{{ $analyticsMeta['table_size_human'] ?? 'Unavailable' }}</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-700">Oldest Record</div>
                    <div>{{ $analyticsMeta['oldest_record_at'] ? \Carbon\Carbon::parse($analyticsMeta['oldest_record_at'])->format('M j, Y g:i a') : 'No records' }}</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-700">Total Records</div>
                    <div>{{ number_format((int) ($analyticsMeta['total_records'] ?? 0)) }}</div>
                </div>
            </div>
        </form>

        <form id="analytics-prune-form" method="POST" action="{{ route('admin.analytics.prune') }}" class="mb-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-3">
            @csrf
            <input type="hidden" name="days" value="{{ $days }}">
            <div class="w-44">
                <x-ui.select label="Prune Older Than" name="prune_days">
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90" selected>90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">365 days</option>
                </x-ui.select>
            </div>
            <div class="mb-4">
                <x-ui.button type="button" color="danger-outline" x-data x-on:click.prevent="confirmAnalyticsPrune()">Prune Records</x-ui.button>
            </div>
        </form>

        <div class="my-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Page Views</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($totals['views']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Sessions</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($totals['sessions']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Unique Visitors (Hashed)</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($totals['visitors']) }}</div>
            </div>
        </div>

        <div id="analytics-daily-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Daily Activity</h3>
            <p class="text-sm text-gray-600 mb-3">Last 7 days, newest to oldest.</p>
            <x-ui.table>
                <x-slot:header>
                    <th>Date</th>
                    <th>Views</th>
                    <th>Sessions</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($daily as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->day)->format('M j, Y') }}</td>
                            <td>{{ number_format((int) $row->views) }}</td>
                            <td>{{ number_format((int) $row->sessions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-500">No data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $daily->appends(request()->query())->links() }}
            </div>
        </div>

        <div id="analytics-hourly-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Hourly Activity</h3>
            <p class="text-sm text-gray-600 mb-3">Last 12 hours.</p>
            <x-ui.table>
                <x-slot:header>
                    <th>Hour</th>
                    <th>Users</th>
                    <th>Sessions</th>
                    <th>Views</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($activeHours as $row)
                        <tr>
                            @php
                                $hourStart = \Carbon\Carbon::parse($row->hour_bucket);
                                $hourEnd = (clone $hourStart)->addHour();
                            @endphp
                            <td>{{ $hourStart->format('M j, Y') }} {{ $hourStart->format('g:ia') }} - {{ $hourEnd->format('g:ia') }}</td>
                            <td>{{ number_format((int) $row->users) }}</td>
                            <td>{{ number_format((int) $row->sessions) }}</td>
                            <td>{{ number_format((int) $row->views) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-gray-500">No hourly activity data in this range.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $activeHours->appends(request()->query())->links() }}
            </div>
        </div>

        <div id="analytics-top-pages-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Top Pages</h3>
            <x-ui.table>
                <x-slot:header>
                    <th>Path</th>
                    <th>Views</th>
                    <th>Sessions</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($topPages as $row)
                        <tr>
                            <td class="font-mono text-xs">{{ $row->path }}</td>
                            <td>{{ number_format((int) $row->views) }}</td>
                            <td>{{ number_format((int) $row->sessions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-500">No data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $topPages->appends(request()->query())->links() }}
            </div>
        </div>

        <div id="analytics-top-workshops-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Top Workshops</h3>
            <x-ui.table>
                <x-slot:header>
                    <th>Workshop</th>
                    <th>Views</th>
                    <th>Sessions</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($topWorkshops as $row)
                        <tr>
                            <td>{{ $row->workshop_title }}</td>
                            <td>{{ number_format((int) $row->views) }}</td>
                            <td>{{ number_format((int) $row->sessions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-500">No workshop page views yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $topWorkshops->appends(request()->query())->links() }}
            </div>
        </div>

        <div id="analytics-top-searches-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Top Search Terms</h3>
            <x-ui.table>
                <x-slot:header>
                    <th>Search</th>
                    <th>Uses</th>
                    <th>Sessions</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($topSearches as $row)
                        <tr>
                            <td>{{ $row->search_term }}</td>
                            <td>{{ number_format((int) $row->uses) }}</td>
                            <td>{{ number_format((int) $row->sessions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-500">No searches recorded yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $topSearches->appends(request()->query())->links() }}
            </div>
        </div>

        <div id="analytics-session-flows-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Recent Session Flows</h3>
            <p class="text-sm text-gray-600 mb-3">Shows grouped session path flow and hashed visitor marker so you can follow journeys without storing personal details.</p>
            <x-ui.table>
                <x-slot:header>
                    <th>Session</th>
                    <th class="md:hidden">Details</th>
                    <th class="hidden md:table-cell">Visitor Hash</th>
                    <th class="hidden lg:table-cell">Started</th>
                    <th class="hidden lg:table-cell">Duration</th>
                    <th class="hidden md:table-cell">Events</th>
                    <th>Flow</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($sessionFlows as $flow)
                        @php
                            $startedAt = \Carbon\Carbon::parse($flow['started_at']);
                            $endedAt = \Carbon\Carbon::parse($flow['ended_at']);
                            $durationSeconds = max(0, $startedAt->diffInSeconds($endedAt));
                            $durationHours = intdiv($durationSeconds, 3600);
                            $durationMinutes = intdiv($durationSeconds % 3600, 60);
                            $durationRemainingSeconds = $durationSeconds % 60;
                            $durationLabel = $durationHours > 0
                                ? sprintf('%dh %02dm %02ds', $durationHours, $durationMinutes, $durationRemainingSeconds)
                                : sprintf('%dm %02ds', $durationMinutes, $durationRemainingSeconds);
                        @endphp
                        <tr>
                            <td class="font-mono text-xs">{{ substr($flow['session_token'], 0, 12) }}</td>
                            <td class="md:hidden">
                                <div class="md:hidden text-xs">Visitor: <span class="font-mono">{{ $flow['visitor_hash'] !== '' ? substr($flow['visitor_hash'], 0, 12) : '-' }}</span></div>
                                <div class="lg:hidden text-xs text-gray-600">Start: {{ \Carbon\Carbon::parse($flow['started_at'])->format('M j, Y g:i a') }}</div>
                                <div class="lg:hidden text-xs text-gray-600">Duration: {{ $durationLabel }}</div>
                                <div class="md:hidden text-xs text-gray-600">Events: {{ number_format($flow['event_count']) }}</div>
                            </td>
                            <td class="hidden md:table-cell font-mono text-xs">{{ $flow['visitor_hash'] !== '' ? substr($flow['visitor_hash'], 0, 12) : '-' }}</td>
                            <td class="hidden lg:table-cell">{{ \Carbon\Carbon::parse($flow['started_at'])->format('M j, Y g:i a') }}</td>
                            <td class="hidden lg:table-cell">{{ $durationLabel }}</td>
                            <td class="hidden md:table-cell">{{ number_format($flow['event_count']) }}</td>
                            <td class="text-xs">
                                @if($flow['steps'] === [])
                                    -
                                @else
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach($flow['steps'] as $step)
                                            @php
                                                $displayStep = (string) $step;
                                                if ($displayStep === '/') {
                                                    $displayStep = '/home';
                                                } elseif (str_starts_with($displayStep, '/ (')) {
                                                    $displayStep = '/home'.substr($displayStep, 1);
                                                }
                                            @endphp
                                            <li class="break-words">{{ $displayStep }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-gray-500">No session data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $sessionFlows->appends(request()->query())->links() }}
            </div>
        </div>

        <div id="analytics-returning-visitors-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Returning Visitors (Hashed)</h3>
            <x-ui.table>
                <x-slot:header>
                    <th>Visitor Hash</th>
                    <th class="md:hidden">Details</th>
                    <th class="hidden md:table-cell">Views</th>
                    <th class="hidden md:table-cell">Sessions</th>
                    <th class="hidden lg:table-cell">Last Seen</th>
                </x-slot:header>
                <x-slot:body>
                    @forelse($returningVisitors as $visitor)
                        <tr>
                            <td class="font-mono text-xs">{{ substr((string) $visitor->visitor_hash, 0, 16) }}</td>
                            <td class="md:hidden">
                                <div class="md:hidden text-xs text-gray-600">Views: {{ number_format((int) $visitor->views) }}</div>
                                <div class="md:hidden text-xs text-gray-600">Sessions: {{ number_format((int) $visitor->sessions) }}</div>
                                <div class="lg:hidden text-xs text-gray-600">Last: {{ \Carbon\Carbon::parse($visitor->last_seen)->format('M j, Y g:i a') }}</div>
                            </td>
                            <td class="hidden md:table-cell">{{ number_format((int) $visitor->views) }}</td>
                            <td class="hidden md:table-cell">{{ number_format((int) $visitor->sessions) }}</td>
                            <td class="hidden lg:table-cell">{{ \Carbon\Carbon::parse($visitor->last_seen)->format('M j, Y g:i a') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-gray-500">No returning visitor data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                {{ $returningVisitors->appends(request()->query())->links() }}
            </div>
        </div>
    </x-container>
</x-layout>

<script>
    const analyticsScrollKey = 'analytics:scrollY';

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-analytics-section] a[href*="_page="]');
        if (!link) {
            return;
        }
        sessionStorage.setItem(analyticsScrollKey, String(window.scrollY || 0));
    });

    window.addEventListener('load', function () {
        const savedScroll = sessionStorage.getItem(analyticsScrollKey);
        if (savedScroll === null) {
            return;
        }

        sessionStorage.removeItem(analyticsScrollKey);
        const scrollY = Number(savedScroll);
        if (!Number.isFinite(scrollY)) {
            return;
        }

        window.scrollTo({ top: Math.max(0, scrollY), behavior: 'auto' });
    });

    function confirmAnalyticsPrune() {
        const form = document.getElementById('analytics-prune-form');
        if (!form || !window.SM || typeof window.SM.confirm !== 'function') {
            form && form.submit();
            return;
        }

        const select = form.querySelector('select[name="prune_days"]');
        const selectedLabel = select && select.options[select.selectedIndex]
            ? select.options[select.selectedIndex].text
            : 'the selected period';

        window.SM.confirm(
            'Confirm prune',
            `Delete analytics records older than ${selectedLabel}? This cannot be undone.`,
            'Prune',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                form.submit();
            }
        );
    }
</script>
