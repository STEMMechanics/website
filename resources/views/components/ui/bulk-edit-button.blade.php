@props(['count' => null])
<x-ui.button {{ $attributes }}>@if($count !== null)Edit {{ $count }} {{ (int) $count === 1 ? 'item' : 'items' }}@else{{ $slot->isNotEmpty() ? $slot : 'Bulk edit' }}@endif</x-ui.button>
