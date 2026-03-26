@component('mail::message')
@php
    $context = is_array($quote->context_payload ?? null) ? $quote->context_payload : [];
    $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
    $items = collect($quote->line_items ?? [])
        ->filter(fn ($item) => is_array($item) && (string) ($item['kind'] ?? 'product') === 'product')
        ->values();
@endphp
# Store shipping quote requested

**Quote:** {{ $quote->quote_number }}  
**Customer:** {{ trim((string) ($customer['billing_name'] ?? '')) ?: ($quote->user?->getName() ?: 'Guest customer') }}  
**Email:** {{ trim((string) ($customer['billing_email'] ?? '')) ?: ($quote->user?->email ?? '-') }}  
**Phone:** {{ trim((string) ($customer['billing_phone'] ?? '')) ?: '-' }}  

@if($items->isNotEmpty())
### Items
<ul>
@foreach($items as $item)
<li><strong>{{ trim((string) ($item['description'] ?? 'Item')) }}</strong> x {{ (float) ($item['quantity'] ?? 0) }}</li>
@endforeach
</ul>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
    <tr>
        <td align="center">
            @component('mail::button', ['url' => $adminUrl])
            Open Quote in Admin
            @endcomponent
        </td>
    </tr>
</table>

Thanks,<br>
{{ config('app.name') }}
@endcomponent
