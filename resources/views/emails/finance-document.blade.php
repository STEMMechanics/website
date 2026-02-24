@component('mail::message')
@php
    $recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
    $hasPayPlaceholder = !empty($resolvedFullMessage) && preg_match('/\{\{\s*pay\s*\}\}/i', (string) $resolvedFullMessage) === 1;
@endphp
@if(!empty($resolvedFullMessage))
@php
    $messageSegments = preg_split('/\{\{\s*pay\s*\}\}/i', (string) $resolvedFullMessage) ?: [''];
    $lastSegmentIndex = count($messageSegments) - 1;
@endphp
@foreach($messageSegments as $segmentIndex => $segmentText)
{{ $segmentText }}
@if($segmentIndex < $lastSegmentIndex && !empty($payUrl))
@component('mail::button', ['url' => $payUrl])
View and Pay Invoice
@endcomponent
@endif
@endforeach
@else
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Your {{ $documentType }} **{{ $documentNumber }}** is attached as a PDF.

@if(!empty($customMessage))
{{ $customMessage }}

@endif
@endif

@if(!empty($payUrl) && !$hasPayPlaceholder)
@component('mail::button', ['url' => $payUrl])
View and Pay Invoice
@endcomponent

You can also open this link directly: {{ $payUrl }}
@endif

Thanks,<br>
{{ !empty($initiatedByName) ? $initiatedByName : config('app.name') }}
@endcomponent
