@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Your ticket order is confirmed.

**Workshop:** {{ (string) ($workshop['title'] ?? '-') }}<br>
**Time:** {{ (string) ($workshop['time'] ?? $workshop['starts_at'] ?? '-') }}<br>
**Location:** {{ (string) ($workshop['location'] ?? '-') }}<br>
**Payment Method:** {{ $paymentMethodLabel }}<br>
@if((float) $amount > 0)
**Order Amount:** ${{ number_format((float) $amount, 2) }}<br>
@endif
**Number of Tickets:** {{ (int) ($ticketCount ?? count($tickets)) }}

@if($invoice)
**Invoice #:** {{ (string) ($invoice['number'] ?? '-') }}<br>
**Invoice Status:** {{ ucfirst((string) ($invoice['status'] ?? '-')) }}
@endif

@if(count($tickets) > 0)
### Ticket Details
@foreach($tickets as $ticket)
- `{{ (string) ($ticket['reference'] ?? '-') }}` | {{ (string) ($ticket['name'] ?? '-') }} | {{ (string) ($ticket['email'] ?? '-') }}
@endforeach
@endif

@if($hasInvoiceAttachment && $hasReceiptAttachment)
Your invoice and payment receipts are attached.
@elseif($hasInvoiceAttachment)
Your invoice is attached.
@elseif($hasReceiptAttachment)
Your payment receipt is attached.
@endif
@if(($ticketAttachmentCount ?? 0) > 0)
Your ticket{{ $ticketAttachmentCount > 1 ? 's are' : ' is' }} attached.
@else
Tickets will be emailed after ticket holder details are confirmed.
@endif

If you have a STEMMechanics account, you can manage your tickets and invoices from your account dashboard when logged in. You can also manage your tickets using the [My Tickets]({{ url('/tickets') }}) link.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
