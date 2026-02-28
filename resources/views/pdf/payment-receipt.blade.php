<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $receiptTitle ?? 'Payment Receipt' }} {{ $receiptNumber }}</title>
    <style>
        @include('pdf.partials.styling')
        /* Receipt does not need invoice/quote page footer spacing */
        .page { min-height: auto; }
        .items.items-last { margin-bottom: 0; }
        body { line-height: 1.2; }
    </style>
</head>

<body>
    @php
    $isRefundDocument = (bool) ($isRefund ?? false);
    $logoPath = public_path('invoice-logo.png');
    if (!file_exists($logoPath)) {
    $logoPath = public_path('logo.png');
    }
    if (!file_exists($logoPath)) {
    $logoPath = public_path('apple-touch-icon.png');
    }
    $businessInfoHtml = \App\Models\SiteOption::valueToHtml('document.business-info');

    $provider = strtolower(trim((string) ($gatewayProvider ?? '')));
    $transactionId = trim((string) ($transactionId ?? ''));
    $squareOrderId = trim((string) ($squareOrderId ?? ''));
    $gatewayStatus = trim((string) ($gatewayStatus ?? ''));
    $cardBrand = trim((string) ($cardBrand ?? ''));
    $cardLast4 = trim((string) ($cardLast4 ?? ''));
    $cardDisplay = trim($cardBrand.($cardLast4 !== '' ? ' ending in '.$cardLast4 : ''));
    $invoiceDisplay = trim((string) ($invoiceNumber ?? ''));
    $invoiceLabelDisplay = trim((string) ($invoiceLabel ?? ''));
    if ($invoiceLabelDisplay === '') {
    $invoiceLabelDisplay = 'Invoice Number';
    }
    $paidOnDateLine = trim((string) ($paidOn ?? ''));
    if ($paidOnDateLine !== '') {
    try {
    $paidOnParsed = \Illuminate\Support\Carbon::parse($paidOnDateLine);
    $paidOnDateLine = $paidOnParsed->format('d/m/y H:i');
    } catch (\Throwable) {
    // Keep original single-line fallback if parsing fails.
    }
    }

    @endphp
    <div class="page">
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
                    @if($isRefundDocument)
                    <div>hello.<br>this is your <span class="underline">refund receipt</span>.</div>
                    @else
                    <div>hello.<br>this is your <span class="underline">receipt</span>.</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="meta-wrap">
            <tr>
                <td class="bill-to">
                </td>
                <td class="summary-wrap">
                    <table class="summary">
                        <tr>
                            <th>{{ $isRefundDocument ? 'REFUND NO' : 'RECEIPT NO' }}</th>
                            <th>DATE / TIME</th>
                            <th class="pay">TOTAL</th>
                        </tr>
                        <tr>
                            <td class="invoice-number">{{ $receiptNumber }}</td>
                            <td class="receipt-datetime">{{ $paidOnDateLine }}</td>
                            <td class="pay">$ {{ number_format((float) $amountPaid, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items items-last">
            <tbody>
                <tr>
                    <td>Payment Method</td>
                    <td>{{ $paymentMethod ?? '-' }}</td>
                </tr>
                @if($invoiceDisplay !== '')
                <tr>
                    <td>{{ $invoiceLabelDisplay }}</td>
                    <td>{{ $invoiceDisplay }}</td>
                </tr>
                @endif
                @if($reference !== '')
                <tr>
                    <td>Reference</td>
                    <td>{{ $reference }}</td>
                </tr>
                @endif
                @if($provider !== '')
                <tr>
                    <td>Gateway</td>
                    <td>{{ strtoupper($provider) }}</td>
                </tr>
                @endif
                @if($transactionId !== '')
                <tr>
                    <td>Transaction ID</td>
                    <td style="word-break: break-all; overflow-wrap: anywhere;">{{ $transactionId }}</td>
                </tr>
                @endif
                @if($squareOrderId !== '')
                <tr>
                    <td>Order ID</td>
                    <td style="word-break: break-all; overflow-wrap: anywhere;">{{ $squareOrderId }}</td>
                </tr>
                @endif
                @if($cardDisplay !== '')
                <tr>
                    <td>Card</td>
                    <td>{{ $cardDisplay }}</td>
                </tr>
                @endif
                @if($gatewayStatus !== '')
                <tr>
                    <td>Gateway Status</td>
                    <td>{{ $gatewayStatus }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</body>

</html>
