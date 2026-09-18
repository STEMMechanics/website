<template x-if="item.kind === 'product'">
    <div class="mb-3 grid gap-3 sm:grid-cols-2">
        <div>

            <x-ui.select label="Store product" aria-label="Store product" x-model="item.source_id" x-on:change="item.source_id = $event.target.value; item.source_variant_id = '0'; applyProductSelection(index)">
                <option value="">Select a product</option>
                <template x-for="product in catalogProducts" :key="product.id"><option :value="String(product.id)" x-text="product.title"></option></template>
            </x-ui.select>
        </div>
        <div x-show="variantOptions(item).length > 0">

            <x-ui.select label="Variant" aria-label="Product variant" x-model="item.source_variant_id" x-on:change="item.source_variant_id = $event.target.value; applyProductSelection(index)">
                <template x-for="variant in variantOptions(item)" :key="variant.id"><option :value="String(variant.id)" x-text="variant.name"></option></template>
            </x-ui.select>
        </div>
    </div>
</template>
