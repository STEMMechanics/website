@component('mail::message')
@php
    $recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Your {{ $documentType }} **{{ $documentNumber }}** is attached as a PDF.

@if(!empty($customMessage))
{{ $customMessage }}

@endif

@if(!empty($payUrl))
@component('mail::button', ['url' => $payUrl])
View and Pay Invoice
@endcomponent

You can also open this link directly: {{ $payUrl }}
@endif

Thanks,<br>
{{ !empty($initiatedByName) ? $initiatedByName : config('app.name') }}
@endcomponent
