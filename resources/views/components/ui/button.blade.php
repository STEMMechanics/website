@props(['type' => 'link', 'class', 'href', 'target', 'color' => 'primary'])

@php
    $colorClasses = [
        'outline' => 'hover:bg-gray-500 focus-visible:outline-primary-color text-gray-800 border border-gray-400 bg-white hover:text-white',
        'primary' => 'hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white',
        'primary-sm' => '!font-normal !text-xs !px-4 !py-1 hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white',
        'primary-outline' => 'hover:bg-primary-color-dark focus-visible:outline-primary-color text-primary-color border border-primary-color bg-white hover:text-white',
        'primary-outline-sm' => '!font-normal !text-xs !px-4 !py-1 hover:bg-primary-color-dark focus-visible:outline-primary-color text-primary-color border border-primary-color bg-white hover:text-white',
        'danger' => 'hover:bg-danger-color-dark focus-visible:outline-danger-color bg-danger-color text-white',
        'danger-outline' => 'hover:bg-danger-color-dark focus-visible:outline-danger-color text-danger-color border border-danger-color bg-white hover:text-white',
        'success' => 'hover:bg-success-color-dark focus-visible:outline-success-color bg-success-color text-white',
        'dark' => 'hover:bg-gray-900 focus-visible:outline-gray-800 bg-gray-800 text-white'
    ][$color];
    $commonClasses = @twMerge(['whitespace-nowrap', 'text-center','justify-center','rounded-md','px-8','py-1.5','text-sm','font-semibold','leading-6','shadow-sm','focus-visible:outline','focus-visible:outline-2','focus-visible:outline-offset-2','transition'], ($class ?? ''));
@endphp

@if($type === 'submit' || $type === 'button')
    <button type="{{ $type }}" class="{{ $colorClasses . ' ' . $commonClasses }}" {{ $attributes }}>{{ $slot }}</button>
@elseif($type === 'link')
    <a href="{{ $href ?? '#' }}" target="{{ $target ?? '_self' }}" class="{{ $colorClasses . ' ' . $commonClasses }}" {{ $attributes }}">{{ $slot }}</a>
@endif
