@props(['label', 'url'])
<a data-dynamic-link href="{{ $url }}" class="sm-filter-chip" aria-label="Remove filter: {{ $label }}">
    {{ $label }} <i class="fa-solid fa-xmark" aria-hidden="true"></i>
</a>
