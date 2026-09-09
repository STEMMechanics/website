@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
$ticketAttachmentCount = (int) ($ticketAttachmentCount ?? 0);
$receiptAttachmentCount = (int) ($receiptAttachmentCount ?? 0);
$creditReceiptAttachmentCount = (int) ($creditReceiptAttachmentCount ?? 0);
$attachmentLabels = [];
if ($hasInvoiceAttachment ?? false) {
    $attachmentLabels[] = ($invoiceAttachmentCount ?? 1) > 1 ? 'invoices' : 'invoice';
}
if ($receiptAttachmentCount > 0) {
    $attachmentLabels[] = $receiptAttachmentCount === 1 ? 'payment receipt' : 'payment receipts';
}
if ($creditReceiptAttachmentCount > 0) {
    $attachmentLabels[] = $creditReceiptAttachmentCount === 1 ? 'credit receipt' : 'credit receipts';
}
if ($ticketAttachmentCount > 0) {
    $attachmentLabels[] = 'ticket'.($ticketAttachmentCount > 1 ? 's' : '');
}
if (($participantAttachmentCount ?? 0) > 0) {
    $attachmentLabels[] = 'workshop document'.($participantAttachmentCount > 1 ? 's' : '');
}
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Your ticket order is confirmed.

**Workshop:** {{ (string) ($workshop['title'] ?? '-') }}<br>
**Time:** {{ (string) ($workshop['time'] ?? $workshop['starts_at'] ?? '-') }}<br>
@foreach(($workshop['schedule'] ?? []) as $session)
{{ $session }}<br>
@endforeach
**Location:** {{ (string) ($workshop['location'] ?? '-') }}<br>
**Payment Method:** {{ $paymentMethodLabel }}<br>
@if((float) $amount > 0)
@php
$creditAppliedAmount = round((float) ($creditAppliedAmount ?? 0), 2);
$paymentAmount = round((float) ($paymentAmount ?? 0), 2);
@endphp
@if($creditAppliedAmount > 0.0001 && $paymentAmount > 0.0001)
**Order Amount:** ${{ number_format((float) $amount, 2) }}<br>
**Account Credit Applied:** ${{ number_format($creditAppliedAmount, 2) }}<br>
@if(!empty($creditReferenceSummary))
**Credit Reference:** {{ $creditReferenceSummary }}<br>
@endif
**Card Charged:** ${{ number_format($paymentAmount, 2) }}<br>
@elseif($creditAppliedAmount > 0.0001)
**Order Amount:** ${{ number_format((float) $amount, 2) }}<br>
**Account Credit Applied:** ${{ number_format($creditAppliedAmount, 2) }}<br>
@if(!empty($creditReferenceSummary))
**Credit Reference:** {{ $creditReferenceSummary }}<br>
@endif
@else
**Order Amount:** ${{ number_format((float) $amount, 2) }}<br>
@endif
@endif
**Number of Tickets:** {{ (int) ($ticketCount ?? count($tickets)) }}

@if(trim((string) ($workshop['participantInformation'] ?? '')) !== '')
### Additional Information
{!! $workshop['participantInformation'] !!}
@endif

@if($invoice)
**Invoice #:** {{ (string) ($invoice['number'] ?? '-') }}<br>
**Invoice Status:** {{ ucfirst((string) ($invoice['status'] ?? '-')) }}
@endif

@if(count($tickets) > 0)
### Ticket Details
@foreach($tickets as $ticket)
- `{{ (string) ($ticket['reference'] ?? '-') }}` | {{ (string) ($ticket['name'] ?? '-') }} | {{ (string) ($ticket['email'] ?? '-') }}@if(!empty($ticket['earlyBird'] ?? false)) | Early bird @endif
@endforeach
@endif

@if($equipmentOrder ?? null)
### Equipment Order
**Store Order #:** {{ $equipmentOrder['number'] }}<br>
**Equipment Invoice #:** {{ $equipmentOrder['invoice_number'] }}<br>
**Equipment and Delivery Total:** ${{ number_format($equipmentOrder['total'], 2) }}<br>
**Delivery:** {{ $equipmentOrder['delivery'] }}

@foreach($equipmentOrder['items'] as $item)
- {{ $item['title'] }} × {{ $item['quantity'] }} — ${{ number_format($item['total'], 2) }}
@endforeach

@include('emails.partials.store-order-shipment-plan', [
    'shipments' => collect($equipmentOrder['shipments']),
    'isPickup' => $equipmentOrder['pickup'],
])

@if($equipmentOrder['pickup'])
We will contact you when your equipment is available to collect.
@endif

Please quote store order **{{ $equipmentOrder['number'] }}** when contacting us about your equipment.

@component('mail::button', ['url' => $equipmentOrder['url']])
View Equipment Order
@endcomponent
@endif

@if(count($attachmentLabels) === 1)
Your {{ $attachmentLabels[0] }} is attached.
@elseif(count($attachmentLabels) === 2)
Your {{ $attachmentLabels[0] }} and {{ $attachmentLabels[1] }} are attached.
@elseif(count($attachmentLabels) > 2)
Your {{ implode(', ', array_slice($attachmentLabels, 0, -1)) }}, and {{ $attachmentLabels[count($attachmentLabels) - 1] }} are attached.
@else
Tickets will be emailed after ticket holder details are confirmed.
@endif

If you have a STEMMechanics account, you can manage your tickets and invoices from your account dashboard when logged in. You can also manage your tickets using the [My Tickets]({{ url('/tickets') }}) link.

@if(count($recommendedWorkshops ?? []) > 0)
### Other workshops you may like
@foreach($recommendedWorkshops as $recommendedWorkshop)
- [{{ $recommendedWorkshop['title'] }}]({{ $recommendedWorkshop['url'] }}) — {{ $recommendedWorkshop['date'] }}@if($recommendedWorkshop['location'] !== '') at {{ $recommendedWorkshop['location'] }}@endif
@endforeach
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
