@props(['name', 'label' => '', 'value' => '', 'error' => null])

@php
    if($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $classes = 'disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-nonefocus:outline-none focus:ring-0 focus:border-blue-600 ' . ($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');

    $label = ($value === '' ? '' : 'Change ') . $label;
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    <div>
        <label for="{{ $name }}" class="block text-sm pl-1 pr-4">
            {{ $label }}
            @if($value !== '')
                <span class="ml-3 text-xs text-gray-500">(Password has been set)</span>
            @else
                <span class="ml-3 text-xs text-gray-500">(No password has been set)</span>
            @endif
        </label>
        <div class="flex items-center gap-4">
            <input class="{{ twMerge(['pt-2.5','mt-1'], $classes) }}" autocomplete="off" placeholder=" " value="" type="password" id="{{ $name }}_password" name="{{ $name }}" {{ $attributes }} />
            @if($value !== '')
                <x-ui.checkbox label="Clear" id="{{ $name }}_clear" name="{{ $name }}_clear" class="mb-0" small="true" />
            @endif
        </div>
    </div>
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
    @if ($hasError)
        <div class="text-xs text-red-600 ml-2 mt-2">{{ $error }}</div>
    @endif
</div>
