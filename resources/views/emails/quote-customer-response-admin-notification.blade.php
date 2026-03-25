@component('mail::message')
@php
    $responseLabel = match ($responseStatus) {
        \App\Models\Quote::STATUS_ACCEPTED => 'accepted',
        \App\Models\Quote::STATUS_CANCELLED => 'cancelled',
        default => trim((string) $responseStatus),
    };
    $customerName = trim((string) ($quote->user?->getName() ?? ''));
    if ($customerName === '') {
        $context = is_array($quote->context_payload ?? null) ? $quote->context_payload : [];
        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
        $customerName = trim((string) ($customer['billing_name'] ?? ''));
    }
@endphp
# Quote {{ $quote->quote_number }} {{ $responseLabel }}

**Customer:** {{ $customerName !== '' ? $customerName : 'Customer' }}  
**Status:** {{ $quote->statusLabel() }}  
**Total:** ${{ number_format((float) $quote->total_amount, 2) }}

@if($invoiceEmailed)
The invoice was emailed automatically after the customer accepted the quote.
@endif

<div style="margin: 18px 0 20px;">
    @if(!empty($adminQuoteUrl))
        <div style="display:block; margin-bottom: 8px;"><span style="color:#64748b;">&raquo;</span> <a href="{{ $adminQuoteUrl }}" style="color:#0284C7; text-decoration: underline;">View Quote</a></div>
    @endif
    @if(!empty($adminInvoiceUrl))
        <div style="display:block; margin-bottom: 8px;"><span style="color:#64748b;">&raquo;</span> <a href="{{ $adminInvoiceUrl }}" style="color:#0284C7; text-decoration: underline;">View Invoice</a></div>
    @endif
    @if(!empty($adminOrderUrl))
        <div style="display:block;"><span style="color:#64748b;">&raquo;</span> <a href="{{ $adminOrderUrl }}" style="color:#0284C7; text-decoration: underline;">View Order</a></div>
    @endif
</div>

Thanks,<br>
{{ config('app.name') }}
@endcomponent
