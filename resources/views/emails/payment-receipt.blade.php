@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

@if($isRefund ?? false)
Refund processed for invoice **{{ $invoiceNumber }}**.
@else
Payment received for invoice **{{ $invoiceNumber }}**. {{ $statusSummary }}
@endif

@if(!empty($invoiceSummary))
**Summary:** {{ $invoiceSummary }}<br>
@endif

**{{ ($isRefund ?? false) ? 'Amount Refunded' : 'Amount Paid' }}:** {{ $amount }}<br>
**{{ ($isRefund ?? false) ? 'Refunded On' : 'Paid On' }}:** {{ $paidOn }}<br>
**Payment Method:** {{ $paymentMethod }}<br>

@if($isRefund ?? false)
Please note that funds can take 2-5 business days to land back in your account.
@endif

@if(!empty($creditSummary))
{{ $creditSummary }}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
