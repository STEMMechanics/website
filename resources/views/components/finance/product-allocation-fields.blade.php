@props(['categories', 'rules' => []])
@php
    $fixed = old('fixed', collect($rules['fixed'] ?? [])->map(fn ($value) => number_format($value / 100, 2, '.', ''))->all());
    $percent = old('percent', collect($rules['percent'] ?? [])->map(fn ($value) => number_format($value / 100, 2, '.', ''))->all());
@endphp
<div x-data="{ fixed: @js((object) $fixed), percent: @js((object) $percent), total(values) { return Object.values(values).reduce((sum, value) => sum + (Number(value) || 0), 0); } }">
    <p class="mb-4 text-sm text-slate-600">Recover the per-unit costs first, then split the remaining sale amount. Amounts exclude GST. Any remainder below 100% stays unallocated.</p>
    @error('fixed')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
    @error('percent')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
    <div class="space-y-4">
        @foreach($categories as $category)
            <div class="grid grid-cols-2 items-end gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,1fr),minmax(0,1fr),minmax(0,1fr)]">
                <p class="col-span-2 font-medium sm:col-span-1 sm:pb-3">{{ $category->name }}</p>
                <x-ui.input :name="'fixed['.$category->id.']'" :id="'fixed-'.$category->id" :aria-label="$category->name.' cost per unit ($)'" label="Cost per unit ($)" type="number" min="0" max="1000000" step="0.01" :value="$fixed[$category->id] ?? ''" x-model="fixed[{{ $category->id }}]" class="mb-0" />
                <x-ui.input :name="'percent['.$category->id.']'" :id="'percent-'.$category->id" :aria-label="$category->name.' remainder (%)'" label="Remainder (%)" type="number" min="0" max="100" step="0.01" :value="$percent[$category->id] ?? ''" x-model="percent[{{ $category->id }}]" class="mb-0" />
            </div>
        @endforeach
    </div>
    <p class="mt-5 text-sm font-semibold">Cost per unit: $<span x-text="total(fixed).toFixed(2)"></span> · Remainder allocated: <span x-text="total(percent).toFixed(2)"></span>%</p>
    <p x-show="total(percent) > 100" x-cloak class="mt-2 text-sm text-red-700">Remainder allocations cannot exceed 100%.</p>
</div>
