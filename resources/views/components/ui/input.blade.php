@props(['type' => 'text', 'name', 'label' => '', 'value' => '', 'floating' => false, 'noLabel' => false, 'readonly' => false, 'info', 'error' => null, 'labelNotice' => null])

@php
    if($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $classes = 'disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-nonefocus:outline-none focus:ring-0 focus:border-blue-600 ' . ($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');
    $value = old($name, $value);
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    @if($floating)
        <div class="relative">
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-4'], $classes) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes }}>{{ $value }}</textarea>
            @else
                <input class="{{ twMerge(['pt-4'], $classes) }}" autocomplete="off" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes }} />
            @endif
            <label for="{{ $name }}" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1">{{ $label }}</label>
        </div>
    @elseif($noLabel)
        <div class="relative">
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-2.5'], $classes) }}" name="{{ $name }}" placeholder="{{ $label }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes }}>{{ $value }}</textarea>
            @else
                <input class="{{ twMerge(['pt-2.5'], $classes) }}" autocomplete="off" placeholder="{{ $label }}" value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes }} />
            @endif
        </div>
    @else
        <div>
            <label for="{{ $name }}" class="block text-sm pl-1">{{ $label }}{!! isset($labelNotice) && $labelNotice !== '' ? '<i class="fa-solid fa-triangle-exclamation ml-1 text-gray-500 hover:text-black" data-tooltip="' . $labelNotice . '"></i>' : '' !!}</label>
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-2.5','mt-1','h-96'], $classes) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes->whereDoesntStartWith('x-') }}>{{ $value }}</textarea>
            @else
                <input class="{{ twMerge(['pt-2.5','mt-1'], $classes) }}" autocomplete="off" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} {{ $attributes }} />
            @endif
        </div>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
    @if ($hasError)
        <div class="text-xs text-red-600 ml-2 mt-2">{{ $error }}</div>
    @endif
</div>
