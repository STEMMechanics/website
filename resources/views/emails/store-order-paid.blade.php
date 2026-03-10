@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($order->billing_name ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : ($order->billing_name ?: 'there') }},

We have received payment for order **{{ $order->order_number }}**.

**Paid total:** ${{ number_format((float) $order->total_amount, 2) }}  
**Status:** {{ $order->statusLabel() }}
@if($order->contains_physical)
**Shipping:** {{ $order->shipping_method ?: 'Shipping' }}  
@endif

@if($order->contains_digital)
Your digital downloads are now available from the order page.
@endif
@if($order->contains_physical)
Your physical items are now in our processing queue.
@endif

@component('mail::button', ['url' => $orderUrl])
Open Order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
