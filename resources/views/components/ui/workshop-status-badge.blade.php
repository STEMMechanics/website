@props(['status'])
@php
    $tone = match (strtolower((string) $status)) {
        'open' => 'success',
        'closed', 'cancelled' => 'danger',
        'full', 'soon' => 'purple',
        'draft' => 'warning',
        'private' => 'orange',
        default => 'gray',
    };
@endphp
<x-ui.badge :color="$tone" variant="solid" {{ $attributes }}>{{ $slot }}</x-ui.badge>
