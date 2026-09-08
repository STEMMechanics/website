@props(['rules', 'categories', 'prefix' => 'supplied_categories', 'values' => [], 'venueSupplied' => false, 'kind' => null, 'model' => null])
<div class="mb-4 flex flex-wrap gap-4">
    @foreach(collect($rules)->filter(fn ($rule) => $kind === null || ($kind === 'travel' ? $rule['basis'] === 'travel' : $rule['basis'] !== 'travel'))->filter(fn ($rule) => ($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour')->unique('category_id') as $rule)
        <div>
            <input type="hidden" name="{{ $prefix }}[{{ $rule['category_id'] }}]" value="0">
            <x-ui.checkbox :x-model="$model ? $model.'['. $rule['category_id'] .']' : null" :name="$prefix.'['.$rule['category_id'].']'" value="1" :noWrapper="true"
                :label="($categories->firstWhere('id', $rule['category_id'])?->name ?? 'Cost centre').' supplied'"
                :checked="$values[$rule['category_id']] ?? ((($rule['venue_default'] ?? false) || $rule['basis'] === 'venue_hour') && $venueSupplied)" />
        </div>
    @endforeach
</div>
