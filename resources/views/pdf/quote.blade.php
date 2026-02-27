<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Quote {{ $quote->quote_number }}</title>
    <style>
        @include('pdf.partials.styling')
        .items.items-last {
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    @php
    $pages = isset($itemPages) && is_array($itemPages) && count($itemPages) > 0 ? $itemPages : [is_array($quote->line_items) ? $quote->line_items : []];
    $customer = $quote->user;
    $logoPath = public_path('invoice-logo.png');
    if (!file_exists($logoPath)) {
    $logoPath = public_path('logo.png');
    }
    if (!file_exists($logoPath)) {
    $logoPath = public_path('apple-touch-icon.png');
    }
    $quoteDate = $quote->quote_date?->format('M d, Y') ?? '-';
    $purchaseOrder = trim((string) ($quote->purchase_order_number ?? ''));
    $quoteTitle = trim((string) ($quote->title ?? ''));
    $quoteDescription = trim((string) ($quote->description ?? ''));
    $allLineItems = is_array($quote->line_items) ? $quote->line_items : [];
    $hasNonTaxableItems = collect($allLineItems)->contains(fn ($item) => ($item['gst_applicable'] ?? true) === false);
    $subtotalEx = (float) $quote->subtotal_amount;
    $defaultBusinessInfo = "STEMMechanics\n63 Dalton Street\nWestcourt, QLD, 4870\nABN 15 772 281 735\n\n0400 130 190\nhello@stemmechanics.com.au\nstemmechanics.com.au";
    $businessInfoHtml = \App\Models\SiteOption::valueToHtml('document-business-info', $defaultBusinessInfo);
    $billToCompany = trim((string) ($customer?->company ?? ''));
    $billToPersonName = trim((string) ($customer?->getName() ?? ''));
    if ($billToPersonName === '') {
    $billToPersonName = trim((string) ($quote->billing_name ?? ''));
    }
    $billingCountry = trim((string) ($customer?->billing_country ?? ''));
    $showBillingCountry = $billingCountry !== '' && ! in_array(strtolower($billingCountry), ['australia', 'au'], true);
    $notes = trim((string) ($quote->notes ?? ''));
    $renderLineNotes = function (string $rawNotes): string {
    $lines = preg_split('/\r\n|\r|\n/', $rawNotes) ?: [];
    $html = [];
    $inList = false;

    foreach ($lines as $line) {
    $trimmed = trim((string) $line);

    if ($trimmed === '') {
    if ($inList) {
    $html[] = '</ul>';
    $inList = false;
    }
    $html[] = '<div class="line-note line-note-empty">&nbsp;</div>';
    continue;
    }

    if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches) === 1) {
    if (! $inList) {
    $html[] = '<ul class="line-note-list">';
    $inList = true;
    }
    $content = e(trim((string) ($matches[1] ?? '')));
    if ($content !== '') {
    $html[] = '<li>'.$content.'</li>';
    }
    continue;
    }

    if ($inList) {
    $html[] = '</ul>';
    $inList = false;
    }
    $html[] = '<div class="line-note">'.e($trimmed).'</div>';
    }

    if ($inList) {
    $html[] = '</ul>';
    }

    return implode('', $html);
    };
    @endphp

    @foreach($pages as $pageIndex => $pageItems)
    <div class="page">
        @if($pageIndex === 0)
        <table class="header">
            <tr>
                <td class="logo-wrap">
                    @if(file_exists($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                    @endif
                </td>
                <td class="company">
                    {!! $businessInfoHtml !!}
                </td>
                <td class="headline">
                    <div>hello.<br>this is your <span class="underline">quote.</span></div>
                </td>
            </tr>
        </table>

        <table class="meta-wrap">
            <tr>
                <td class="bill-to">
                    @if($billToCompany !== '')
                    <div style="font-size:14px; font-weight:700;">{{ $billToCompany }}</div>
                    @if($billToPersonName !== '' && strcasecmp($billToPersonName, $billToCompany) !== 0)
                    <div>{{ $billToPersonName }}</div>
                    @endif
                    @else
                    <div style="font-size:14px; font-weight:700;">{{ $billToPersonName !== '' ? $billToPersonName : '-' }}</div>
                    @endif
                    @if($customer?->billing_address)<div>{{ $customer->billing_address }}</div>@endif
                    @if($customer?->billing_address2)<div>{{ $customer->billing_address2 }}</div>@endif
                    @if($customer?->billing_city || $customer?->billing_state || $customer?->billing_postcode)
                    <div>{{ trim(implode(', ', array_filter([$customer->billing_city ?? null, $customer->billing_state ?? null, $customer->billing_postcode ?? null]))) }}</div>
                    @endif
                    @if($showBillingCountry)<div>{{ $billingCountry }}</div>@endif
                </td>
                <td class="summary-wrap">
                    <table class="summary">
                        <tr>
                            <th>QUOTE NO</th>
                            <th>QUOTE DATE</th>
                            <th class="pay">TOTAL</th>
                        </tr>
                        <tr>
                            <td class="quote-number">{{ $quote->quote_number }}</td>
                            <td>{{ $quoteDate }}</td>
                            <td class="pay">$ {{ number_format((float) $quote->total_amount, 2) }}</td>
                        </tr>
                    </table>
                    <div class="quote-validity">Quotes are valid for 28 days from date</div>
                    @if($purchaseOrder !== '')
                    <div class="quote-validity">PO Number: {{ $purchaseOrder }}</div>
                    @endif
                </td>
            </tr>
        </table>

        @if($quoteTitle !== '' || $quoteDescription !== '')
        <div class="quote-details">
            @if($quoteTitle !== '')
            <div class="quote-title">{{ $quoteTitle }}</div>
            @endif
            @if($quoteDescription !== '')
            <div>{!! nl2br(e($quoteDescription)) !!}</div>
            @endif
        </div>
        @endif

        @else
        <div style="text-align:right; font-size:10px; margin-bottom:8px; color:#666;">
            Quote {{ $quote->quote_number }} (continued)
        </div>
        @endif

        <table class="items {{ $loop->last ? 'items-last' : '' }}">
            <thead>
                <tr>
                    <th style="width:58%;">DESCRIPTION</th>
                    <th class="center" style="width:14%;">HRS / QTY</th>
                    <th class="right" style="width:14%;">RATE / PRICE<br><span class="excl">(Excl GST)</span></th>
                    <th class="right" style="width:14%;">SUBTOTAL<br><span class="excl">(Excl GST)</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pageItems as $item)
                @php
                $qty = (float) ($item['quantity'] ?? 0);
                $unitEx = (float) ($item['unit_price'] ?? 0);
                $lineEx = (float) ($item['line_total'] ?? 0);
                $gstApplicable = ($item['gst_applicable'] ?? true) === true;
                $lineNotes = trim((string) ($item['notes'] ?? ''));
                @endphp
                <tr>
                    <td>
                        <div class="line-desc">{{ $item['description'] ?? '' }}{{ $gstApplicable ? '' : '*' }}</div>
                        @if($lineNotes !== '')
                        {!! $renderLineNotes($lineNotes) !!}
                        @endif
                    </td>
                    <td class="center">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="right">$ {{ number_format($unitEx, 2) }}</td>
                    <td class="right">$ {{ number_format($lineEx, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="muted">No line items.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if(!$loop->last)
        <div class="continued">Continued on next page...</div>
        @else
        @if($notes !== '')
        <div class="notes"><span class="note-title">Note: </span>{!! nl2br(e($notes)) !!}</div>
        @endif
        <div class="bottom-block">
            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value">$ {{ number_format($subtotalEx, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">
                        @if($hasNonTaxableItems)
                        <div class="tax-note">"*" indicates non taxable item(s)</div>
                        @endif
                        GST
                    </td>
                    <td class="value">$ {{ number_format((float) $quote->gst_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label">TOTAL</td>
                    <td class="value">$ {{ number_format((float) $quote->total_amount, 2) }}</td>
                </tr>
            </table>

            @include('pdf.partials.footer')
        </div>
        @endif
    </div>
    @endforeach
</body>

</html>
