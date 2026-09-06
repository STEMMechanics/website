<div {{ $attributes->except('class') }} class="{{ twMerge('grid min-w-0 grid-cols-1 gap-6', $attributes->get('class')) }}">
    {{ $slot }}
</div>
