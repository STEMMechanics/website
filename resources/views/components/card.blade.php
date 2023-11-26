@if (isset($href))
    <a href="{{ $href }}"
        {{ $attributes->merge(['class' => 'bg-gray-50 border border-gray-300 rounded-lg p-6']) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => 'bg-gray-50 border border-gray-300 rounded-lg p-6']) }}>
        {{ $slot }}
    </div>
@endif
