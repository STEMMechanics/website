@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

The following ticket has been cancelled.

**Ticket:** {{ $ticketReference }}<br>
**Workshop:** {{ $workshopTitle }}<br>
**Time:** {{ $workshopTime }}<br>
**Location:** {{ $workshopLocation }}<br>

@if(trim((string) ($financialSummary ?? '')) !== '')
{{ $financialSummary }}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
