<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Ticket {{ $ticketReferenceCode ?? $ticket->reference_code ?? $ticket->id }}</title>
    <style>
        @page {
            margin: 30px;
            size: A4;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 12px;
        }

        .header {
            margin-bottom: 2px;
            width: 100%;
        }

        .logo {
            max-width: 150px;
            height: auto;
        }

        .reference {
            font-size: 11px;
            color: #000;
            font-weight: 700;
            text-align: right;
            vertical-align: bottom;
        }

        .wrap {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }

        .top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .top td {
            vertical-align: top;
        }

        .image-wrap {
            text-align: right;
            width: 96px;
        }

        .qr-wrap {
            text-align: right;
            width: 180px;
            vertical-align: bottom;
        }

        .qr-box {
            display: inline-block;
        }

        .qr-box svg {
            width: 84px;
            height: 84px;
            display: block;
        }

        .code {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            margin-top: -8px;
        }

        .title {
            font-size: 22px;
            font-weight: 700;
        }

        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 18px;
        }

        .ticket-info {
            width: 100%;
            border-collapse: collapse;
        }

        .order-info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .order-info td {
            vertical-align: top;
        }

        .info-header {
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #777;
        }

        .info-value-order {
            font-size: 10px;
            color: #333;
            padding-right: 20px;
        }

        .info-value-name {
            font-size: 10px;
            font-weight: 700;
            color: #333;
            white-space: nowrap;
            padding-right: 20px;
        }

        .address {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .time {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .ticket-status {
            font-size: 11px;
            font-weight: 700;
        }
    </style>
</head>

<body>
    @php
    $logoPath = public_path('ticket-logo.png');
    if (!file_exists($logoPath)) {
    $logoPath = public_path('logo.png');
    }
    if (!file_exists($logoPath)) {
    $logoPath = public_path('apple-touch-icon.png');
    }
    @endphp
    <table class="header">
        <tr>
            <td class="logo-wrap">
                @if(file_exists($logoPath))
                <img class="logo" src="{{ $logoPath }}" alt="Logo" />
                @endif
            </td>
            <td class="reference">Ticket #{{ $ticketReferenceCode ?? $ticket->reference_code ?? $ticket->id }}</td>
        </tr>
    </table>
    <div class="wrap">
        <table class="top">
            <tr>
                <td>
                    <div class="title">{{ $workshop?->title }}</div>
                    <div class="subtitle">General Ticket</div>
                </td>
                <td class="image-wrap">
                    @if(!empty($ticketHeroImagePath))
                    <img src="{{ $ticketHeroImagePath }}" alt="" style="width: 96px; height: auto;">
                    @endif
                </td>
            </tr>
        </table>

        <table class="ticket-info">
            <tr>
                <td>
                    <div class="address">
                        {{ $workshop?->getLocationName() }}
                        @if(isset($workshop->location?->address) && $workshop->location?->address)
                        - {{ $workshop->location?->address }}
                        @endif
                    </div>
                    <div class="time">{!! \App\Helpers::createTicketTimeDurationStr($workshop->starts_at, $workshop->ends_at) !!}</div>
                    <div class="ticket-status">{{ $ticket?->customer_status_label }}</div>
                    <table class="order-info">
                        <tr>
                            <td>
                                <div class="info-header">Order Information</div>
                                <div class="info-value-order">
                                    @if($ticket->invoice_id)
                                    <span>Invoice #{{ $ticket->invoice?->invoice_number ?? $ticket->invoice_id }} - </span>
                                    @endif
                                    <span>Ordered by {{ $ticket->user?->getName() }} on {{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }} - </span>
                                    @if($ticket->reissued_from_ticket_id)
                                    <span>Originally Issued As #{{ $ticket->reissuedFromTicket?->reference_code ?: $ticket->reissued_from_ticket_id }} - </span>
                                    @endif
                                    <span>Generated on {{ now()->format('M j, Y g:i a') }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-header">Name</div>
                                <div class="info-value-name">{{ trim(($ticket->firstname ?? '').' '.($ticket->surname ?? '')) ?: '-' }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="qr-wrap">
                    @if(!empty($ticketQrDataUri))
                    <div class="qr-box">
                        <img src="{{ $ticketQrDataUri }}" alt="Ticket QR code" style="width: 84px; height: auto;">
                        <div class="code">{{ $ticketReferenceCode ?? $ticket->reference_code ?? $ticket->id }}</div>
                    </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
