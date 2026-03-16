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
    @php
        $logoPath = public_path('invoice-logo.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('logo.png');
        }
        if (! file_exists($logoPath)) {
            $logoPath = public_path('apple-touch-icon.png');
        }

        $businessInfoHtml = \App\Models\SiteOption::valueToHtml('document.business-info');
        $renderMarkdown = static function (?string $value): string {
            $normalized = \App\Support\EmailMessageFormatter::normalizeForMarkdown((string) ($value ?? ''));
            if ($normalized === '') {
                return '';
            }

            return (string) \Illuminate\Mail\Markdown::parse($normalized);
        };
    @endphp

    <table class="header">
        <tr>
            <td class="logo-wrap">
                @if(file_exists($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                @endif
            </td>
            <td class="headline" style="vertical-align: middle">
                <div>Workshop Pick List</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Details</div>
    <table class="pick-details">
        <tr>
            <td><div class="label">Workshop</div><div class="value">{{ (string) ($workshop->title ?? '-') }}</div></td>
            <td><div class="label">Date / Time</div><div class="value">{{ $workshop->starts_at?->format('D j M - g:ia') ?? '-' }}</div></td>
            <td><div class="label" style="width: 96px;">Participants</div><div class="value">{{ (int) $participants }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Location</div><div class="value">{{ (string) $workshop->getLocationName() }}</div></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="section-title">Items / Materials</div>

    @php
        $itemsCollection = ($calculatedItems ?? collect());
        $columnCount = 3;
        $itemsPerColumn = (int) ceil(max(1, $itemsCollection->count()) / $columnCount);
        $chunked = $itemsCollection->chunk($itemsPerColumn);
    @endphp

    <table class="items-grid">
        <tr>
            @foreach($chunked as $column)
                <td>
                    @foreach($column as $row)
                        <div class="line">
                            <div><span class="box"></span>{{ $row['quantity_text'] }} x {{ \App\Support\ItemLabelFormatter::forQuantity((string) ($row['item_name'] ?? ''), (int) ($row['quantity'] ?? 0)) }}</div>
                            @php
                                $typeNoteHtml = $renderMarkdown((string) ($row['type_note'] ?? ''));
                            @endphp
                            @if($typeNoteHtml !== '')
                                <div class="type-note">{!! $typeNoteHtml !!}</div>
                            @endif
                        </div>
                    @endforeach
                </td>
            @endforeach
            @for($idx = ($chunked->count() ?? 0); $idx < 3; $idx++)
                <td></td>
            @endfor
        </tr>
    </table>

    @if(trim((string) ($workshop->pick_list_notes ?? '')) !== '')
        @php
            $notesHtml = $renderMarkdown((string) $workshop->pick_list_notes);
        @endphp
        <div class="notes-wrap">
            <div class="section-title">Template Notes</div>
            <div class="notes-body">{!! $notesHtml !== '' ? $notesHtml : e((string) $workshop->pick_list_notes) !!}</div>
        </div>
    @endif
</body>
</html>
