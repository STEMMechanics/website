<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Workshop History</title>
    <style>
        @page { margin: 18mm 12mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #000; font-size: 9px; }
        h1 { margin: 0 0 3px; font-size: 20px; color: #000; }
        .logo { position: absolute; top: 0; right: 0; width: 145px; height: auto; }
        .meta { margin-bottom: 12px; color: #000; font-size: 9px; }
        .filters { margin: 0 0 12px; color: #000; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { padding: 6px 5px; border: 1px solid #d1d5db; background: #f3f4f6; color: #000; text-align: center; vertical-align: middle; font-size: 12px; }
        td { padding: 6px 5px; border: 1px solid #e5e7eb; vertical-align: middle; }
        .workshop-column { text-align: left; }
        .center { text-align: center; }
        .date { white-space: nowrap; text-align: center; }
        .footer { position: fixed; left: 0; bottom: -9mm; color: #000; font-size: 9px; }
    </style>
</head>
<body>
    <img class="logo" src="{{ public_path('logo.png') }}" alt="STEMMechanics">
    <h1>Workshop History</h1>

    @if($filters !== [])
        <div class="filters">{{ implode(' · ', $filters) }}</div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 11%">Date</th>
                <th class="workshop-column" style="width: {{ $showStatus ? '25%' : '28%' }}">Workshop</th>
                <th style="width: {{ $showStatus ? '18%' : '21%' }}">Hosted for</th>
                <th style="width: {{ $showStatus ? '16%' : '18%' }}">Requested by</th>
                <th style="width: {{ $showStatus ? '20%' : '22%' }}">Location</th>
                @if($showStatus)
                    <th style="width: 10%">Status</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($workshops as $workshop)
                <tr>
                    <td class="date">{{ $workshop->starts_at?->format('d M Y') ?? '-' }}</td>
                    <td class="workshop-column">{{ $workshop->title }}</td>
                    <td class="center">{{ $workshop->hostedFor?->name ?? '-' }}</td>
                    <td class="center">{{ $workshop->requestedBy?->getName() ?? '-' }}</td>
                    <td class="center">{{ $workshop->getLocationName() }}</td>
                    @if($showStatus)
                        <td class="center">{{ $workshop->adminStatusLabel() }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $showStatus ? 6 : 5 }}">No workshop records matched the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated {{ $generatedAt->format('d M Y, g:i a') }}</div>
</body>
</html>
