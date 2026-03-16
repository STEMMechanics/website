@props(['type' => 'link', 'class', 'href', 'target', 'color' => 'primary'])

@php
    $disabledClasses = 'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none disabled:shadow-none';

    $colorMap = [
        'outline' => "hover:bg-gray-500 focus-visible:outline-primary-color text-gray-800 border border-gray-400 bg-white hover:text-white {$disabledClasses}",
        'primary' => "hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white {$disabledClasses}",
        'primary-sm' => "!font-normal !text-xs !px-4 !py-1 hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white {$disabledClasses}",
        'accent' => "hover:bg-orange-600 focus-visible:outline-orange-500 bg-orange-500 text-white {$disabledClasses}",
        'primary-outline' => "hover:bg-primary-color-dark focus-visible:outline-primary-color text-primary-color border border-primary-color bg-white hover:text-white {$disabledClasses}",
        'primary-outline-sm' => "!font-normal !text-xs !px-4 !py-1 hover:bg-primary-color-dark focus-visible:outline-primary-color text-primary-color border border-primary-color bg-white hover:text-white {$disabledClasses}",
        'secondary' => "hover:bg-gray-200 focus-visible:outline-gray-300 border border-gray-300 bg-gray-100 text-gray-800 {$disabledClasses}",
        'danger' => "hover:bg-danger-color-dark focus-visible:outline-danger-color bg-danger-color text-white {$disabledClasses}",
        'danger-outline' => "hover:bg-danger-color-dark focus-visible:outline-danger-color text-danger-color border border-danger-color bg-white hover:text-white {$disabledClasses}",
        'success' => "hover:bg-success-color-dark focus-visible:outline-success-color bg-success-color text-white {$disabledClasses}",
        'dark' => "hover:bg-gray-900 focus-visible:outline-gray-800 bg-gray-800 text-white {$disabledClasses}",
    ];
    $colorClasses = $colorMap[$color] ?? $colorMap['primary'];
    $commonClasses = twMerge(['whitespace-nowrap', 'cursor-pointer', 'text-center','justify-center','rounded-md','px-8','py-1.5','text-sm','font-semibold','leading-6','shadow-sm','focus-visible:outline','focus-visible:outline-2','focus-visible:outline-offset-2','transition'], ($class ?? ''));
    $hrefValue = html_entity_decode((string) ($href ?? '#'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
@endphp

@if($type === 'submit' || $type === 'button')
    <button
        type="{{ $type }}"
        class="{{ $colorClasses . ' ' . $commonClasses }}"
        {{ $attributes }}
    >
        {{ $slot }}
    </button>
@elseif($type === 'link')
    <a
        href="{{ $hrefValue }}"
        target="{{ $target ?? '_self' }}"
        class="{{ $colorClasses . ' ' . $commonClasses }}"
        {{ $attributes }}
    >
        {{ $slot }}
    </a>
@endif
