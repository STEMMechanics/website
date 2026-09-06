@props(['field', 'label', 'default' => 'created_at', 'center' => false])
@php
    $active = request('sort', $default) === $field;
    $direction = request('direction', 'desc');
    $next = $active && $direction === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $field, 'direction' => $next, 'page' => null]);
@endphp
<th {{ $attributes->class([$center ? 'text-center!' : 'text-left']) }} aria-sort="{{ $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a data-dynamic-link href="{{ $url }}" class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap" aria-label="Sort by {{ $label }}, {{ $next === 'asc' ? 'ascending' : 'descending' }}">
        {{ $label }} <i class="fa-solid {{ $active ? ($direction === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort' }} text-xs {{ $active ? 'text-primary-color' : 'text-slate-400' }}" aria-hidden="true"></i>
    </a>
</th>
