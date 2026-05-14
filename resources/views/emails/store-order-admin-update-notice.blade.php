@php
    $primaryOrder = isset($orders) && is_array($orders) && count($orders) === 1 ? ($orders[0] ?? null) : null;
    $notificationType = is_array($primaryOrder) ? (string) ($primaryOrder['notification_type'] ?? 'updated') : 'updated';
    $resolvedHeadline = $headline ?? match ($notificationType) {
        'shipped' => 'Store Order Shipped',
        'partially_shipped' => 'Store Order Partially Shipped',
        'partially_collected' => 'Store Order Partially Collected',
        'ready_for_partial_collection' => 'Store Order Ready for Partial Collection',
        'items_cancelled' => 'Store Order Items Cancelled',
        'cancelled' => 'Store Order Cancelled',
        'ready_for_pickup' => 'Store Order Ready for Pickup',
        'collected' => 'Store Order Collected',
        'fulfilled' => 'Store Order Complete',
        'preparing' => 'Store Order Preparing',
        default => 'Store Order Updated',
    };
    $resolvedIntroLine = $introLine ?? match ($notificationType) {
        'shipped' => 'A store order has now shipped.',
        'partially_shipped' => 'Part of a store order has now shipped.',
        'partially_collected' => 'Part of a store order has now been collected.',
        'ready_for_partial_collection' => 'Some items on a store order are now ready for partial collection.',
        'items_cancelled' => 'Some items on a store order were cancelled.',
        'cancelled' => 'A store order has now been cancelled.',
        'ready_for_pickup' => 'A store order is now ready for pickup.',
        'collected' => 'A store order has now been collected.',
        'fulfilled' => 'A store order is now complete.',
        'preparing' => 'A store order is now being prepared.',
        default => 'A store order has been updated.',
    };
@endphp

@component('mail::message')
# {{ $resolvedHeadline }}

{{ $resolvedIntroLine }}

@foreach(($orders ?? []) as $order)
### Order {{ $order['order_number'] }}

- **Customer:** {{ $order['customer_name'] }} ({{ $order['customer_email'] }})
- **Current status:** {{ $order['status_label'] }}
@foreach($order['updates'] as $update)
- @if(!empty($update['time'])) **{{ $update['time'] }}** · @endif{{ $update['summary'] }}
@if(!empty($update['detail']))
  <br><span style="color:#4b5563;">{{ $update['detail'] }}</span>
@endif
@endforeach

@component('mail::button', ['url' => $order['admin_url']])
Open in Admin
@endcomponent

@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
