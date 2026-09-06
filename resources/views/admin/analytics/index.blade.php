<x-layout>
    <x-mast>Analytics</x-mast>

    <x-container>
        <x-ui.dynamic-list name="admin-analytics-index">

        <x-ui.period-presets name="days" :value="$days" :options="[30 => 'Last 30 days', 7 => 'Last 7 days', 90 => 'Last 90 days', 365 => 'Last 365 days']" label="Date range" />
        <div class="my-4">
            <x-ui.grid class="ml-auto md:grid-cols-3 gap-3 text-xs text-gray-600">
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
            </x-ui.grid>
        </div>

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

        <x-ui.grid class="my-4 md:grid-cols-3 gap-4">
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
        </x-ui.grid>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Workshop Recommendations</h3>
            <x-ui.grid class="gap-4 sm:grid-cols-3">
                <div><div class="text-xs uppercase tracking-wide text-gray-500">Card impressions</div><div class="mt-1 text-2xl font-bold">{{ number_format((int) $recommendationAnalytics['impressions']) }}</div></div>
                <div><div class="text-xs uppercase tracking-wide text-gray-500">Clicks</div><div class="mt-1 text-2xl font-bold">{{ number_format((int) $recommendationAnalytics['clicks']) }}</div></div>
                <div><div class="text-xs uppercase tracking-wide text-gray-500">Click-through rate</div><div class="mt-1 text-2xl font-bold">{{ number_format((float) $recommendationAnalytics['click_through_rate'], 1) }}%</div></div>
            </x-ui.grid>
            @if($recommendationAnalytics['placements']->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2 text-xs text-gray-600">
                    @foreach($recommendationAnalytics['placements'] as $placement)
                        <x-ui.badge class="bg-gray-100">{{ str_replace('_', ' ', ucfirst((string) ($placement->recommendation_placement ?: 'unknown'))) }}: {{ number_format((int) $placement->clicks) }} / {{ number_format((int) $placement->impressions) }}</x-ui.badge>
                    @endforeach
                </div>
            @endif
        </div>

        <div id="analytics-traffic-sources-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-1">Traffic Sources</h3>
            <p class="mb-3 text-sm text-gray-600">First-touch source for sessions that began in the selected date range. UTM attribution takes precedence over the referring website.</p>
            <x-ui.collection-controls scope="analytics_traffic_sources" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_traffic_sources" label="Source" />
                    <x-ui.list-heading scope="analytics_traffic_sources" label="Medium" />
                    <x-ui.list-heading scope="analytics_traffic_sources" label="Campaign" />
                    <x-ui.list-heading scope="analytics_traffic_sources" label="Sessions" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($trafficSources as $source)
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
                            <td>{{ $source->campaign ?: '—' }}</td>
                            <td>{{ number_format((int) $source->sessions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-gray-500">No traffic source data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                <x-ui.list-pagination :paginator="$trafficSources" />
            </div>
        </div>

        <div id="analytics-landing-pages-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-1">Landing Pages</h3>
            <p class="mb-3 text-sm text-gray-600">The first page viewed in each session.</p>
            <x-ui.collection-controls scope="analytics_landing_pages" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_landing_pages" label="Landing page" />
                    <x-ui.list-heading scope="analytics_landing_pages" label="Sessions" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($landingPages as $landingPage)
                        <tr>
                            <td class="font-mono text-xs">{{ $landingPage->landing_path === '/' ? '/home' : $landingPage->landing_path }}</td>
                            <td>{{ number_format((int) $landingPage->sessions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-gray-500">No landing page data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                <x-ui.list-pagination :paginator="$landingPages" />
            </div>
        </div>

        <div id="analytics-daily-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Daily Activity</h3>
            <p class="text-sm text-gray-600 mb-3">Last 7 days, newest to oldest.</p>
            <x-ui.collection-controls scope="analytics_daily" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_daily" class="text-center!" label="Date" />
                    <x-ui.list-heading scope="analytics_daily" label="Views" />
                    <x-ui.list-heading scope="analytics_daily" label="Sessions" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($daily as $row)
                        <tr>
                            <td class="text-center!"><x-ui.date-time>{{ \Carbon\Carbon::parse($row->day)->format('M j, Y') }}</x-ui.date-time></td>
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
                <x-ui.list-pagination :paginator="$daily" />
            </div>
        </div>

        <div id="analytics-hourly-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Hourly Activity</h3>
            <p class="text-sm text-gray-600 mb-3">Last 12 hours.</p>
            <x-ui.collection-controls scope="analytics_hour" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_hour" class="text-center!" label="Hour" />
                    <x-ui.list-heading scope="analytics_hour" label="Users" />
                    <x-ui.list-heading scope="analytics_hour" label="Sessions" />
                    <x-ui.list-heading scope="analytics_hour" label="Views" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($activeHours as $row)
                        <tr>
                            @php
                                $hourStart = \Carbon\Carbon::parse($row->hour_bucket);
                                $hourEnd = (clone $hourStart)->addHour();
                            @endphp
                            <td class="text-center!"><x-ui.date-time>{{ $hourStart->format('M j, Y') }}</x-ui.date-time> <x-ui.date-time>{{ $hourStart->format('g:ia') }}</x-ui.date-time> - <x-ui.date-time>{{ $hourEnd->format('g:ia') }}</x-ui.date-time></td>
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
                <x-ui.list-pagination :paginator="$activeHours" />
            </div>
        </div>

        <div id="analytics-top-pages-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Top Pages</h3>
            <x-ui.collection-controls scope="analytics_top_pages" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_top_pages" label="Path" />
                    <x-ui.list-heading scope="analytics_top_pages" label="Views" />
                    <x-ui.list-heading scope="analytics_top_pages" label="Sessions" />
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
                <x-ui.list-pagination :paginator="$topPages" />
            </div>
        </div>

        <div id="analytics-top-workshops-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Top Workshops</h3>
            <x-ui.collection-controls scope="analytics_top_workshops" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_top_workshops" label="Workshop" />
                    <x-ui.list-heading scope="analytics_top_workshops" label="Views" />
                    <x-ui.list-heading scope="analytics_top_workshops" label="Sessions" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($topWorkshops as $row)
                        @php
                            $startsAt = $row->workshop_starts_at ? \Carbon\Carbon::parse($row->workshop_starts_at) : null;
                            $locationName = trim((string) ($row->workshop_location_name ?? ''));
                            if ($locationName === '' && $row->workshop_location_id === null && $startsAt) {
                                $locationName = 'Online';
                            }
                            $workshopMeta = collect([
                                $startsAt?->format('M j, Y g:i a'),
                                $locationName !== '' ? $locationName : null,
                            ])->filter()->implode(' | ');
                        @endphp
                        <tr>
                            <td>
                                <div>{{ $row->workshop_title }}</div>
                                @if($workshopMeta !== '')
                                    <div class="mt-1 text-xs text-gray-500">{{ $workshopMeta }}</div>
                                @endif
                            </td>
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
                <x-ui.list-pagination :paginator="$topWorkshops" />
            </div>
        </div>

        <div id="analytics-top-searches-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Top Search Terms</h3>
            <x-ui.collection-controls scope="analytics_top_searches" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_top_searches" label="Search" />
                    <x-ui.list-heading scope="analytics_top_searches" label="Uses" />
                    <x-ui.list-heading scope="analytics_top_searches" label="Sessions" />
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
                <x-ui.list-pagination :paginator="$topSearches" />
            </div>
        </div>

        <div id="analytics-session-flows-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Recent Session Flows</h3>
            <p class="text-sm text-gray-600 mb-3">Shows grouped session path flow and hashed visitor marker so you can follow journeys without storing personal details.</p>
            <x-ui.collection-controls scope="analytics_session_flows" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_session_flows" label="Session" />
                    <x-ui.list-heading scope="analytics_session_flows" class="md:hidden" label="Details" />
                    <x-ui.list-heading scope="analytics_session_flows" class="hidden md:table-cell" label="Visitor Hash" />
                    <x-ui.list-heading scope="analytics_session_flows" class="hidden lg:table-cell" label="Started" />
                    <x-ui.list-heading scope="analytics_session_flows" class="hidden lg:table-cell" label="Duration" />
                    <x-ui.list-heading scope="analytics_session_flows" class="hidden md:table-cell" label="Events" />
                    <x-ui.list-heading scope="analytics_session_flows" label="Flow" />
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
                                <div class="lg:hidden text-xs text-gray-600">Start: <x-ui.date-time>{{ \Carbon\Carbon::parse($flow['started_at'])->format('M j, Y g:i a') }}</x-ui.date-time></div>
                                <div class="lg:hidden text-xs text-gray-600">Duration: {{ $durationLabel }}</div>
                                <div class="md:hidden text-xs text-gray-600">Events: {{ number_format($flow['event_count']) }}</div>
                            </td>
                            <td class="hidden md:table-cell font-mono text-xs">{{ $flow['visitor_hash'] !== '' ? substr($flow['visitor_hash'], 0, 12) : '-' }}</td>
                            <td class="hidden lg:table-cell"><x-ui.date-time>{{ \Carbon\Carbon::parse($flow['started_at'])->format('M j, Y g:i a') }}</x-ui.date-time></td>
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
                                            <li class="wrap-break-word">{{ $displayStep }}</li>
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
                <x-ui.list-pagination :paginator="$sessionFlows" />
            </div>
        </div>

        <div id="analytics-returning-visitors-section" data-analytics-section class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Returning Visitors (Hashed)</h3>
            <x-ui.collection-controls scope="analytics_returning_visitors" class="mb-4" label="Search these results" />
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading scope="analytics_returning_visitors" label="Visitor Hash" />
                    <x-ui.list-heading scope="analytics_returning_visitors" class="md:hidden" label="Details" />
                    <x-ui.list-heading scope="analytics_returning_visitors" class="hidden md:table-cell" label="Views" />
                    <x-ui.list-heading scope="analytics_returning_visitors" class="hidden md:table-cell" label="Sessions" />
                    <x-ui.list-heading scope="analytics_returning_visitors" class="hidden lg:table-cell" label="Last Seen" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($returningVisitors as $visitor)
                        <tr>
                            <td class="font-mono text-xs">{{ substr((string) $visitor->visitor_hash, 0, 16) }}</td>
                            <td class="md:hidden">
                                <div class="md:hidden text-xs text-gray-600">Views: {{ number_format((int) $visitor->views) }}</div>
                                <div class="md:hidden text-xs text-gray-600">Sessions: {{ number_format((int) $visitor->sessions) }}</div>
                                <div class="lg:hidden text-xs text-gray-600">Last: <x-ui.date-time>{{ \Carbon\Carbon::parse($visitor->last_seen)->format('M j, Y g:i a') }}</x-ui.date-time></div>
                            </td>
                            <td class="hidden md:table-cell">{{ number_format((int) $visitor->views) }}</td>
                            <td class="hidden md:table-cell">{{ number_format((int) $visitor->sessions) }}</td>
                            <td class="hidden lg:table-cell"><x-ui.date-time>{{ \Carbon\Carbon::parse($visitor->last_seen)->format('M j, Y g:i a') }}</x-ui.date-time></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-gray-500">No returning visitor data yet.</td>
                        </tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            <div class="mt-4">
                <x-ui.list-pagination :paginator="$returningVisitors" />
            </div>
        </div>

        </x-ui.dynamic-list>
    </x-container>
</x-layout>

<script>
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
