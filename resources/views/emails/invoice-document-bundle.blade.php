@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

{{ $attachmentSummary }}

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
{{ \App\Support\EmailSignatureFormatter::resolve($initiatedByName ?? null) }}
@endcomponent
