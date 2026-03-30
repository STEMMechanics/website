@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

@if($isRefund ?? false)
Refund processed for invoice **{{ $invoiceNumber }}**.
Your refund receipt is attached.
@else
Payment received for invoice **{{ $invoiceNumber }}**. {{ $statusSummary }}
@if(trim((string) $paymentMethod) === 'Account Credit')
Your credit receipt is attached.
@elseif((bool) ($hasInvoiceAttachment ?? false))
Your invoice and payment receipt are attached.
@else
Your payment receipt is attached.
@endif
@endif

@if(!empty($invoiceSummary))
@php
    $summaryLines = collect(preg_split('/\r\n|\r|\n/', (string) $invoiceSummary))
        ->map(fn (string $line): string => trim($line))
        ->filter(fn (string $line): bool => $line !== '')
        ->values();
@endphp
@if($summaryLines->count() > 1)
**Summary:**

@foreach($summaryLines as $line)
- {{ $line }}
@endforeach
@else
**Summary:** {{ $summaryLines->first() }}<br>
@endif
@endif

**{{ ($isRefund ?? false) ? 'Amount Refunded' : 'Amount Paid' }}:** {{ $amount }}<br>
**{{ ($isRefund ?? false) ? 'Refunded On' : 'Paid On' }}:** {{ $paidOn }}<br>
**Payment Method:** {{ $paymentMethod }}<br>
@if((float) ($creditAppliedAmount ?? 0) > 0.0001)
**Account Credit Applied:** ${{ number_format((float) $creditAppliedAmount, 2) }}<br>
@if(!empty($creditReferenceSummary))
**Credit Reference:** {{ $creditReferenceSummary }}<br>
@endif
@if((float) ($orderTotalAmount ?? 0) > 0.0001 && abs((float) $orderTotalAmount - (float) $amount) > 0.0001)
**Invoice Total:** ${{ number_format((float) $orderTotalAmount, 2) }}<br>
@endif
@endif

@if($isRefund ?? false)
Please note that funds can take 2-5 business days to land back in your account.
@endif

@if(!empty($creditSummary))
{{ $creditSummary }}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
