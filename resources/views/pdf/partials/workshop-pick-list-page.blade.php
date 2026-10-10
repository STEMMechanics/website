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
    $templateMode = isset($template) && $template instanceof \App\Models\PickListTemplate;
    $formatPickQuantity = static fn (float $quantity): string => rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    $pluralizePickUnit = static fn (string $unit, float $quantity): string => \Illuminate\Support\Str::plural($unit, abs($quantity - 1) < 0.0005 ? 1 : 2);
    $kitSummariesByItemId = collect($kitSummaries ?? [])->keyBy(fn (array $kit): int => (int) ($kit['item_id'] ?? 0));
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
    @if($templateMode)
    <tr>
        <td><div class="label">Blueprint</div><div class="value">{{ $template->name }}</div></td>
        <td><div class="label">Duration</div><div class="value">{{ $template->duration ?: 'Not specified' }}</div></td>
        <td><div class="label" style="width: 96px;">Participants</div><div class="value">{{ $template->participants ?: 'Not specified' }}</div></td>
    </tr>
    @else
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
    @endif
</table>

@if($templateMode && trim((string) $template->description) !== '')
    <div class="notes-wrap workshop-notes"><div class="section-title">Workshop Notes</div><div class="notes-body">{{ $template->description }}</div></div>
@endif
@if(trim((string) ($pickListNotes ?? '')) !== '')
    @php
        $notesHtml = $renderMarkdown((string) $pickListNotes);
    @endphp
    <div class="notes-wrap workshop-notes">
        <div class="section-title">Workshop Notes</div>
        <div class="notes-body">{!! $notesHtml !== '' ? $notesHtml : e((string) $pickListNotes) !!}</div>
    </div>
@endif

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
                        @php
                            $kitSummary = $kitSummariesByItemId->get((int) ($row['item_id'] ?? 0));
                            $rowItemName = (string) ($kitSummary['item_name'] ?? $row['item_name'] ?? '');
                            $rowItemQuantity = $kitSummary !== null
                                ? (float) ($kitSummary['required'] ?? 0)
                                : (float) ($row['quantity'] ?? 0);
                            $rowItemLabel = $pluralizePickUnit($rowItemName, $rowItemQuantity);
                            $typeNoteHtml = $renderMarkdown((string) ($row['type_note'] ?? ''));
                        @endphp
                        <div>
                            <span class="box"></span>
                            @if($kitSummary !== null)
                                {{ $formatPickQuantity($rowItemQuantity) }} {{ $rowItemLabel }}
                            @else
                                {{ $row['quantity_text'] }} x {{ \App\Support\ItemLabelFormatter::forQuantity($rowItemName, (int) ($row['quantity'] ?? 0)) }}
                            @endif
                        </div>
                        @if($typeNoteHtml !== '')
                            <div class="type-note">{!! $typeNoteHtml !!}</div>
                        @endif
                        @if($kitSummary !== null)
                            <div class="kit-contents">
                                @foreach($kitSummary['contents'] ?? [] as $part)
                                    @php
                                        $partQuantity = (bool) ($part['is_kit'] ?? false)
                                            ? (float) ($part['required'] ?? 0)
                                            : (float) ($part['quantity'] ?? 0);
                                        $partName = (string) ($part['item_name'] ?? '');
                                        $partUnit = trim((string) ($part['unit'] ?? ''));
                                        $partLabel = in_array(strtolower($partUnit), ['', 'each', 'unit', 'units'], true)
                                            ? $formatPickQuantity($partQuantity).' × '.$pluralizePickUnit($partName, $partQuantity)
                                            : $formatPickQuantity($partQuantity).' '.$pluralizePickUnit($partUnit, $partQuantity).' of '.$pluralizePickUnit($partName, $partQuantity);
                                        $partNotes = collect($part['notes'] ?? [$part['note'] ?? null])
                                            ->map(fn ($note): string => trim((string) $note))
                                            ->filter()
                                            ->unique()
                                            ->values();
                                    @endphp
                                    <div class="kit-component">
                                        <div>{{ $partLabel }}</div>
                                        @if($partNotes->isNotEmpty())
                                            <div class="kit-component-note">Per kit: {{ $partNotes->implode(' · ') }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif(!empty($row['kit_contents']))
                            <div class="kit-contents">
                                @foreach($row['kit_contents'] as $part)
                                    <div class="kit-component">
                                        @if($part['is_kit'] ?? false)
                                            {{ $formatPickQuantity((float) $part['quantity']) }} × {{ $pluralizePickUnit((string) $part['stock_item_name'], (float) $part['quantity']) }}
                                        @elseif(in_array(strtolower((string) $part['stock_unit']), ['each', 'unit', 'units'], true))
                                            {{ $formatPickQuantity((float) $part['quantity']) }} × {{ $pluralizePickUnit((string) $part['stock_item_name'], (float) $part['quantity']) }}
                                        @else
                                            {{ $formatPickQuantity((float) $part['quantity']) }} {{ $pluralizePickUnit((string) $part['stock_unit'], (float) $part['quantity']) }} of {{ $pluralizePickUnit((string) $part['stock_item_name'], (float) $part['quantity']) }}
                                        @endif
                                        @if(trim((string) ($part['note'] ?? '')) !== '')
                                            <div class="kit-component-note">Per kit: {{ $part['note'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
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
