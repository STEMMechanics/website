@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Attached are your invoice and related documents for invoice **{{ $invoiceNumber }}**. This includes the invoice itself, any linked tax adjustment notes, and any receipts that have been issued for it.

@php
    $owing = max(0, (float) ($outstandingAmount ?? 0));
    $hasOwing = $owing > 0.0001;
@endphp

@if($hasOwing)
The current amount owing is **${{ number_format($owing, 2) }}**.

@if(!empty($payUrl))
You can pay this now using the link below.

@component('mail::button', ['url' => $payUrl])
Pay Invoice
@endcomponent
@endif
@else
There is no amount owing on this invoice.
@endif

Thanks,<br>
{{ $initiatedByName ?: config('app.name') }}
@endcomponent
