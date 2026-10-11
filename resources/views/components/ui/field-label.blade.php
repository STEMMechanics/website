@props(['label' => '', 'info' => null, 'notice' => null, 'floating' => false, 'labelAttributes' => null])
@php
    $attributes = $attributes->merge($labelAttributes instanceof \Illuminate\View\ComponentAttributeBag ? $labelAttributes->getAttributes() : ($labelAttributes ?? []));
    $classes = $floating
        ? 'absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-left bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto inset-s-1'
        : 'flex items-center text-sm pl-1';
@endphp
<label class="{{ twMerge($classes, $attributes->get('class')) }}" {{ $attributes->except('class') }}>
    {{ $label }}
    @if($info !== null && $info !== '')<span class="ml-1 text-xs text-gray-500">{{ $info }}</span>@endif
    @if($notice !== null && $notice !== '')
        <span class="ml-1 inline-flex text-gray-500" data-tooltip="{{ $notice }}" role="img" aria-label="{{ $notice }}" tabindex="0"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
    @endif
</label>
