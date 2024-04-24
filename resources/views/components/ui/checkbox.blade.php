@props(['name', 'label', 'checked' => false])

<style>
    input[type="checkbox"]:checked {
        &:after {
            content: '';
            display: block;
            height: 1.2rem;
            width: 0.6rem;
            border-width: 0 0.25rem 0.25rem 0;
            border-style: solid;
            border-color: #000;
            transform: rotate(40deg) translateY(-0.2rem) translateX(0.65rem);
        }
    }
</style>

<div class="mb-4">
    <div class="flex items-center">
        <input class="{{ twMerge(['bg-white','mt-1','h-8','w-8','rounded-lg','border','border-gray-300','appearance-none','focus:outline-none','focus:ring-0','focus:border-blue-600','peer','focus:ring-indigo-300'], $classes ?? '') }}" type="checkbox" {{ $checked ? 'checked' : '' }} id="{{ $name }}" name="{{ $name }}" {{ $attributes }} />
        <label for="{{ $name }}" class="text-sm pl-2 pt-1">{{ $label }}</label>
    </div>
</div>
