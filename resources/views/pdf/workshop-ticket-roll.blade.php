<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Ticket Roll - {{ (string) ($workshop->title ?? 'Workshop') }}</title>
    <style>
        @page { margin: 24px; size: A4; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 22px 0 8px; }
        .meta { margin-bottom: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Workshop Ticket Roll</h1>
    <div class="meta">
        <div><strong>Workshop:</strong> {{ (string) ($workshop->title ?? '-') }}</div>
        <div><strong>Starts:</strong> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
        <div><strong>Location:</strong> {{ (string) ($workshop->getLocationName()) }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a') }}</div>
    </div>

    @php
        $sections = [
            'Current Tickets' => $currentTickets ?? collect(),
            'Reissued Tickets' => $reissuedTickets ?? collect(),
            'Cancelled Tickets' => $cancelledTickets ?? collect(),
        ];
    @endphp

    @foreach($sections as $title => $sectionTickets)
        <h2>{{ $title }} ({{ $sectionTickets->count() }})</h2>
        @if($sectionTickets->isEmpty())
            <div class="muted">No tickets in this section.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 16%">Reference</th>
                        <th style="width: 24%">Ticket Holder</th>
                        <th style="width: 28%">Contact</th>
                        <th style="width: 16%">Status</th>
                        <th style="width: 16%">Mark Off</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sectionTickets as $ticket)
                        <tr>
                            <td>{{ (string) ($ticket->reference_code ?: $ticket->id) }}</td>
                            <td>{{ trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-' }}</td>
                            <td>
                                <div>{{ (string) ($ticket->email ?? '-') }}</div>
                                <div class="muted">{{ (string) ($ticket->phone ?? '-') }}</div>
                            </td>
                            <td>{{ ucwords(str_replace('-', ' ', (string) ($ticket->status_label ?? '-'))) }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
