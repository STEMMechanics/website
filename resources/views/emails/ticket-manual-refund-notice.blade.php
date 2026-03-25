@component('mail::message')
@php
    $recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

{{ $introLine }}

The cancellation has left a manual refund that needs to be processed:

**Ticket:** {{ $ticketReference }}<br>
**Workshop:** {{ $workshopTitle }}<br>
**Invoice:** #{{ $invoiceNumber }}<br>
**Customer:** {{ $customerName }} ({{ $customerEmail }})<br>
@if($refundAmount !== null)
**Refund amount:** ${{ number_format((float) $refundAmount, 2) }}<br>
@endif

@if(!empty($operationSummaries))
@foreach($operationSummaries as $operationSummary)
**Refund operation #{{ $operationSummary['operation_id'] }}:** {{ ucfirst(str_replace('_', ' ', $operationSummary['status'])) }}<br>
**Requested:** ${{ number_format((float) $operationSummary['requested_amount'], 2) }}<br>
@if(($operationSummary['refunded_amount'] ?? 0) > 0)
**Refunded:** ${{ number_format((float) $operationSummary['refunded_amount'], 2) }}<br>
@endif
@if(trim((string) ($operationSummary['failure_message'] ?? '')) !== '')
**Failure:** {{ $operationSummary['failure_message'] }}<br>
@endif
@if(trim((string) ($operationSummary['payment_edit_url'] ?? '')) !== '')
» [Open payment #{{ $operationSummary['payment_id'] }}]({{ $operationSummary['payment_edit_url'] }})<br>
@endif
@if(trim((string) ($operationSummary['invoice_number'] ?? '')) !== '')
» [Open invoice #{{ $operationSummary['invoice_number'] }}]({{ $invoiceUrl }})<br>
@endif
@if(trim((string) ($operationSummary['ticket_reference'] ?? '')) !== '')
» [Open workshop tickets]({{ $ticketUrl }})<br>
@endif
<br>
@endforeach
@endif

» [Open credits queue]({{ $creditsUrl }})<br>
» [Open invoice #{{ $invoiceNumber }}]({{ $invoiceUrl }})<br>
» [Open workshop tickets]({{ $ticketUrl }})<br>

Thanks,<br>
{{ config('app.name') }}
@endcomponent
