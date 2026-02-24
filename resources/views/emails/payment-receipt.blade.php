@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

{{ ($isRefund ?? false) ? 'Refund processed for invoice' : 'Payment received for invoice' }} **{{ $invoiceNumber }}**.

**Receipt #:** {{ $receiptNumber }}<br>
**{{ ($isRefund ?? false) ? 'Amount Refunded' : 'Amount Paid' }}:** {{ $amount }}<br>
**{{ ($isRefund ?? false) ? 'Refunded On' : 'Paid On' }}:** {{ $paidOn }}<br>

@if($isRefund ?? false)
Please note that funds can take 2-5 business days to land back in your account.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
