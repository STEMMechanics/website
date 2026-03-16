@component('mail::message')
@php
$shippingBreakdown = $order->shippingBreakdown();
$shipments = collect($shippingBreakdown['shipments'] ?? [])->filter(fn ($shipment) => is_array($shipment))->values();
$digitalItems = $order->items->filter(fn ($item) => $item->isDigital())->values();
$heading = $notificationType === 'paid'
    ? 'Payment received for store order'
    : 'New store order received';
@endphp
# {{ $heading }}

**Order:** {{ $order->order_number }}  
**Customer:** {{ $order->billing_name ?: 'Guest customer' }}  
**Email:** {{ $order->billing_email ?: '-' }}  
**Phone:** {{ $order->billing_phone ?: '-' }}  
**Total:** ${{ number_format((float) $order->total_amount, 2) }}  
**Status:** {{ $order->statusLabel() }}  
@if($order->contains_physical)
**Delivery:** {{ $order->shipping_method ?: 'Shipping' }}  
@endif
@if($order->contains_preorder)
**Contains pre-order items:** Yes  
@endif

@if($shipments->isEmpty() || $digitalItems->isNotEmpty())
### Items
<ul>
@foreach($shipments->isEmpty() ? $order->items : $digitalItems as $item)
<li>
    <strong>{{ $item->displayTitle() }}</strong> x {{ $item->quantity }}
    @if($item->is_preorder)
        <br><span style="color:#92400e;">Pre-order · Estimated shipping {{ $item->preorderShippingEstimateLabel('F jS Y') ?: 'to be confirmed' }}</span>
    @elseif($item->isBackorder())
        <br><span style="color:#1d4ed8;">
            @if((int) $item->available_now_quantity > 0)
                {{ (int) $item->available_now_quantity }} shipping now, {{ (int) $item->delayed_quantity }} shipping later{{ $item->delayedShippingEstimateLabel('F jS Y') ? ' from '.$item->delayedShippingEstimateLabel('F jS Y') : '' }}
            @else
                Shipping estimated {{ $item->delayedShippingEstimateLabel('F jS Y') ?: 'to be confirmed' }}
            @endif
        </span>
    @endif
</li>
@endforeach
</ul>
@endif

@include('emails.partials.store-order-shipment-plan', [
    'shipments' => $shipments,
    'isPickup' => $order->usesPickup(),
])

@component('mail::button', ['url' => $adminUrl])
Open Order in Admin
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
