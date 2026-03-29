@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
$ticketAttachmentCount = (int) ($ticketAttachmentCount ?? 0);
$receiptAttachmentCount = (int) ($receiptAttachmentCount ?? 0);
$creditReceiptAttachmentCount = (int) ($creditReceiptAttachmentCount ?? 0);
$attachmentLabels = [];
if ($hasInvoiceAttachment ?? false) {
    $attachmentLabels[] = 'invoice';
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
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

@if((string) ($workshop['registration'] ?? '') === 'classroom')
Your classroom access is confirmed.
@else
Your ticket order is confirmed.
@endif

**Workshop:** {{ (string) ($workshop['title'] ?? '-') }}<br>
**Time:** {{ (string) ($workshop['time'] ?? $workshop['starts_at'] ?? '-') }}<br>
**Location:** {{ (string) ($workshop['location'] ?? '-') }}<br>
**Payment Method:** {{ $paymentMethodLabel }}<br>
@if((string) ($workshop['registration'] ?? '') === 'classroom')
@if(!empty($workshop['courseUrl'] ?? ''))
**Course:** {{ (string) $workshop['courseUrl'] }}<br>
@endif
@if(!empty($workshop['classroomUrl'] ?? ''))
**Classroom:** {{ (string) $workshop['classroomUrl'] }}<br>
@endif
@if(!empty($workshop['forumUrl'] ?? ''))
**Forum:** {{ (string) $workshop['forumUrl'] }}<br>
@endif
@endif
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
@if((string) ($workshop['registration'] ?? '') === 'classroom')
**Access holders:** {{ (int) ($ticketCount ?? count($tickets)) }}
@else
**Number of Tickets:** {{ (int) ($ticketCount ?? count($tickets)) }}
@endif

@if($invoice)
**Invoice #:** {{ (string) ($invoice['number'] ?? '-') }}<br>
**Invoice Status:** {{ ucfirst((string) ($invoice['status'] ?? '-')) }}
@endif

@if(count($tickets) > 0 && (string) ($workshop['registration'] ?? '') !== 'classroom')
### Ticket Details
@foreach($tickets as $ticket)
- `{{ (string) ($ticket['reference'] ?? '-') }}` | {{ (string) ($ticket['name'] ?? '-') }} | {{ (string) ($ticket['email'] ?? '-') }}
@endforeach
@endif

@if(count($attachmentLabels) === 1)
Your {{ $attachmentLabels[0] }} is attached.
@elseif(count($attachmentLabels) === 2)
Your {{ $attachmentLabels[0] }} and {{ $attachmentLabels[1] }} are attached.
@elseif(count($attachmentLabels) > 2)
Your {{ implode(', ', array_slice($attachmentLabels, 0, -1)) }}, and {{ $attachmentLabels[count($attachmentLabels) - 1] }} are attached.
@else
@if((string) ($workshop['registration'] ?? '') === 'classroom')
Your classroom access details are included in this email and will also be available from your account dashboard once you sign in.
@else
Tickets will be emailed after ticket holder details are confirmed.
@endif
@endif

@if((string) ($workshop['registration'] ?? '') === 'classroom')
If you have a STEMMechanics account, open your classrooms from the [Classrooms]({{ url('/account/classrooms') }}) page when logged in. If you do not have an account yet, use the registration email we sent to finish creating one.
@else
If you have a STEMMechanics account, you can manage your tickets and invoices from your account dashboard when logged in. You can also manage your tickets using the [My Tickets]({{ url('/tickets') }}) link.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
