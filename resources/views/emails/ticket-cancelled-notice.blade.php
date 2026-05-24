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

**Refund and credit guidance**

If you paid online by credit card, the refund will be returned to the same card once processed and usually appears within 24-72 hours, depending on your bank.

If you paid by bank transfer, we will handle that manually. The amount may be held as account credit for future workshops, or we can arrange a bank transfer refund if you reply with your bank details.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
