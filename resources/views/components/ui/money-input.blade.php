@props(['name' => null, 'label' => 'Amount', 'value' => '0.00', 'noLabel' => false, 'info' => null, 'error' => null])
@php
    $amount = old($name ?? '', $value);
    $noLabel = filter_var($noLabel, FILTER_VALIDATE_BOOLEAN);
    $error ??= $name ? $errors->first($name) : '';
    $field = new \App\Support\UiField($attributes, filled($info), $error !== '');
@endphp
<div data-validation-field="{{ $name }}" class="{{ twMerge('mb-4 min-w-0', $attributes->get('class')) }}" {{ $field->rootAttributes() }} {{ $attributes->only(['x-show', 'x-cloak']) }}>
        <x-ui.field-label :label="$label" :class="$noLabel ? 'sr-only' : 'mb-1 text-gray-700'" :label-attributes="$field->labelAttributes()" />
        <span class="relative block">
            <span class="pointer-events-none absolute left-3 top-2 text-slate-500" aria-hidden="true">$</span>
            <x-ui.input-control type="number" :name="$name" :value="is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : $amount"
                min="0" max="100000" step="0.01" inputmode="decimal" class="pl-7! text-right tabular-nums"
                x-on:blur="if ($el.value !== '' && Number.isFinite(Number($el.value))) $el.value = Number($el.value).toFixed(2)"
                :control-attributes="$field->controlAttributes()" />
        </span>
    <x-ui.field-feedback :field="$field" :info="$info" :error="$error" />
</div>
