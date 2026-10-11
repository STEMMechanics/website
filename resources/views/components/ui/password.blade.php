@props(['name', 'label' => '', 'value' => '', 'error' => null, 'info' => null])

@php
    if($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $field = new \App\Support\UiField($attributes->merge(['id' => $name.'_password']), filled($info), $hasError);
    $classes = 'disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 ' . ($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');

    $label = ($value === '' ? '' : 'Change ') . $label;
@endphp

<div data-validation-field="{{ $name }}" class="{{ twMerge(['mb-4'], $attributes->get('class')) }}" {{ $field->rootAttributes() }} {{ $attributes->only(['x-show', 'x-cloak']) }}>
    <div>
        <x-ui.field-label :label="$label" :info="$value !== '' ? '(Password has been set)' : '(No password has been set)'" class="pr-4" :label-attributes="$field->labelAttributes()" />
        <div class="flex items-center gap-4">
            <input class="{{ twMerge(['pt-2.5','mt-1'], $classes) }}" autocomplete="{{ $attributes->get('autocomplete', 'new-password') }}" placeholder=" " value="" type="password" name="{{ $name }}" {{ $field->controlAttributes()->except('autocomplete') }} />
            @if($value !== '')
                <x-ui.checkbox label="Clear" id="{{ $name }}_clear" name="{{ $name }}_clear" class="mb-0" small="true" />
            @endif
        </div>
    </div>
    <x-ui.field-feedback :field="$field" :info="$info" :error="$error" />
</div>
