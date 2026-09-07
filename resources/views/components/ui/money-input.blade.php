@props(['name' => null, 'label' => 'Amount', 'value' => '0.00', 'noLabel' => false])
@php($amount = old($name ?? '', $value))
<div class="mb-4 min-w-0">
    <label class="block">
        <span @class(['sr-only' => $noLabel, 'mb-1 block text-sm text-gray-700' => !$noLabel])>{{ $label }}</span>
        <span class="relative block">
            <span class="pointer-events-none absolute left-3 top-2 text-slate-500" aria-hidden="true">$</span>
            <x-ui.input-control type="number" :name="$name" :value="is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : $amount"
                min="0" max="100000" step="0.01" inputmode="decimal" class="pl-7! text-right tabular-nums"
                x-on:blur="if ($el.value !== '' && Number.isFinite(Number($el.value))) $el.value = Number($el.value).toFixed(2)"
                {{ $attributes }} />
        </span>
    </label>
    @if($name && $errors->has($name))<p class="mt-1 text-sm text-red-600">{{ $errors->first($name) }}</p>@endif
</div>
