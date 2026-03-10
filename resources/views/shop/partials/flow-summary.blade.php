@php
    $heading = trim((string) ($heading ?? 'Order Summary'));
    $rows = is_iterable($rows ?? null) ? $rows : [];
@endphp

<div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
    <div class="mb-3 text-base font-semibold text-gray-900">{{ $heading }}</div>
    <table class="text-sm">
        @foreach($rows as $row)
            @php($valueClass = trim((string) ($row['value_class'] ?? 'text-gray-900')))
            <tr>
                <th class="w-28 pr-4 pb-2 text-left align-top font-medium text-gray-600">{{ $row['label'] ?? '-' }}</th>
                <td class="pb-2 {{ $valueClass }}">{{ $row['value'] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
</div>
