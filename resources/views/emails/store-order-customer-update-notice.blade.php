@php
    $resolvedRecipientName = trim((string) ($recipientName ?? 'there')) ?: 'there';
    $primaryOrder = isset($orders) && is_array($orders) && count($orders) === 1 ? ($orders[0] ?? null) : null;
    $primaryOrderNumber = is_array($primaryOrder) ? trim((string) ($primaryOrder['order_number'] ?? '')) : '';
    $notificationType = is_array($primaryOrder) ? (string) ($primaryOrder['notification_type'] ?? 'updated') : 'updated';
    $resolvedItemSections = is_array($primaryOrder) && is_array($primaryOrder['item_sections'] ?? null)
        ? array_values(array_filter($primaryOrder['item_sections'], fn ($section) => is_array($section) && ! empty($section['items'])))
        : [];
    $resolvedIntroLine = $introLine ?? match ($notificationType) {
        'shipped' => 'Your order, '.$primaryOrderNumber.', has now shipped.',
        'partially_shipped' => 'Part of your order, '.$primaryOrderNumber.', has now shipped.',
        'partially_collected' => 'Part of your order, '.$primaryOrderNumber.', has now been collected.',
        'ready_for_partial_collection' => 'Part of your order, '.$primaryOrderNumber.', is now ready for partial collection.',
        'items_cancelled' => 'Some items on your order, '.$primaryOrderNumber.', were cancelled.',
        'cancelled' => 'Your order, '.$primaryOrderNumber.', has been cancelled.',
        'ready_for_pickup' => 'Your order, '.$primaryOrderNumber.', is now ready for pickup.',
        'collected' => 'Your order, '.$primaryOrderNumber.', has been collected.',
        'fulfilled' => 'Your order, '.$primaryOrderNumber.', is now complete.',
        'preparing' => 'Your order, '.$primaryOrderNumber.', is now being prepared.',
        default => 'Your order has been updated.',
    };
    $resolvedDetailLine = $detailLine ?? null;
    $resolvedShowOrderBreakdown = $showOrderBreakdown ?? true;
@endphp

@component('mail::message')
Hi {{ trim((string) strtok($resolvedRecipientName, ' ')) ?: $resolvedRecipientName }},

{{ $resolvedIntroLine }}

@if($resolvedDetailLine)
{{ $resolvedDetailLine }}

@endif

@if($resolvedItemSections !== [])
@foreach($resolvedItemSections as $section)
@php
    $sectionDetailParts = is_array($section['detail_parts'] ?? null) ? array_values(array_filter($section['detail_parts'], fn ($part) => is_array($part) && trim((string) ($part['text'] ?? '')) !== '')) : [];
@endphp
<div style="margin: 18px 0 8px; font-size: 16px; font-weight: 700; color: #111827;">{{ $section['heading'] }}</div>
@if($sectionDetailParts !== [])
<div style="margin: 0 0 10px; font-size: 13px; line-height: 1.5; color: #6b7280;">
@foreach($sectionDetailParts as $index => $part)
@if($index > 0)<span style="color: #9ca3af;"> | </span>@endif@if(trim((string) ($part['prefix'] ?? '')) !== '')<span>{{ $part['prefix'] }}</span>@endif@if(trim((string) ($part['url'] ?? '')) !== '')<a href="{{ $part['url'] }}" style="color: #2563eb; text-decoration: underline;">{{ $part['text'] }}</a>@else<span>{{ $part['text'] }}</span>@endif
@endforeach
</div>
@elseif(!empty($section['detail']))
<div style="margin: 0 0 10px; font-size: 13px; line-height: 1.5; color: #6b7280;">{{ $section['detail'] }}</div>
@endif
<ul style="margin: 0 0 16px 20px; padding: 0;">
@foreach(($section['items'] ?? []) as $item)
<li>
    <strong>{{ $item['title'] }}</strong> x {{ $item['quantity'] }}
    @if(!empty($item['detail']))
        <br><span style="color:#4b5563;">{{ $item['detail'] }}</span>
    @endif
</li>
@endforeach
</ul>
@endforeach

<p class="tall center">
    @component('mail::button', ['url' => $primaryOrder['order_url']])
    View Order
    @endcomponent
</p>
@elseif(is_array($primaryOrder) && ! $resolvedShowOrderBreakdown)
<p class="tall center">
    @component('mail::button', ['url' => $primaryOrder['order_url']])
    View Order
    @endcomponent
</p>
@else
@foreach(($orders ?? []) as $order)
### Order {{ $order['order_number'] }}

- **Current status:** {{ $order['status_label'] }}
@foreach($order['updates'] as $update)
- @if(!empty($update['time'])) **{{ $update['time'] }}** · @endif{{ $update['summary'] }}
@if(!empty($update['detail']))
  <br><span style="color:#4b5563;">{{ $update['detail'] }}</span>
@endif
@endforeach

<p class="tall center">
    @component('mail::button', ['url' => $order['order_url']])
    View Order
    @endcomponent
</p>

@endforeach
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
