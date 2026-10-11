@props(['model' => 'item', 'catalogExpression' => 'productCatalog'])
<div x-data="{ preparationItem: {{ $model }}, preparationCatalog: {{ $catalogExpression }}, get linkedMaterial() { return this.preparationCatalog.find(option => String(option.id) === String(this.preparationItem.product_id)); } }" x-effect="preparationItem = {{ $model }}; preparationCatalog = {{ $catalogExpression }}" class="mt-2">
    <div x-show="linkedMaterial?.material_kind === 'sheet'" x-cloak>
        <label class="text-xs text-slate-600">Cut size
            <x-ui.select-control class="mt-1 text-sm" x-model="preparationItem.cut_size_id">
                <option value="">Whole sheet</option>
                <template x-for="cut in linkedMaterial?.cut_sizes || []" :key="cut.id"><option x-bind:value="cut.id" x-text="cut.label"></option></template>
            </x-ui.select-control>
        </label>
    </div>
    <div class="grid grid-cols-[1fr_5rem] gap-2" x-show="linkedMaterial?.material_kind === 'length'" x-cloak>
        <label class="text-xs text-slate-600">Length of each piece <span x-show="linkedMaterial?.product_type !== 'material'">(optional)</span><x-ui.input-control class="mt-1 text-sm" type="number" step="any" min="0.0001" x-model="preparationItem.length" x-bind:placeholder="linkedMaterial?.product_type === 'material' ? '' : 'Whole pack'" x-bind:required="linkedMaterial?.product_type === 'material' && linkedMaterial?.material_kind === 'length'" x-bind:disabled="linkedMaterial?.material_kind !== 'length'" /></label>
        <label class="text-xs text-slate-600">Unit<x-ui.select-control class="mt-1 text-sm" x-model="preparationItem.length_unit"><option value="mm">mm</option><option value="cm">cm</option><option value="m">m</option></x-ui.select-control></label>
    </div>
</div>
