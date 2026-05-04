<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Materials Summary - {{ $currentMonthLabel }}</title>
    <style>
        @include('pdf.partials.styling')
        @page { margin: 12mm; size: A4 landscape; }

        body { line-height: 1.1; padding-bottom: 8mm; }
        .document-title { color: #1da1e6; font-size: 18px; font-weight: 700; line-height: 1; }
        .document-subtitle { margin-top: -10px; color: #333; font-size: 24px; font-weight: 700; line-height: 1.1; }
        .materials { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .materials thead th { padding: 5px 4px; text-align: center; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #1da1e6; border-bottom: 1px solid #666; line-height: 1.05; }
        .materials tbody td { padding: 5px 4px; border-bottom: 1px solid #e3e3e3; vertical-align: top; font-size: 10px; }
        .materials tbody tr { page-break-inside: avoid; }
        .materials th.item, .materials td.item { text-align: left; }
        .materials th.total, .materials td.total { text-align: right; }
        .item-name { font-size: 10px; font-weight: 700; color: #333; line-height: 1.05; }
        .workshop-head { font-size: 9px; font-weight: 700; color: #333; line-height: 1.05; word-break: break-word; }
        .workshop-head .date { display: block; margin-top: 2px; font-size: 8px; font-weight: 400; color: #666; }
        .workshop-value { text-align: center; font-size: 10px; font-weight: 700; color: #333; line-height: 1; }
        .total-value { text-align: right; font-size: 10px; font-weight: 700; color: #333; line-height: 1; }
        .empty-state { text-align: center; font-size: 12px; color: #666; padding: 18px 0; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('invoice-logo.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('apple-touch-icon.png');
        }
    @endphp

    <table class="header">
        <tr>
            <td class="logo-wrap">
                @if(file_exists($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                @endif
            </td>
            <td class="headline" style="vertical-align: middle">
                <div class="document-title">Workshop Materials Summary</div>
                <div class="document-subtitle">{{ $currentMonthLabel }}</div>
            </td>
        </tr>
    </table>

    @php
        $workshopColumns = $workshopSummaries->values();
        $workshopCount = max(1, $workshopColumns->count());
        $itemColumnWidth = 34;
        $totalColumnWidth = 8;
        $workshopColumnWidth = (100 - $itemColumnWidth - $totalColumnWidth) / $workshopCount;
    @endphp

    <table class="materials">
        <thead>
            <tr>
                <th class="item" style="width: {{ $itemColumnWidth }}%;">Item</th>
                @foreach($workshopColumns as $summary)
                    @php
                        $workshop = $summary['workshop'];
                    @endphp
                    <th style="width: {{ $workshopColumnWidth }}%;">
                        <div class="workshop-head">
                            {{ (string) ($workshop->title ?? 'Workshop') }}
                            <span class="date">{{ $workshop->starts_at?->format('D j M') ?? '-' }}</span>
                        </div>
                    </th>
                @endforeach
                <th class="total" style="width: {{ $totalColumnWidth }}%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materialRows as $row)
                @php
                    $breakdowns = $row['workshopBreakdowns']->keyBy('workshop_id');
                @endphp
                <tr>
                    <td class="item">
                        <div class="item-name">{{ (string) ($row['item_name'] ?? '') }}</div>
                    </td>
                    @foreach($workshopColumns as $summary)
                        @php
                            $workshopId = (string) $summary['workshop']->getKey();
                            $quantity = (int) ($breakdowns->get($workshopId)['quantity'] ?? 0);
                        @endphp
                        <td class="workshop-value">{{ $quantity > 0 ? $quantity : '-' }}</td>
                    @endforeach
                    <td class="total">
                        <div class="total-value">{{ (int) ($row['total_quantity'] ?? 0) }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + $workshopColumns->count() }}" class="empty-state">No workshop materials found for this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
