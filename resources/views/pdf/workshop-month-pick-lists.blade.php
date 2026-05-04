<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Pick Lists - {{ $currentMonthLabel }}</title>
    <style>
        @include('pdf.partials.styling')
        body { line-height: 1.25; }
        .document-subtitle { font-size: 13px; font-weight: 400; color: #666; margin-top: 4px; line-height: 1.1; }
        .pick-details { border-collapse: collapse; }
        .label { display: inline-block; width: 80px; color: #777; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .value { display: inline-block; color: #333; font-size: 12px; margin-bottom: 0 }
        .section-title { color: #1da1e6; font-weight: 700; font-size: 14px; margin: 14px 0 8px; text-transform: uppercase; }
        .items-grid { width: 100%; border-collapse: collapse; margin-top: 2px; table-layout: fixed; }
        .items-grid td { width: 33.33%; vertical-align: middle; padding: 0 10px 0 0; }
        .line { height: 40px; font-size: 12px; }
        .box { display: inline-block; width: 12px; height: 12px; border: 1px solid #666; margin-right: 8px; margin-top: -2px; vertical-align: text-top }
        .type-note { margin: -6px 0 0 22px; font-size: 9px; color: #666; line-height: 1.25; }
        .type-note p, .notes-body p { margin: 0 0 3px; }
        .type-note ul, .notes-body ul { margin: 0 0 3px 14px; padding: 0; }
        .type-note li, .notes-body li { margin: 0 0 2px; }
        .notes-wrap { margin-top: 12px; }
        .notes-body { font-size: 11px; color: #333; line-height: 1.35; }
        .empty-page { padding: 72px 0 0; text-align: center; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    @forelse($workshopPages as $page)
        <div class="page">
            @include('pdf.partials.workshop-pick-list-page', [
                'workshop' => $page['workshop'],
                'participants' => $page['participants'],
                'calculatedItems' => $page['calculatedItems'],
                'pickListNotes' => $page['pickListNotes'],
                'documentTitle' => 'Workshop Pick List',
            ])
        </div>
    @empty
        <div class="page">
            <table class="header">
                <tr>
                    <td class="logo-wrap">
                        @php
                            $logoPath = public_path('invoice-logo.png');
                            if (! file_exists($logoPath)) {
                                $logoPath = public_path('apple-touch-icon.png');
                            }
                        @endphp
                        @if(file_exists($logoPath))
                            <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                        @endif
                    </td>
                    <td class="headline" style="vertical-align: middle">
                        <div>Workshop Pick List</div>
                    </td>
                </tr>
            </table>
            <div class="empty-page">No workshops found for this month.</div>
        </div>
    @endforelse
</body>
</html>
