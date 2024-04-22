@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{{ $slot }}</a>
