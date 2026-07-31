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
        .meta { margin-bottom: 9px; color: #000; font-size: 9px; }
        .filters { margin: 0 0 12px; color: #000; font-size: 12px; }
        .matrix-page { page-break-after: always; }
        .matrix-page:last-child { page-break-after: auto; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { padding: 6px 5px; border: 1px solid #d1d5db; background: #f3f4f6; color: #000; text-align: center; vertical-align: middle; font-size: 12px; }
        td { padding: 6px 5px; border: 1px solid #e5e7eb; text-align: center; vertical-align: middle; }
        .workshop { width: 22%; font-weight: bold; text-align: left; }
        th.workshop { background: #f3f4f6; }
        td.workshop { background: #fff; }
        td.workshop { text-align: left; }
        .date { display: block; margin-bottom: 2px; color: #000; }
        .empty { color: #000; }
        .footer { position: fixed; left: 0; bottom: -9mm; color: #000; font-size: 9px; }
    </style>
</head>
<body>
    <div class="footer">Generated {{ $generatedAt->format('d M Y, g:i a') }}</div>

    @php($columnChunks = $columns->chunk(6))

    @forelse($columnChunks as $chunkIndex => $columnChunk)
        <section class="matrix-page">
            <img class="logo" src="{{ public_path('logo.png') }}" alt="STEMMechanics">
            <h1>Workshop History</h1>
            @if($filters !== [])
                <div class="filters">
                    {{ implode(' · ', $filters) }}
                </div>
            @endif
            <table>
                <thead>
                    <tr>
                        <th class="workshop" rowspan="2">Workshop</th>
                        @foreach($columnChunk->groupBy('organisation_id') as $organisationColumns)
                            <th class="organisation" colspan="{{ $organisationColumns->count() }}">{{ $organisationColumns->first()['organisation_name'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($columnChunk as $column)
                            <th>{{ $column['location_name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="workshop">{{ $row['title'] }}</td>
                            @foreach($columnChunk as $column)
                                @php($deliveries = $row['cells'][$column['id']] ?? [])
                                <td>
                                    @forelse($deliveries as $delivery)
                                        <span class="date">{{ $delivery['date'] }}</span>
                                    @empty
                                        <span class="empty">-</span>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <h1>Workshop History</h1>
        <p>No workshop records matched the selected filters.</p>
    @endforelse

</body>
</html>
