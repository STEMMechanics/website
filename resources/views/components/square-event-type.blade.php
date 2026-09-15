@props(['type'])
@php($color = str_starts_with((string) $type, 'payment.') ? 'sky' : (str_starts_with((string) $type, 'refund.') ? 'purple' : 'gray'))
<x-ui.badge :color="$color">{{ $type ?: '-' }}</x-ui.badge>
