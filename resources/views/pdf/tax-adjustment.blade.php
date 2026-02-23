<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Tax Adjustment {{ $adjustment->adjustment_number }}</title>
    <style>
        @include('pdf.partials.styling')
    </style>
</head>

<body>
    @php
    $pages = isset($itemPages) && is_array($itemPages) && count($itemPages) > 0 ? $itemPages : [[]];
    $customer = $invoice->user;
    $logoPath = public_path('invoice-logo.png');
    if (!file_exists($logoPath)) {
    $logoPath = public_path('logo.png');
    }
    if (!file_exists($logoPath)) {
    $logoPath = public_path('apple-touch-icon.png');
    }
    $issueDate = $adjustment->issue_date?->format('M d, Y') ?? '-';
    $dueDate = $adjustment->due_date?->format('M d, Y') ?? '-';
    $allLineItems = collect($pages)->flatten(1)->all();
    $hasNonTaxableItems = collect($allLineItems)->contains(fn ($item) => ((float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0))) <= 0.0001);
        $subtotalEx=(float) $adjustment->subtotal_amount;
        $defaultBusinessInfo = "STEMMechanics\n63 Dalton Street\nWestcourt, QLD, 4870\nABN 15 772 281 735\n\n0400 130 190\nhello@stemmechanics.com.au\nstemmechanics.com.au";
        $businessInfoHtml = \App\Models\SiteOption::valueToHtml('document-business-info', $defaultBusinessInfo);
        $billToCompany = trim((string) ($customer?->company ?? ''));
        $billToPersonName = trim((string) ($customer?->getName() ?? ''));
        if ($billToPersonName === '') {
        $billToPersonName = trim((string) ($adjustment->billing_name ?? ''));
        }
        $billingCountry = trim((string) ($customer?->billing_country ?? ''));
        $showBillingCountry = $billingCountry !== '' && ! in_array(strtolower($billingCountry), ['australia', 'au'], true);
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
                        <div>hello.<br>tax adjustment note</div>
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
                        @if($invoice->invoice_number !== '')
                        <div class="po"><strong>Original Invoice:</strong> {{ $invoice->invoice_number }}</div>
                        @endif

                    </td>
                    <td class="summary-wrap">
                        <table class="summary">
                            <tr>
                                <th>ADJUSTMENT NO</th>
                                <th>DATE</th>
                                <th class="pay">TOTAL</th>
                            </tr>
                            <tr>
                                <td class="invoice-number">{{ $adjustment->adjustment_number }}</td>
                                <td>{{ $issueDate }}</td>
                                <td class="pay">$ {{ number_format((float) $adjustment->total_amount, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            @else
            <div style="text-align:right; font-size:10px; margin-bottom:8px; color:#666;">
                Invoice {{ $invoice->invoice_number }} (continued)
            </div>
            @endif

            <table class="items {{ $loop->last ? 'items-last' : '' }}">
                <thead>
                    <tr>
                        <th style="width:58%;">DESCRIPTION</th>
                        <th class="right" style="width:14%;">HRS / QTY</th>
                        <th class="right" style="width:14%;">RATE / PRICE<br><span class="excl">(Excl GST)</span></th>
                        <th class="right" style="width:14%;">SUBTOTAL<br><span class="excl">(Excl GST)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustment->lines as $line)
                    @php
                    $qty = (float) ($line->quantity ?? 0);
                    $unitEx = (float) ($line->unit_price_ex_tax ?? $line->unit_price ?? 0);
                    $lineEx = (float) ($line->line_total_ex_tax ?? $line->line_total ?? 0);
                    $taxRate = (float) ($line->tax_rate ?? (($line->gst_applicable ?? true) ? 0.1 : 0));
                    $gstApplicable = $taxRate > 0.0001;
                    $lineNotes = trim((string) ($line->notes ?? ''));
                    @endphp
                    <tr>
                        <td>
                            <div class="line-desc">{{ $line->description ?? '' }}{{ $gstApplicable ? '' : '*' }}</div>
                            @if($lineNotes !== '')
                            <div class="line-note">{!! nl2br(e($lineNotes)) !!}</div>
                            @endif
                        </td>
                        <td class="right">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
                        <td class="right">-$ {{ number_format($unitEx, 2) }}</td>
                        <td class="right">-$ {{ number_format($lineEx, 2) }}</td>
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
                        <td class="value">$ {{ number_format((float) $adjustment->gst_amount, 2) }}</td>
                    </tr>
                    <tr class="subtotal-row">
                        <td class="label">TOTAL</td>
                        <td class="value">$ {{ number_format((float) $adjustment->total_amount, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td class="label">TOTAL DUE</td>
                        <td class="value">$ {{ number_format((float) $adjustment->total_amount, 2) }}</td>
                    </tr>
                </table>

                @include('pdf.partials.footer')
            </div>
            @endif
        </div>
        @endforeach
</body>

</html>
