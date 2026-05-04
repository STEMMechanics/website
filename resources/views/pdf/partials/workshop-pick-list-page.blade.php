@php
    $documentTitle = trim((string) ($documentTitle ?? 'Workshop Pick List'));
    $documentSubtitle = trim((string) ($documentSubtitle ?? ''));
    $logoPath = $logoPath ?? public_path('invoice-logo.png');
    if (! file_exists($logoPath)) {
        $logoPath = public_path('apple-touch-icon.png');
    }

    $renderMarkdown = $renderMarkdown ?? static function (?string $value): string {
        $normalized = \App\Support\EmailMessageFormatter::normalizeForMarkdown((string) ($value ?? ''));
        if ($normalized === '') {
            return '';
        }

        return (string) \Illuminate\Mail\Markdown::parse($normalized);
    };

    $itemsCollection = ($calculatedItems ?? collect());
@endphp

<table class="header">
    <tr>
        <td class="logo-wrap">
            @if(file_exists($logoPath))
                <img class="logo" src="{{ $logoPath }}" alt="Logo" />
            @endif
        </td>
        <td class="headline" style="vertical-align: middle">
            <div>{{ $documentTitle }}</div>
            @if($documentSubtitle !== '')
                <div class="document-subtitle">{{ $documentSubtitle }}</div>
            @endif
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

@if(trim((string) ($pickListNotes ?? '')) !== '')
    @php
        $notesHtml = $renderMarkdown((string) $pickListNotes);
    @endphp
    <div class="notes-wrap">
        <div class="section-title">Pick List Notes</div>
        <div class="notes-body">{!! $notesHtml !== '' ? $notesHtml : e((string) $pickListNotes) !!}</div>
    </div>
@endif
