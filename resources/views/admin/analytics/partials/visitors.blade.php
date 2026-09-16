@if($activeVisitors === null)
    <p class="rounded-xl border border-gray-200 bg-white p-5 text-gray-600">Visitor information is currently unavailable.</p>
@elseif($activeVisitors->isEmpty())
    <p class="rounded-xl border border-gray-200 bg-white p-5 text-gray-600">No visitors are online right now.</p>
@else
    <x-ui.table variant="listing" :mobile-cards="true" class="online-visitors-table">
        <x-slot:header>
            <th>Visitor</th>
            <th>Last page visited</th>
            <th data-column-align="center" class="whitespace-nowrap">Page views</th>
            <th class="visitor-session-column">Session started</th>
            <th class="visitor-connection-column">IP address / location</th>
            <th class="visitor-browser-column">Browser</th>
            <th class="whitespace-nowrap">Last active</th>
        </x-slot:header>
        <x-slot:body>
            @foreach($activeVisitors as $visitor)
                @php
                    $startedAt = $visitor['started_at'] ? \Carbon\Carbon::createFromTimestamp($visitor['started_at'])->timezone(config('app.timezone')) : null;
                    $seenAt = \Carbon\Carbon::createFromTimestamp($visitor['seen_at'])->timezone(config('app.timezone'));
                    $lastActive = strtr($seenAt->diffForHumans(), ['seconds' => 'secs', 'second' => 'sec', 'minutes' => 'mins', 'minute' => 'min', 'hours' => 'hrs', 'hour' => 'hr']);
                @endphp
                <tr>
                    <td data-label="Visitor" data-mobile-primary>
                        <span class="font-semibold">{{ $visitor['name'] }}</span>
                        <span class="block text-xs text-gray-500">{{ $visitor['signed_in'] ? 'Signed in' : 'Anonymous visitor' }}</span>
                        <div class="visitor-connection-summary mt-2 text-xs text-gray-500">
                            <span class="block break-all">{{ $visitor['ip'] ?? 'Not recorded yet' }}</span>
                            <span class="block">{{ $visitor['location'] ?? 'Location unavailable' }}</span>
                            <span class="block mt-1" title="{{ $visitor['user_agent'] ?? '' }}">{{ $visitor['browser'] ?? 'Not recorded yet' }}</span>
                        </div>
                    </td>
                    <td data-label="Last page visited" data-mobile-wide class="visitor-page-column break-all">
                        @if($visitor['path'] && str_starts_with($visitor['path'], '/') && ! str_starts_with($visitor['path'], '//') && ! str_contains($visitor['path'], '\\'))
                            <a class="text-primary hover:underline" href="{{ url($visitor['path']) }}">{{ $visitor['path'] }}</a>
                        @else
                            {{ $visitor['path'] ?? 'Not recorded yet' }}
                        @endif
                    </td>
                    <td data-label="Page views" class="tabular-nums">{{ $visitor['page_views'] ?? 'Not recorded yet' }}</td>
                    <td data-label="Session started" class="visitor-session-column">
                        @if($startedAt)
                            <time datetime="{{ $startedAt->toIso8601String() }}" title="{{ $startedAt->format('j M Y, g:i:s a T') }}">
                                <span class="block whitespace-nowrap">{{ $startedAt->format('j M Y') }}</span>
                                <span class="block whitespace-nowrap text-xs text-gray-500">{{ $startedAt->format('g:i a T') }}</span>
                            </time>
                        @else
                            Not recorded yet
                        @endif
                    </td>
                    <td data-label="IP address / location" class="visitor-connection-column">
                        <span class="break-all">{{ $visitor['ip'] ?? 'Not recorded yet' }}</span>
                        <span class="block text-xs text-gray-500">{{ $visitor['location'] ?? 'Location unavailable' }}</span>
                        <span class="visitor-browser-summary mt-1 text-xs text-gray-500" title="{{ $visitor['user_agent'] ?? '' }}">{{ $visitor['browser'] ?? 'Not recorded yet' }}</span>
                    </td>
                    <td data-label="Browser" class="visitor-browser-column" title="{{ $visitor['user_agent'] ?? '' }}">{{ $visitor['browser'] ?? 'Not recorded yet' }}</td>
                    <td data-label="Last active">
                        <time class="whitespace-nowrap" datetime="{{ $seenAt->toIso8601String() }}" title="{{ $seenAt->format('j M Y, g:i:s a T') }}">{{ $lastActive }}</time>
                        <span class="visitor-session-summary mt-1 text-xs text-gray-500">
                            @if($startedAt)
                                Started <time datetime="{{ $startedAt->toIso8601String() }}" title="{{ $startedAt->format('j M Y, g:i:s a T') }}"><span class="whitespace-nowrap">{{ $startedAt->isToday() ? $startedAt->format('g:i a') : $startedAt->format('j M, g:i a') }}</span></time>
                            @else
                                Session start not recorded
                            @endif
                        </span>
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-ui.table>
@endif
