@props(['name', 'label', 'checked' => false, 'small' => false])

@php
    $checkBoxClasses = '';
    $labelClasses = '';
    if($small) {
        $checkBoxClasses = 'h-6 w-6 rounded-md text-xs';
        $labelClasses = 'pl-1 text-xs';
    }
@endphp

<style>
    input[type="checkbox"]:checked {
        &:after {
            content: '';
            display: block;
            height: 1.2rem;
            width: 0.6rem;
            border-width: 0 0.25rem 0.25rem 0;
            border-style: solid;
            border-color: #0370A1;
            transform: rotate(40deg) translateY(-0.2rem) translateX(0.65rem);
        }
    }

    input.text-xs[type="checkbox"]:checked {
        &:after {
            height: 1rem;
            width: 0.55rem;
            border-width: 0 0.225rem 0.225rem 0;
            transform: rotate(40deg) translateY(-0.2rem) translateX(0.4rem);
        }
    }
</style>

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    <div class="flex items-center">
        <input class="{{ twMerge(['bg-white','mt-1','h-8','w-8','rounded-lg','border','border-gray-300','appearance-none','focus:outline-none','focus:ring-0','focus:border-blue-600','peer','focus:ring-indigo-300'], $checkBoxClasses ?? '') }}" type="checkbox" {{ $checked ? 'checked' : '' }} id="{{ $name }}" name="{{ $name }}" {{ $attributes }} />
        <label for="{{ $name }}" class="{{ twMerge(['text-sm','pl-2','pt-1'], $labelClasses ?? '') }}">{{ $label }}</label>
    </div>
</div>
