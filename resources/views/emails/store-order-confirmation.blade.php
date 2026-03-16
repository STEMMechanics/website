@component('mail::message')
@php
$recipientFirstName = trim((string) strtok((string) ($order->billing_name ?? ''), ' '));
$shippingBreakdown = $order->shippingBreakdown();
$shipments = collect($shippingBreakdown['shipments'] ?? [])->filter(fn ($shipment) => is_array($shipment))->values();
$digitalItems = $order->items->filter(fn ($item) => $item->isDigital())->values();
$receiptAttachmentCount = (int) ($receiptAttachmentCount ?? 0);
@endphp
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : ($order->billing_name ?: 'there') }},

Your order **{{ $order->order_number }}** has been created.

@if((float) $order->total_amount <= 0.0001)
No payment was required for this order.
@elseif($order->isPaid())
Payment has already been received.
@else
You can review and pay for this order using the link below.
@endif

### Order Summary

- **Order total:** ${{ number_format((float) $order->total_amount, 2) }}
- **Status:** {{ $order->statusLabel() }}
@if($order->coupon_code)
- **Voucher:** {{ $order->coupon_code }}
@endif
@if($order->contains_physical)
- **Delivery:** {{ $order->shipping_method ?: 'Shipping' }}
@endif
@if($order->contains_preorder)
- **Pre-order items:** Included in this order and shipped when available.
@endif

@if($shipments->isEmpty() || $digitalItems->isNotEmpty())
### Items
<ul>
@foreach($shipments->isEmpty() ? $order->items : $digitalItems as $item)
<li>
    <strong>{{ $item->displayTitle() }}</strong> x {{ $item->quantity }} - ${{ number_format((float) $item->line_total_amount, 2) }}
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

@if($hasInvoiceAttachment && $receiptAttachmentCount > 0)
Your invoice and payment receipt{{ $receiptAttachmentCount === 1 ? '' : 's' }} are attached.
@elseif($hasInvoiceAttachment)
Your invoice is attached.
@elseif($receiptAttachmentCount > 0)
Your payment receipt{{ $receiptAttachmentCount === 1 ? '' : 's are' }} attached.
@endif

@component('mail::button', ['url' => $orderUrl])
View Order
@endcomponent

@if($order->isPaid() && $order->contains_digital)
Your digital downloads are available from the order page.
@elseif($order->contains_digital)
Your digital downloads unlock automatically after payment.
@endif
@if($order->contains_physical)
@if($order->usesPickup())
We will contact you when your order is available to collect.
@else
We will use the shipping address supplied during checkout.
@endif
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
