@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($order->billing_name ?? ''), ' '));
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : ($order->billing_name ?: 'there') }},

Your order **{{ $order->order_number }}** has been created.

@if($order->isPaid())
Payment has already been received.
@else
You can review and pay for this order using the link below.
@endif

**Order total:** ${{ number_format((float) $order->total_amount, 2) }}  
**Status:** {{ $order->statusLabel() }}
@if($order->coupon_code)
**Coupon:** {{ $order->coupon_code }}  
@endif
@if($order->contains_physical)
**Shipping:** {{ $order->shipping_method ?: 'Shipping' }}  
@endif

### Items
@foreach($order->items as $item)
- {{ $item->displayTitle() }} x {{ $item->quantity }} - ${{ number_format((float) $item->line_total_amount, 2) }}
@endforeach

@component('mail::button', ['url' => $orderUrl])
View Order
@endcomponent

@if($order->isPaid() && $order->contains_digital)
Your digital downloads are available from the order page.
@elseif($order->contains_digital)
Your digital downloads unlock automatically after payment.
@endif
@if($order->contains_physical)
We will use the shipping address supplied during checkout.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
