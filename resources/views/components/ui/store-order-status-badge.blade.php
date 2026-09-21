@props(['order'])
@php
    [$tone, $icon] = match ((string) $order->status) {
        \App\Models\StoreOrder::STATUS_PENDING_PAYMENT => ['gray', 'fa-regular fa-clock'],
        \App\Models\StoreOrder::STATUS_QUOTE_REQUESTED => ['purple', 'fa-solid fa-file-invoice'],
        \App\Models\StoreOrder::STATUS_READY_FOR_PICKUP,
        \App\Models\StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION => ['sky', 'fa-solid fa-box'],
        \App\Models\StoreOrder::STATUS_PROCESSING,
        \App\Models\StoreOrder::STATUS_PARTIALLY_COLLECTED,
        \App\Models\StoreOrder::STATUS_PARTIALLY_SHIPPED => ['warning', 'fa-solid fa-box-open'],
        \App\Models\StoreOrder::STATUS_SHIPPED,
        \App\Models\StoreOrder::STATUS_COLLECTED,
        \App\Models\StoreOrder::STATUS_FULFILLED => ['success', 'fa-solid fa-check'],
        \App\Models\StoreOrder::STATUS_CANCELLED => ['gray', 'fa-solid fa-ban'],
        default => ['gray', 'fa-regular fa-circle'],
    };
@endphp
<x-ui.badge :tone="$tone" :icon="$icon">{{ $order->statusLabel() }}</x-ui.badge>
