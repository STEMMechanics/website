<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendance report - {{ $workshop->title }}</title>
    <style>
        @include('pdf.partials.styling')
        @page { margin: 28px; size: A4 landscape; }
        body { font-size: 11px; line-height: 1.3; }
        .sheet + .sheet { page-break-before: always; }
        .brand { margin-bottom: 10px; }
        .brand td { vertical-align: middle; }
        .brand img { width: 170px; }
        .document-title { text-align: right; font-size: 20px; font-weight: 700; color: #1889c4; }
        .workshop-title { font-size: 16px; font-weight: 700; margin: 0 0 3px; }
        .details { color: #555; }
        .report-meta { padding: 7px 10px; background: #edf7fc; border-left: 3px solid #1da1e6; margin-bottom: 10px; }
        .attendance th { background: #edf7fc; color: #24485d; font-size: 10px; text-align: left; padding: 7px; border: 1px solid #b9cbd5; }
        .attendance td { border: 1px solid #cbd5dc; padding: 6px 7px; vertical-align: middle; overflow-wrap: break-word; }
        .attendance tr { page-break-inside: avoid; }
        .attendance .center { text-align: center; }
        .contact { font-size: 10px; }
        .contact div { line-height: 0.8; }
        .reference, .recorded { font-size: 8px; color: #667985; }
        .source { font-size: 9px; }
        .status { font-weight: 700; color: #24485d; }
        .sheet-footer { margin-top: 12px; color: #667985; font-size: 9px; }
        .sheet-footer .right { text-align: right; }
    </style>
</head>
<body>
    @php
        $records = collect($rows ?? []);
        $pages = $records->isEmpty() ? collect([collect()]) : $records->chunk(8);
    @endphp
    @foreach($pages as $page)
        <div class="sheet">
            <table class="brand">
                <tr>
                    <td style="width: 22%"><img src="{{ public_path('invoice-logo.png') }}" alt="STEMMechanics" /></td>
                    <td style="width: 52%">
                        <h1 class="workshop-title">{{ $workshop->title }}</h1>
                        <div class="details"><strong>{{ $workshop->starts_at?->format('l j F Y, g:i a') ?? 'Date to be confirmed' }}</strong><br>{{ $workshop->getLocationName() }}</div>
                    </td>
                    <td class="document-title">Attendance report</td>
                </tr>
            </table>
            <div class="report-meta">{{ $records->count() }} {{ \Illuminate\Support\Str::plural('attendance record', $records->count()) }} · Generated {{ $generatedAt->format('j M Y, g:i a') }}</div>
            @if($page->isEmpty())
                <p>No attendance records found.</p>
            @else
                <table class="attendance">
                    <thead>
                        <tr>
                            <th style="width: 11%">Source</th>
                            <th style="width: 22%">Attendee</th>
                            <th style="width: 4%" class="center">Age</th>
                            <th style="width: 37%">Parent / guardian contact</th>
                            <th style="width: 8%" class="center">Media consent</th>
                            <th style="width: 18%">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($page as $row)
                            <tr>
                                <td class="source">{{ ucfirst($row['source'] ?? '') }}@if(!empty($row['ticket_reference']))<div class="reference">{{ $row['ticket_reference'] }}</div>@endif</td>
                                <td>{{ $row['child_name'] ?? '' }}</td>
                                <td class="center">{{ $row['age'] ?? '' }}</td>
                                <td class="contact">
                                    @foreach(['guardian_name', 'email', 'phone'] as $field)
                                        @if(($row[$field] ?? '') !== '')<div>{{ $row[$field] }}</div>@endif
                                    @endforeach
                                </td>
                                <td class="center">{{ $row['media_consent'] ?? '' }}</td>
                                <td><span class="status">{{ $row['status'] ?? '' }}</span>@if(!empty($row['recorded_at']))<div class="recorded">{{ $row['recorded_at'] }}</div>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <table class="sheet-footer">
                <tr>
                    <td>STEMMechanics · stemmechanics.com.au</td>
                    <td class="right">Page {{ $loop->iteration }} of {{ $pages->count() }}</td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
