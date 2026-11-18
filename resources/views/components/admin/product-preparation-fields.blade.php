@props(['model' => 'item', 'catalogExpression' => 'productCatalog', 'inline' => false])
<div
    x-data="$SM.productPreparationFields({{ $model }}, {{ $catalogExpression }})"
    x-effect="syncPreparationValues({{ $model }}, {{ $catalogExpression }})"
    x-show="hasPreparationFields"
    x-cloak
    {{ $attributes->class(['flex max-w-full flex-wrap items-end gap-2', 'mt-2' => !$inline, 'shrink-0' => $inline]) }}
>
    <label class="block w-44 text-xs text-slate-600" x-show="hasQuantityChoice">
        <span class="{{ $inline ? 'sr-only' : 'block mb-1' }}">Quantity counts or variant</span>
        <x-ui.select-control class="h-11 text-sm" title="Quantity counts or variant" x-bind:value="quantityChoice" x-effect="syncQuantityChoice($el)" x-on:change="$SM.changeProductQuantityChoice(preparationItem, $event.target.value, linkedMaterial)" x-bind:disabled="!hasQuantityChoice">
            <template x-if="hasLinkedMaterialMeasurements"><option value="item">Individual units</option></template>
            <option value="pack" x-text="linkedMaterialLabel"></option>
            <template x-for="variant in selectableVariants" :key="variant.id"><option x-bind:value="'variant:' + variant.id" x-text="variant.name"></option></template>
        </x-ui.select-control>
    </label>
    <div class="w-40" x-show="quantityUnit === 'item' && linkedMaterialKind === 'sheet'" x-cloak>
        <label class="block text-xs text-slate-600"><span class="{{ $inline ? 'sr-only' : 'block mb-1' }}">Cut size</span>
            <x-ui.select-control class="h-11 text-sm" title="Cut size" x-model="preparationItem.cut_size_id" x-bind:disabled="quantityUnit !== 'item' || linkedMaterialKind !== 'sheet'">
                <option value="">Whole sheet</option>
                <template x-for="cut in linkedMaterialCutSizes" :key="cut.id"><option x-bind:value="cut.id" x-text="cut.label"></option></template>
            </x-ui.select-control>
        </label>
    </div>
    <div class="flex items-end gap-2" x-show="quantityUnit === 'item' && linkedMaterialKind === 'length'" x-cloak>
        <label class="w-32 text-xs text-slate-600"><span class="{{ $inline ? 'sr-only' : 'block mb-1' }}">Length per unit</span><x-ui.input-control class="h-11 text-sm" title="Length per unit" type="number" step="any" min="0.0001" x-model="preparationItem.length" x-bind:placeholder="linkedMaterialIsPhysical ? 'Length each' : 'Whole ' + linkedMaterialPurchaseUnit" x-bind:required="linkedMaterialIsPhysical &amp;&amp; linkedMaterialKind === 'length'" x-bind:disabled="quantityUnit !== 'item' || linkedMaterialKind !== 'length'" /></label>
        <label class="w-16 text-xs text-slate-600"><span class="{{ $inline ? 'sr-only' : 'block mb-1' }}">Length unit</span><x-ui.select-control class="h-11 text-sm" title="Length unit" x-model="preparationItem.length_unit" x-bind:disabled="quantityUnit !== 'item' || linkedMaterialKind !== 'length'"><option value="mm">mm</option><option value="cm">cm</option><option value="m">m</option></x-ui.select-control></label>
    </div>
</div>
