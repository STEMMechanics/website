<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop sign-in - {{ $workshop->title }}</title>
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
        .instructions { padding: 7px 10px; background: #edf7fc; border-left: 3px solid #1da1e6; margin-bottom: 10px; }
        .roll th { background: #edf7fc; color: #24485d; font-size: 10px; text-align: left; padding: 7px; border: 1px solid #b9cbd5; }
        .roll td { border: 1px solid #cbd5dc; height: 32px; padding: 4px 7px; line-height: 0.95; vertical-align: middle; overflow-wrap: break-word; }
        .roll tr { page-break-inside: avoid; }
        .roll .center { text-align: center; }
        .tick-box { display: inline-block; width: 11px; height: 11px; border: 1px solid #758895; vertical-align: middle; }
        .consent-choice { display: inline-block; white-space: nowrap; margin: 0 3px; }
        .reference { font-size: 8px; color: #667985; }
        .contact { font-size: 10px; }
        .consent { margin-top: 10px; font-size: 10px; line-height: 1; color: #4c5c67; }
        .consent strong { color: #24485d; }
        .sheet-footer { margin-top: 8px; color: #667985; font-size: 9px; }
        .sheet-footer .right { text-align: right; }
    </style>
</head>
<body>
    @php
        $signInRows = ($currentTickets ?? collect())->values();
        // Fill unused space with writing rows; an empty workshop gets a blank sheet.
        $blankRows = $signInRows->isEmpty() ? 10 : (10 - ($signInRows->count() % 10)) % 10;
        $pages = $signInRows->concat(array_fill(0, $blankRows, null))->chunk(10);
        $pages->push(collect(array_fill(0, 10, null)));
        $startsAt = isset($session['starts_at']) ? \Illuminate\Support\Carbon::parse($session['starts_at']) : $workshop->starts_at;
    @endphp
    @foreach($pages as $page)
        <div class="sheet">
            <table class="brand">
                <tr>
                    <td style="width: 22%"><img src="{{ public_path('invoice-logo.png') }}" alt="STEMMechanics" /></td>
                    <td style="width: 52%">
                        <h1 class="workshop-title">{{ $workshop->title }}</h1>
                        <div class="details"><strong>{{ $startsAt?->format('l j F Y, g:i a') ?? 'Date to be confirmed' }}</strong><br>{{ $workshop->getLocationName() }}</div>
                    </td>
                    <td style="width: 26%" class="document-title">{{ $loop->last ? 'Drop-in sign-in' : 'Workshop sign-in' }}</td>
                </tr>
            </table>
            <div class="instructions">Parent / guardian: your signature confirms your contact details are correct and your child has been dropped off. Write any corrections and choose Yes or No for media consent.</div>
            <table class="roll">
                <thead>
                    <tr>
                        <th style="width: 27%">Attendee name</th>
                        <th style="width: 33%">Contact email / phone</th>
                        <th style="width: 16%" class="center">Media consent<br>(see below)</th>
                        <th style="width: 24%">Parent / guardian<br>signature</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($page as $ticket)
                        <tr>
                            <td>
                                {{ $ticket ? trim(($ticket->firstname ?? '').' '.($ticket->surname ?? '')) : '' }}
                                @if($ticket)<div class="reference">{{ $ticket->reference_code ?: $ticket->id }}</div>@endif
                            </td>
                            <td class="contact">
                                <div>{{ $ticket?->email ?? '' }}</div>
                                <div>{{ $ticket?->phone ?? '' }}</div>
                            </td>
                            <td class="center">
                                <span class="consent-choice"><span class="tick-box"></span> Yes</span>
                                <span class="consent-choice"><span class="tick-box"></span> No</span>
                            </td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="consent"><strong>Media consent (Yes):</strong> @include('workshop.partials.media-consent-text')</div>
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
