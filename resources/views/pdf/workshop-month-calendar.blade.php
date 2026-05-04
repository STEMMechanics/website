<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Calendar - {{ $currentMonthLabel }}</title>
    <style>
        @include('pdf.partials.styling')

        @page { margin: 12mm; size: A4 landscape; }
        body { font-size: 8px; line-height: 1.2; color: #333; }
        .page { min-height: 0; }

        .header { margin-bottom: 6px; }
        .logo-wrap { width: 34%; }
        .logo { width: 120px; height: auto; margin-top: 1px; }
        .headline { width: 66%; text-align: right; vertical-align: top; }
        .headline .title { color: #1da1e6; font-size: 18px; font-weight: 700; line-height: 1; }
        .headline .month { margin-top: -10px; color: #333; font-size: 24px; font-weight: 700; line-height: 1.1; }
        .headline .subline { margin-top: 2px; color: #6b7280; font-size: 9px; line-height: 1.1; }

        table.calendar {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 20px;
        }
        .calendar th,
        .calendar td {
            border: 1px solid #d1d5db;
            vertical-align: top;
        }
        .calendar th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
            padding: 3px 2px 10px;
            text-align: center;
            text-transform: uppercase;
        }
        .calendar td {
            height: 20mm;
            padding: 3px;
            overflow: hidden;
        }
        .calendar td.outside {
            background: #f9fafb;
            color: #9ca3af;
        }
        .calendar td.today {
            /*background: #eff6ff;*/
        }

        .day-header {
            display: block;
            margin-bottom: 2px;
            min-height: 8px;
        }
        .day-number {
            display: inline-block;
            width: auto;
            height: auto;
            border-radius: 0;
            background: transparent;
            color: #111827;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            text-align: left;
            flex: none;
        }
        .today .day-number {
            /*color: #1da1e6;*/
        }
        .outside .day-number {
            color: #9ca3af;
        }

        .event {
            border: 1px solid #e5e7eb;
            background: #f8f8f8;
            border-radius: 3px;
            padding: 1px 3px 6px;
            margin-bottom: 2px;
            page-break-inside: avoid;
        }
        .event-time {
            color: #111827;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.1;
        }
        .event-title {
            color: #111827;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.05;
            margin-top: 1px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .event-location {
            color: #6b7280;
            font-size: 8px;
            line-height: 1.05;
            margin-top: 1px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .more {
            color: #1da1e6;
            font-size: 7px;
            font-weight: 700;
            line-height: 1.1;
            margin-top: 1px;
        }
        .footer {
            margin-top: 6px;
            color: #6b7280;
            font-size: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('invoice-logo.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('logo-dark.png');
        }
        if (! file_exists($logoPath)) {
            $logoPath = public_path('logo.png');
        }
        if (! file_exists($logoPath)) {
            $logoPath = public_path('logo.svg');
        }
        if (! file_exists($logoPath)) {
            $logoPath = public_path('apple-touch-icon.png');
        }
        $searchLabel = trim((string) $search);
    @endphp

    <table class="header">
        <tr>
            <td class="logo-wrap">
                @if(file_exists($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="Business logo" />
                @endif
            </td>
            <td class="headline">
                <div class="title">Workshop Calendar</div>
                <div class="month">{{ $currentMonthLabel }}</div>
                @if($searchLabel !== '')
                    <div class="subline">Search: {{ $searchLabel }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="calendar">
        <thead>
            <tr>
                <th>Sun</th>
                <th>Mon</th>
                <th>Tue</th>
                <th>Wed</th>
                <th>Thu</th>
                <th>Fri</th>
                <th>Sat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calendarWeeks as $week)
                <tr>
                    @foreach($week as $day)
                        @php
                            $dayWorkshops = collect($day['workshops'] ?? []);
                            $visibleWorkshops = $dayWorkshops->take(1);
                            $remainingWorkshops = max(0, $dayWorkshops->count() - $visibleWorkshops->count());
                        @endphp
                        <td class="{{ $day['in_month'] ? '' : 'outside' }} {{ $day['is_today'] ? 'today' : '' }}">
                            <div class="day-header">
                                <div class="day-number">{{ $day['label'] }}</div>
                            </div>

                            @foreach($visibleWorkshops as $workshop)
                                <div class="event">
                                    <div class="event-time">{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</div>
                                    <div class="event-title">{{ $workshop->title }}</div>
                                    <div class="event-location">{{ $workshop->getLocationName() }}</div>
                                </div>
                            @endforeach

                            @if($remainingWorkshops > 0)
                                <div class="more">+{{ $remainingWorkshops }} more</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated {{ $generatedAt?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a') }}
    </div>
</body>
</html>
