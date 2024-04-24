@props(['type' => 'text', 'name', 'label', 'value' => '', 'floating' => false, 'readonly' => false, 'info'])

@php
    $classes = 'disabled:bg-gray-100 bg-white block mt-1 px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 border-gray-300 focus:border-indigo-300 focus:ring-indigo-300';
    $value = old($name, $value);
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }} {{ $attributes->only('x-show') }}">
    @if($floating)
        <div class="relative">
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-4'], $classes) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes->except(['x-show','style']) }}>{{ $value }}</textarea>
            @else
                <input class="{{ twMerge(['pt-4'], $classes) }}" autocomplete="off" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes }} />
            @endif
            <label for="{{ $name }}" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1">{{ $label }}</label>
        </div>
    @else
        <div class="relative">
            <label for="{{ $name }}" class="block text-sm pl-1">{{ $label }}</label>
            <select class="{{ twMerge(['pt-2.5'], $classes) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes->except(['x-show','style']) }}>
                {{ $slot }}
            </select>
            <i class="fa-solid fa-caret-down absolute text-gray-700 text-2xl right-3 bottom-1.5"></i>
        </div>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
</div>
