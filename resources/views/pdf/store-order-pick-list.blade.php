<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pick List {{ $order->order_number }}</title>
    <style>
        @include('pdf.partials.styling')
        body { line-height: 1.25; }
        .meta-wrap { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .meta-wrap td { vertical-align: top; padding: 0; }
        .meta-box { padding: 12px 14px; }
        .meta-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #77808c; margin-bottom: 6px; }
        .meta-line { font-size: 12px; color: #333; margin-bottom: 2px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table th { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #77808c; text-align: left; padding-bottom: 4px; }
        .summary-table td { font-size: 12px; color: #333; padding: 1px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 14px; table-layout: fixed; }
        .items-table th { border-bottom: 1px solid #d9dde3; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #77808c; padding: 8px 0; text-align: left; }
        .items-table td { border-bottom: 1px solid #eceff3; vertical-align: top; font-size: 12px; color: #333; padding: 10px 0; }
        .item-title { font-weight: 700; color: #111; }
        .item-sku { margin-top: 2px; font-size: 10px; color: #6b7280; }
        .item-detail { margin-top: 2px; font-size: 10px; color: #6b7280; }
        .qty { text-align: center; white-space: nowrap; }
        .right { text-align: right; }
        .header { margin-bottom: 12px; }
        .logo-wrap { width: 36%; vertical-align: middle; }
        .headline { width: 64%; font-size: 18px; font-weight: 700; text-align: right; vertical-align: middle; }
    </style>
</head>
<body>
    @php
        $customerName = trim((string) ($order->billing_name ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($order->user?->getName() ?? ''));
        }
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $customerCompany = trim((string) ($order->billing_company ?? ''));
        $customerEmail = trim((string) ($order->billing_email ?? ''));
        $customerPhone = trim((string) ($order->billing_phone ?? ''));
        $shippingLines = $order->shippingAddressLines();
        $collectionLabel = $order->usesPickup() ? 'Pick up / Collection' : 'Delivery';
        $collectionNote = $order->usesPickup()
            ? ((string) $order->status === \App\Models\StoreOrder::STATUS_READY_FOR_PICKUP
                ? 'Ready for pickup now.'
                : ((string) $order->status === \App\Models\StoreOrder::STATUS_COLLECTED
                    ? 'Collected.'
                    : 'Customer will be contacted for collection.'))
            : (trim((string) ($order->shipping_method ?? '')) !== '' ? (string) $order->shipping_method : 'Delivery');
        $orderDate = $order->created_at?->format('M j, Y g:i a') ?? '-';
        $invoiceNumber = trim((string) ($order->invoice?->invoice_number ?? ''));
        $pickListItems = collect($pickListItems ?? [])->filter(fn ($item) => is_array($item))->values();
    @endphp

    <div class="page">
        <table class="header">
            <tr>
                <td class="logo-wrap">
                    @if(file_exists(public_path('invoice-logo.png')))
                        <img class="logo" src="{{ public_path('invoice-logo.png') }}" alt="Logo" />
                    @elseif(file_exists(public_path('logo.svg')))
                        <img class="logo" src="{{ public_path('logo.svg') }}" alt="Logo" />
                    @endif
                </td>
                <td class="headline">
                    <div>Pick/Packing List</div>
                </td>
            </tr>
        </table>

        <table class="meta-wrap">
            <tr>
                <td style="width: 54%; padding-right: 12px;">
                    <div class="meta-box">
                        <div class="meta-title">Customer details</div>
                        <div class="meta-line"><strong>{{ $customerName }}</strong></div>
                        @if($customerCompany !== '')
                            <div class="meta-line">{{ $customerCompany }}</div>
                        @endif
                        @if($customerEmail !== '')
                            <div class="meta-line">{{ $customerEmail }}</div>
                        @endif
                        @if($customerPhone !== '')
                            <div class="meta-line">{{ $customerPhone }}</div>
                        @endif
                        @if($shippingLines !== [] && !$order->usesPickup())
                            <div class="meta-line" style="margin-top: 6px; font-size: 10px; text-transform: uppercase; color: #77808c; font-weight: 700;">{{ $collectionLabel }}</div>
                            @foreach($shippingLines as $line)
                                <div class="meta-line">{{ $line }}</div>
                            @endforeach
                        @endif
                    </div>
                </td>
                <td style="width: 46%;">
                    <div class="meta-box">
                        <table class="summary-table">
                            <tr>
                                <th>Order</th>
                                <td>{{ $order->order_number }}</td>
                            </tr>
                            <tr>
                                <th>Invoice</th>
                                <td>{{ $invoiceNumber !== '' ? $invoiceNumber : '-' }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>{{ $order->statusLabel() }}</td>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <td>{{ $orderDate }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 54%;">Item</th>
                    <th class="qty" style="text-align: center; width: 12%;">Ordered</th>
                    <th class="qty" style="text-align: center; width: 12%;">To Pick</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pickListItems as $item)
                    <tr>
                        <td>
                            <div class="item-title">{{ (string) ($item['title'] ?? 'Item') }}</div>
                            @if(trim((string) ($item['sku'] ?? '')) !== '')
                                <div class="item-sku" style="line-height:80%;">SKU {{ $item['sku'] }}</div>
                            @endif
                        </td>
                        <td class="qty" style="vertical-align: middle">{{ (int) ($item['ordered_quantity'] ?? 0) }}</td>
                        <td class="qty" style="vertical-align: middle">{{ (int) ($item['open_quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted">No items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
