<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @include('pdf.partials.styling')
    </style>
</head>

<body>
    @php
    $pages = isset($itemPages) && is_array($itemPages) && count($itemPages) > 0 ? $itemPages : [[]];
    $customer = $invoice->user;
    $billingAddress = $customer?->resolvedBillingAddress() ?? ['address' => '', 'address2' => '', 'city' => '', 'state' => '', 'postcode' => '', 'country' => ''];
    $inlineLogoSvg = '';
    $logoPath = public_path('invoice-logo.png');
    if (!file_exists($logoPath)) {
    $logoPath = public_path('logo.svg');
    }
    if (!file_exists($logoPath)) {
    $logoPath = public_path('apple-touch-icon.png');
    }
    $issueDate = $invoice->issue_date?->format('M d, Y') ?? '-';
    $dueDate = $invoice->due_date?->format('M d, Y') ?? '-';
    $purchaseOrder = trim((string) ($invoice->purchase_order_number ?? ''));
    $allLineItems = collect($pages)->flatten(1)->all();
    $hasNonTaxableItems = collect($allLineItems)->contains(fn ($item) => ((float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0))) <= 0.0001);
        $subtotalEx=(float) $invoice->subtotal_amount;
        $businessInfoHtml = \App\Models\SiteOption::valueToHtml('document.business-info');
        $billToCompany = trim((string) ($customer?->primaryOrganisation?->name ?? ''));
        $billToPersonName = trim((string) ($customer?->getName() ?? ''));
        if ($billToPersonName === '') {
        $billToPersonName = trim((string) ($invoice->billing_name ?? ''));
        }
    $billingCountry = $billingAddress['country'];
    $showBillingCountry = $billingCountry !== '' && ! in_array(strtolower($billingCountry), ['australia', 'au'], true);
    $documentTitle = 'tax invoice';
    $documentType = 'invoice';
    $isCancelled = (string) $invoice->status === \App\Models\Invoice::STATUS_CANCELLED;
    $amountDue = (float) $invoice->displayOutstandingAmount();
    $amountPaid = (float) $invoice->settledAmount();
    $taxAdjustmentTotal = (float) $invoice->issuedAdjustmentTotalAmount();
    $paymentsAndCredits = $amountPaid + max(0, -$taxAdjustmentTotal);
    $paymentAllocations = $invoice->allocations()
        ->with('customerPayment')
        ->where('allocated_amount', '>', 0)
        ->orderBy('created_at')
        ->orderBy('id')
        ->get()
        ->filter(fn ($allocation) => $allocation->customerPayment instanceof \App\Models\Payment)
        ->values();
    $taxAdjustments = $invoice->taxAdjustments()
        ->orderBy('issue_date')
        ->orderBy('id')
        ->get();
    $publicPayUrl = isset($publicPayUrl) && is_string($publicPayUrl) ? $publicPayUrl : null;
    $displayPublicPayUrl = $publicPayUrl !== null ? preg_replace('#^https?://#', '', $publicPayUrl) : '';
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
            @if($isCancelled)
            <div class="watermark">CANCELLED</div>
            @endif
            @if($pageIndex === 0)
            <table class="header">
                <tr>
                    <td class="logo-wrap">
                        @if($inlineLogoSvg !== '')
                        {!! $inlineLogoSvg !!}
                        @elseif(file_exists($logoPath))
                        <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                        @endif
                    </td>
                    <td class="company">
                        {!! $businessInfoHtml !!}
                    </td>
                    <td class="headline">
                        <div>hello.<br>this is your <span class="underline">{{ $documentTitle }}.</span></div>
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
                        @if($billingAddress['address'] !== '')<div>{{ $billingAddress['address'] }}</div>@endif
                        @if($billingAddress['address2'] !== '')<div>{{ $billingAddress['address2'] }}</div>@endif
                        @if($billingAddress['city'] !== '' || $billingAddress['state'] !== '' || $billingAddress['postcode'] !== '')
                        <div>{{ trim(implode(', ', array_filter([$billingAddress['city'], $billingAddress['state'], $billingAddress['postcode']]))) }}</div>
                        @endif
                        @if($showBillingCountry)<div>{{ $billingCountry }}</div>@endif
                        @if($purchaseOrder !== '')
                        <div class="po"><strong>Purchase Order:</strong> {{ $purchaseOrder }}</div>
                        @endif

                    </td>
                    <td class="summary-wrap">
                        <table class="summary">
                            <tr>
                                <th>INVOICE NO</th>
                                <th>INVOICE DATE</th>
                                <th class="pay">AMOUNT DUE</th>
                                <th>DUE DATE</th>
                            </tr>
                            <tr>
                                <td class="invoice-number">{{ $invoice->invoice_number }}</td>
                                <td>{{ $issueDate }}</td>
                                <td class="pay">$ {{ number_format($amountDue, 2) }}</td>
                                <td>{{ $dueDate }}</td>
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
                        <th class="center" style="width:14%;">HRS / QTY</th>
                        <th class="right" style="width:14%;">RATE / PRICE<br><span class="excl">(Excl GST)</span></th>
                        <th class="right" style="width:14%;">SUBTOTAL<br><span class="excl">(Excl GST)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pageItems as $item)
                    @php
                    $qty = (float) ($item['quantity'] ?? 0);
                    $unitEx = (float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0);
                    $lineEx = (float) ($item['line_total_ex_tax'] ?? $item['line_total'] ?? 0);
                    $taxRate = (float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0));
                    $gstApplicable = $taxRate > 0.0001;
                    $lineNotes = trim((string) ($item['notes'] ?? ''));
                    $lineKind = trim((string) ($item['kind'] ?? ''));
                    $lineDescription = (string) ($item['description'] ?? '');
                    if ($lineKind === 'shipping') {
                    $lineDescription = trim((string) preg_replace('/\s+-\s+.+$/', '', $lineDescription));
                    }
                    @endphp
                    <tr>
                        <td>
                            <div class="line-desc">{{ $lineDescription }}{{ $gstApplicable ? '' : '*' }}</div>
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
                        <td class="value">$ {{ number_format((float) $invoice->gst_amount, 2) }}</td>
                    </tr>
                    <tr class="subtotal-row">
                        <td class="label">TOTAL</td>
                        <td class="value">$ {{ number_format((float) $invoice->total_amount, 2) }}</td>
                    </tr>
                    @if($taxAdjustmentTotal > 0.0001)
                    <tr>
                        <td class="label">Adjustments</td>
                        <td class="value">$ {{ number_format($taxAdjustmentTotal, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="label">Payments / Credits</td>
                        <td class="value">{{ $paymentsAndCredits > 0.0001 ? '- ' : '' }}$ {{ number_format($paymentsAndCredits, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td class="label">TOTAL DUE</td>
                        <td class="value">$ {{ number_format($amountDue, 2) }}</td>
                    </tr>
                </table>

                @include('pdf.partials.footer')
            </div>
            @endif
        </div>
        @endforeach

        @if($paymentAllocations->isNotEmpty() || $taxAdjustments->isNotEmpty())
        <div class="page">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:13%;">DATE</th>
                        <th style="width:12%;">METHOD</th>
                        <th style="width:26%;">CARD</th>
                        <th style="width:37%; padding-left:12px;">TRANSACTION ID</th>
                        <th class="right" style="width:12%;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paymentAllocations as $allocation)
                    @php
                    $payment = $allocation->customerPayment;
                    $cardBrand = trim((string) $payment->square_card_brand);
                    $cardLast4 = trim((string) $payment->square_card_last4);
                    $cardDisplay = trim($cardBrand.($cardLast4 !== '' ? ' ending '.$cardLast4 : ''));
                    $transactionId = trim((string) ($payment->square_payment_id ?: $payment->gateway_reference_id));
                    @endphp
                    <tr>
                        <td>{{ $payment->received_on?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ \App\Models\Payment::paymentMethodLabel((string) $payment->payment_method) }}</td>
                        <td>{{ $cardDisplay !== '' ? $cardDisplay : '-' }}</td>
                        <td style="padding-left:12px; word-break: break-all; overflow-wrap: anywhere;">{{ $transactionId !== '' ? $transactionId : '-' }}</td>
                        <td class="right">$ {{ number_format((float) $allocation->allocated_amount, 2) }}</td>
                    </tr>
                    @endforeach
                    @foreach($taxAdjustments as $adjustment)
                    <tr>
                        <td>{{ $adjustment->issue_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ (float) $adjustment->total_amount < 0 ? 'Credit' : 'Adjustment' }}</td>
                        <td>-</td>
                        <td style="padding-left:12px;">{{ $adjustment->adjustment_number }}</td>
                        <td class="right">$ {{ number_format(abs((float) $adjustment->total_amount), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @php
        $adjustmentDocuments = collect($adjustments ?? [])
        ->filter(fn ($adjustment) => $adjustment instanceof \App\Models\TaxAdjustment);
        @endphp

        @foreach($adjustmentDocuments as $adjustment)
        @php
        $adjustmentIssueDate = $adjustment->issue_date?->format('M d, Y') ?? '-';
        $adjustmentSubtotalEx = (float) $adjustment->subtotal_amount;
        $adjustmentLines = $adjustment->lines ?? collect();
        $adjustmentHasNonTaxableItems = collect($adjustmentLines)
        ->contains(fn ($line) => ((float) ($line->tax_rate ?? 0.1)) <= 0.0001);
        @endphp
        <div class="page">
            <table class="header">
                <tr>
                    <td class="logo-wrap">
                        @if($inlineLogoSvg !== '')
                        {!! $inlineLogoSvg !!}
                        @elseif(file_exists($logoPath))
                        <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                        @endif
                    </td>
                    <td class="company">
                        {!! $businessInfoHtml !!}
                    </td>
                    <td class="headline">
                        <div>hello.<br>tax adjustment note.</div>
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
                        @if($billingAddress['address'] !== '')<div>{{ $billingAddress['address'] }}</div>@endif
                        @if($billingAddress['address2'] !== '')<div>{{ $billingAddress['address2'] }}</div>@endif
                        @if($billingAddress['city'] !== '' || $billingAddress['state'] !== '' || $billingAddress['postcode'] !== '')
                        <div>{{ trim(implode(', ', array_filter([$billingAddress['city'], $billingAddress['state'], $billingAddress['postcode']]))) }}</div>
                        @endif
                        @if($showBillingCountry)<div>{{ $billingCountry }}</div>@endif
                        <div class="po"><strong>Original Invoice:</strong> {{ $invoice->invoice_number }}</div>
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
                                <td>{{ $adjustmentIssueDate }}</td>
                                <td class="pay">$ {{ number_format((float) $adjustment->total_amount, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="items items-last">
                <thead>
                    <tr>
                        <th style="width:58%;">DESCRIPTION</th>
                        <th class="right" style="width:14%;">HRS / QTY</th>
                        <th class="right" style="width:14%;">RATE / PRICE<br><span class="excl">(Excl GST)</span></th>
                        <th class="right" style="width:14%;">SUBTOTAL<br><span class="excl">(Excl GST)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustmentLines as $line)
                    @php
                    $qty = (float) ($line->quantity ?? 0);
                    $unitEx = (float) ($line->unit_price_ex_tax ?? 0);
                    $lineEx = (float) ($line->line_total_ex_tax ?? 0);
                    $taxRate = (float) ($line->tax_rate ?? 0.1);
                    $gstApplicable = $taxRate > 0.0001;
                    $lineNotes = trim((string) ($line->notes ?? ''));
                    @endphp
                    <tr>
                        <td>
                            <div class="line-desc">{{ $line->description ?? '' }}{{ $gstApplicable ? '' : '*' }}</div>
                            @if($lineNotes !== '')
                            {!! $renderLineNotes($lineNotes) !!}
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

            <div class="bottom-block">
                <table class="totals">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">$ {{ number_format($adjustmentSubtotalEx, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">
                            @if($adjustmentHasNonTaxableItems)
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
        </div>
        @endforeach
</body>

</html>
