@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

{{ $introLine }}

**Ticket:** {{ $ticketReference }}<br>
**Workshop:** {{ $workshopTitle }}<br>
**Time:** {{ $workshopTime }}<br>
**Location:** {{ $workshopLocation }}<br>

@if(trim((string) ($financialSummary ?? '')) !== '')
{{ $financialSummary }}
@endif

@if(trim((string) ($documentSummary ?? '')) !== '')
{{ $documentSummary }}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
