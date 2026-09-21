@php
    $rows = is_iterable($rows ?? null) ? $rows : [];
    $resolvedTotalActionLabel = trim((string) ($totalActionLabel ?? ''));
    $resolvedTotalActionAttributes = new \Illuminate\View\ComponentAttributeBag(is_array($totalActionAttributes ?? null) ? $totalActionAttributes : []);
@endphp

<x-ui.table variant="plain" table-class="text-sm mb-4">
    @if($showWorkshopDetails ?? true)
    <tr>
        <th class="text-left pr-4 w-24">Workshop</th>
        <td>{{ $workshop->title }}</td>
    </tr>
    <tr>
        <th class="text-left pr-4 align-top">Date</th>
        <td>
            @if($workshop->isCourse() && $workshop->courseWeeklySummary() === null)
                <ul class="list-disc pl-4 space-y-1">
                    @foreach($workshop->courseScheduleDisplayLines() as $session)<li>{{ $session }}</li>@endforeach
                </ul>
            @else
                {{ $workshop->getTicketTimeRangeLabel() }}
            @endif
        </td>
    </tr>
    @if($workshop->isPrivate() && !empty($workshop->hostedFor))
    <tr>
        <th class="text-left pr-4 align-top">Hosted For</th>
        <td>
            {{ $workshop->hostedFor->name }}
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
    @endif
    @foreach($rows as $row)
        @php
            $rowType = trim((string) ($row['type'] ?? 'data'));
            $label = trim((string) ($row['label'] ?? '-'));
            $valueClass = trim((string) ($row['value_class'] ?? 'text-gray-900'));
            $valueHtml = $row['value_html'] ?? null;
        @endphp
        @if($rowType === 'spacer')
            <tr aria-hidden="true">
                <td colspan="2" class="h-3"></td>
            </tr>
            @continue
        @endif
        @if($rowType === 'ticket')
            <tr>
                <td colspan="2" class="py-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span class="min-w-0 flex-1 basis-48 font-semibold">{{ $label }}</span>
                        <span class="shrink-0 whitespace-nowrap text-gray-700">{{ $row['value'] ?? '-' }}</span>
                    </div>
                </td>
            </tr>
            @continue
        @endif
        @if($label === 'Total Cost' && $resolvedTotalActionLabel !== '')
            <tr>
                <td colspan="2" class="pt-3">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3">
                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 font-semibold"><span>{{ $label }}</span><span class="whitespace-nowrap">{{ $row['value'] ?? '-' }}</span></div>
                        <x-ui.button variant="plain" :type="$resolvedTotalActionAttributes->get('type', 'button')" :button-attributes="$resolvedTotalActionAttributes->merge(['type' => 'button', 'class' => 'shrink-0 rounded-md border border-gray-400 bg-white px-4 py-1 text-xs font-semibold leading-6 text-gray-800 shadow-sm hover:bg-gray-500 hover:text-white'])">{{ $resolvedTotalActionLabel }}</x-ui.button>
                    </div>
                </td>
            </tr>
            @continue
        @endif
        <tr>
            <th class="text-left pr-4">{{ $label }}</th>
            <td class="{{ $valueClass }}">
                @if($valueHtml !== null)
                    {!! $valueHtml !!}
                @else
                    {{ $row['value'] ?? '-' }}
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.table>
