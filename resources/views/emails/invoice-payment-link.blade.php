@component('mail::message')
@php
    $recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Your invoice **{{ $invoiceNumber }}** is ready for payment.

@if(!empty($customMessage))
{{ $customMessage }}

@endif

@component('mail::button', ['url' => $payUrl])
View and Pay Invoice
@endcomponent

You can also open the invoice payment page directly:
{{ $pdfUrl }}

Thanks,<br>
{{ \App\Support\EmailSignatureFormatter::resolve($initiatedByName ?? null) }}
@endcomponent
