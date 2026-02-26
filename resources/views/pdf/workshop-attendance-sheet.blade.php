<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Attendance Sheet - {{ (string) ($workshop->title ?? 'Workshop') }}</title>
    <style>
        @page { margin: 24px; size: A4; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin-bottom: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Workshop Attendance Sheet</h1>
    <div class="meta">
        <div><strong>Workshop:</strong> {{ (string) ($workshop->title ?? '-') }}</div>
        <div><strong>Starts:</strong> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
        <div><strong>Location:</strong> {{ (string) ($workshop->getLocationName()) }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a') }}</div>
    </div>

    @if(($rows ?? collect())->isEmpty())
        <div class="muted">No attendance records found.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 10%">Source</th>
                    <th style="width: 18%">Child Name</th>
                    <th style="width: 17%">Parent/Guardian</th>
                    <th style="width: 17%">Email</th>
                    <th style="width: 12%">Phone</th>
                    <th style="width: 8%">Media</th>
                    <th style="width: 10%">Ticket Ref</th>
                    <th style="width: 8%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ (string) ($row['source'] ?? '') }}</td>
                        <td>{{ (string) ($row['child_name'] ?? '') }}</td>
                        <td>{{ (string) ($row['guardian_name'] ?? '') }}</td>
                        <td>{{ (string) ($row['email'] ?? '') }}</td>
                        <td>{{ (string) ($row['phone'] ?? '') }}</td>
                        <td>{{ (string) ($row['media_consent'] ?? '') }}</td>
                        <td>{{ (string) ($row['ticket_reference'] ?? '') }}</td>
                        <td>{{ (string) ($row['status'] ?? '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
