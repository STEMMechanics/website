@php
    $rows = is_iterable($rows ?? null) ? $rows : [];
    $resolvedTotalActionLabel = trim((string) ($totalActionLabel ?? ''));
    $resolvedTotalActionAttributes = new \Illuminate\View\ComponentAttributeBag(is_array($totalActionAttributes ?? null) ? $totalActionAttributes : []);
@endphp

<table class="text-sm mb-4">
    <tr>
        <th class="text-left pr-4 w-24">Workshop</th>
        <td>{{ $workshop->title }}</td>
    </tr>
    <tr>
        <th class="text-left pr-4">Date</th>
        <td>{{ $workshop->getTicketTimeRangeLabel() }}</td>
    </tr>
    @if(!empty($workshop->hosted_for))
    <tr>
        <th class="text-left pr-4 align-top">Hosted For</th>
        <td>
            {{ $workshop->hosted_for }}
        </td>
    </tr>
    @endif
    @if(!$workshop->isPrivate())
    <tr>
        <th class="text-left pr-4 align-top">Location</th>
        <td>
            {{ $workshop->getLocationDisplay() }}
        </td>
    </tr>
    @endif
    @foreach($rows as $row)
        @php
            $rowType = trim((string) ($row['type'] ?? 'data'));
            $label = trim((string) ($row['label'] ?? '-'));
            $valueClass = trim((string) ($row['value_class'] ?? 'text-gray-900'));
        @endphp
        @if($rowType === 'spacer')
            <tr aria-hidden="true">
                <td colspan="2" class="h-3"></td>
            </tr>
            @continue
        @endif
        <tr>
            <th class="text-left pr-4">{{ $label }}</th>
            <td class="{{ $valueClass }}">
                @if($label === 'Total Cost' && $resolvedTotalActionLabel !== '')
                    <div class="relative w-full pr-28">
                        <button {{ $resolvedTotalActionAttributes->merge(['type' => 'button', 'class' => 'absolute right-0 top-1/2 inline-flex shrink-0 -translate-y-1/2 items-center justify-center rounded-md border border-gray-400 bg-white px-4 py-1 text-xs font-semibold leading-6 text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color disabled:cursor-not-allowed disabled:opacity-50']) }}>
                            {{ $resolvedTotalActionLabel }}
                        </button>
                        <span class="font-semibold">{{ $row['value'] ?? '-' }}</span>
                    </div>
                @else
                    {{ $row['value'] ?? '-' }}
                @endif
            </td>
        </tr>
    @endforeach
</table>
