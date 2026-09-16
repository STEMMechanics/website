@if($activeVisitors === null)
    <p class="rounded-xl border border-gray-200 bg-white p-5 text-gray-600">Visitor information is currently unavailable.</p>
@elseif($activeVisitors->isEmpty())
    <p class="rounded-xl border border-gray-200 bg-white p-5 text-gray-600">No visitors are online right now.</p>
@else
    <x-ui.table variant="listing" :mobile-cards="true">
        <x-slot:header>
            <th>Visitor</th>
            <th>Last page visited</th>
            <th>Last active</th>
        </x-slot:header>
        <x-slot:body>
            @foreach($activeVisitors as $visitor)
                <tr>
                    <td data-label="Visitor">
                        <span class="font-semibold">{{ $visitor['name'] }}</span>
                        <span class="block text-xs text-gray-500">{{ $visitor['signed_in'] ? 'Signed in' : 'Anonymous visitor' }}</span>
                    </td>
                    <td data-label="Last page visited" class="break-all">
                        @if($visitor['path'] && str_starts_with($visitor['path'], '/') && ! str_starts_with($visitor['path'], '//') && ! str_contains($visitor['path'], '\\'))
                            <a class="text-primary hover:underline" href="{{ url($visitor['path']) }}">{{ $visitor['path'] }}</a>
                        @else
                            {{ $visitor['path'] ?? 'Not recorded yet' }}
                        @endif
                    </td>
                    <td data-label="Last active">
                        <time datetime="{{ \Carbon\Carbon::createFromTimestamp($visitor['seen_at'])->toIso8601String() }}" title="{{ \Carbon\Carbon::createFromTimestamp($visitor['seen_at'])->timezone(config('app.timezone'))->format('j M Y, g:i:s a') }}">{{ \Carbon\Carbon::createFromTimestamp($visitor['seen_at'])->diffForHumans() }}</time>
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-ui.table>
@endif
