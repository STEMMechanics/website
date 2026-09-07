<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Pricing &amp; Cost Breakdown</title>
    <style>
        @page { margin: 35pt 38pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $fontSize }}pt; color: #151515; line-height: 1.25; }
        h1 { font-size: 17pt; margin: 0 0 5pt; }
        h2 { font-size: 12pt; margin: 0 0 4pt; }
        p { margin: 0 0 8pt; }
        .meta, .note { font-size: 8pt; color: #444; }
        .grid { width: 100%; border-collapse: collapse; margin: 16pt 0 0; }
        .grid > tbody > tr > td { width: 48%; vertical-align: top; padding: 0 0 14pt; }
        .grid > tbody > tr > td.gap { width: 4%; }
        .rates { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .rates td, .rates th { border: 0.5pt solid #999; padding: 4pt; text-align: left; }
        .rates th { background: #c6c8c8; font-weight: bold; }
        .rates tr:nth-child(even) td { background: #f1f1f1; }
        .rates .money { text-align: right; }
        .rates .seats { text-align: center; width: 22%; }
        .subtitle { font-size: 8pt; margin-bottom: 7pt; }
        .section { margin-top: 12pt; }
        .costs td, .costs th { padding: 3pt 5pt; }
        .footer { margin-top: 12pt; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php($money = fn ($cents) => '$'.number_format($cents / 100, 2))
    <h1>Workshop Pricing &amp; Cost Breakdown</h1>
    <p class="meta">{{ $version->name }} · Internal reference · {{ now()->format('d M Y') }}</p>
    <table class="grid"><tbody>
        @foreach([[1, 2], [3, 4]] as $pair)
            <tr>
                @foreach($pair as $hours)
                    @if(!$loop->first)<td class="gap"></td>@endif
                    <td>
                        <h2>{{ $hours }}-Hour Workshop</h2>
                        <p class="subtitle">{{ $money($durations[$hours]['standard']) }} (Standard) / {{ $money($durations[$hours]['school']) }} (School/Library) per person</p>
                        <table class="rates">
                            <thead><tr><th class="seats">Participants</th><th>Standard<br>Charge P/P</th><th>School / Library<br>Charge P/W</th></tr></thead>
                            <tbody>@foreach([10, 15, 20] as $seats)
                                <tr><td class="seats">{{ $seats }}</td><td class="money">{{ $money($durations[$hours]['standard']) }}</td><td class="money">{{ $money($durations[$hours]['school'] * $seats) }}</td></tr>
                            @endforeach</tbody>
                        </table>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody></table>
    <div class="section">
        <h2>Travel Costs</h2>
        <p class="subtitle">Travel is charged one-way after the first {{ $freeMinutes }} minutes at {{ $money($travelRate) }} per 15 minutes, including GST.</p>
        @if($travelBreakdown)<p class="note">Allocation breakdown (ex GST): {{ $travelBreakdown }}.</p>@endif
        <table class="grid" style="margin-top: 8pt"><tbody><tr>
            @foreach(array_chunk($regions, 4) as $group)
                @if(!$loop->first)<td class="gap"></td>@endif
                <td><table class="rates"><thead><tr><th style="width:16%">Region</th><th>Location</th><th style="width:27%" class="money">Charge</th></tr></thead>
                    <tbody>@foreach($group as $region)<tr><td class="seats">{{ $region['units'] }}</td><td>{{ $region['name'] }}</td><td class="money">{{ $money($region['units'] * $travelRate) }}</td></tr>@endforeach</tbody>
                </table></td>
            @endforeach
        </tr></tbody></table>
    </div>
    <div class="section">
        <h2>Workshop Cost Breakdown <span class="note">(allocation rates excluding GST)</span></h2>
        <table class="rates costs"><thead><tr><th style="width:32%">Cost item</th><th>Amount</th></tr></thead>
            <tbody>@foreach($breakdown as $name => $amounts)<tr><td>{{ $name }}</td><td>{{ implode('; ', $amounts) }}</td></tr>@endforeach</tbody>
        </table>
    </div>
    <div class="footer note">
        <p>Customer prices include GST. P/P = per person; P/W = per workshop. Rates use {{ $participants }} pricing participants; school/library assumes venue supplied. Travel regions use the reference sheet's billable 15-minute units.</p>
        <p>Generated from the default allocation plan. Internal estimates only.</p>
    </div>
</body>
</html>
