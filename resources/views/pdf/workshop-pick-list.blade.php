<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Pick List - {{ (string) ($workshop->title ?? 'Workshop') }}</title>
    <style>
        @include('pdf.partials.styling')
        body { line-height: 1.25; }
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
    </style>
</head>
<body>
    @include('pdf.partials.workshop-pick-list-page', [
        'workshop' => $workshop,
        'participants' => $participants,
        'calculatedItems' => $calculatedItems,
        'pickListNotes' => $pickListNotes ?? ($workshop->pick_list_notes ?? ''),
        'documentTitle' => 'Workshop Pick List',
    ])
</body>
</html>
