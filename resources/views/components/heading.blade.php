@php
    $id = \Illuminate\Support\Str::slug($slot);
    $uri = url()->current() . '#' . $id;
@endphp

<a
    id={{ $id }}
        class="{{ twMerge(['inline-block','text-lg','font-semibold my-3'], $class ?? '') }}"
    x-data="{ show: false, uri: '{{ $uri }}' }"
    @mouseover="show = true"
    @mouseout="show = false"
    @click="SM.copyToClipboard('{{ $uri }}')"
>
    {{ $slot }}
    <span x-show="show" class="text-primary-color-light pl-2">#</span>
</a>
