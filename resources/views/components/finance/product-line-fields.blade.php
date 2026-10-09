<template x-if="item.kind === 'product'">
    <div class="mb-3 grid gap-3 sm:grid-cols-2">
        <div
            x-id="['store-product-input', 'store-product-options', 'store-product-hint']"
            x-init="productSearchState(item)"
            x-on:keydown.escape.window="closeProductSuggestions(item)"
            x-on:resize.window="if (productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input')) positionProductSuggestions(item, document.getElementById($id('store-product-input')))"
            x-on:scroll.window.capture="if (productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input')) positionProductSuggestions(item, document.getElementById($id('store-product-input')))"
            x-on:pointerdown.window="
                if (productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input')) {
                    const target = $event.target;
                    const menu = document.getElementById($id('store-product-options'));
                    const input = document.getElementById($id('store-product-input'));
                    if (!(target instanceof Node) || (!(input instanceof Node) || !input.contains(target)) && (!(menu instanceof Node) || !menu.contains(target))) {
                        closeProductSuggestions(item, $id('store-product-input'));
                    }
                }
            "
            x-on:focusin.window="
                if (productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input')) {
                    const target = $event.target;
                    const menu = document.getElementById($id('store-product-options'));
                    const input = document.getElementById($id('store-product-input'));
                    if (!(target instanceof Node) || (!(input instanceof Node) || !input.contains(target)) && (!(menu instanceof Node) || !menu.contains(target))) {
                        closeProductSuggestions(item, $id('store-product-input'));
                    }
                }
            "
        >
            <label class="mb-1 block pl-1 text-sm" x-bind:for="$id('store-product-input')">Store product <span class="text-red-600" aria-hidden="true">*</span></label>
            <x-ui.input-control
                x-bind:id="$id('store-product-input')"
                x-bind:value="productSearchState(item).query"
                x-bind:disabled="isLocked"
                x-bind:aria-invalid="!isLocked && !findProduct(item.source_id)"
                x-bind:aria-required="true"
                x-bind:aria-describedby="!isLocked && !findProduct(item.source_id) ? $id('store-product-hint') : null"
                x-bind:aria-expanded="productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input')"
                x-bind:aria-controls="$id('store-product-options')"
                x-bind:aria-activedescendant="productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input') && productSuggestions(item).length ? $id('store-product-options') + '-' + productSearchState(item).selectedIndex : null"
                role="combobox"
                aria-autocomplete="list"
                autocomplete="off"
                placeholder="Search store products by name or SKU"
                class="h-11"
                x-bind:class="isLocked || findProduct(item.source_id) ? '' : 'border-red-400 focus:border-red-600'"
                x-on:focus="openProductSuggestions(item, $el, $id('store-product-input'))"
                x-on:input="searchProducts(item, index, $event.target.value, $el, $id('store-product-input'))"
                x-on:keydown.arrow-down.prevent="moveProductSuggestion(item, 1, $el, $id('store-product-input'))"
                x-on:keydown.arrow-up.prevent="moveProductSuggestion(item, -1, $el, $id('store-product-input'))"
                x-on:keydown.enter.prevent.stop="confirmProductSuggestion(item, index)"
            />
            <p
                x-cloak
                x-show="!isLocked && !findProduct(item.source_id)"
                x-bind:id="$id('store-product-hint')"
                class="mt-1 pl-1 text-xs text-red-700"
            >Choose a store product from the suggestions.</p>

            <template x-teleport="body">
                <div
                    x-cloak
                    x-show="productSearchState(item).open && productSearchState(item).activeId === $id('store-product-input')"
                    class="fixed z-50 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                    x-bind:style="{
                        top: productSearchState(item).top + 'px',
                        left: productSearchState(item).left + 'px',
                        width: productSearchState(item).width + 'px',
                        maxHeight: productSearchState(item).maxHeight + 'px'
                    }"
                    role="listbox"
                    x-bind:id="$id('store-product-options')"
                >
                    <template x-for="(product, productIndex) in productSuggestions(item)" :key="product.id">
                        <button
                            type="button"
                            role="option"
                            class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm"
                            x-bind:class="productIndex === productSearchState(item).selectedIndex ? 'bg-sky-50 text-sky-800' : 'text-slate-700 hover:bg-slate-50'"
                            x-bind:id="$id('store-product-options') + '-' + productIndex"
                            x-bind:aria-selected="productIndex === productSearchState(item).selectedIndex"
                            x-on:mouseenter="productSearchState(item).selectedIndex = productIndex"
                            x-on:mousedown.prevent
                            x-on:click="chooseProductSuggestion(item, index, product)"
                        >
                            <span class="min-w-0 truncate" x-text="product.title"></span>
                            <span x-show="product.sku" class="shrink-0 text-xs text-slate-500" x-text="product.sku"></span>
                        </button>
                    </template>
                    <p x-show="!productSuggestions(item).length" class="px-3 py-2 text-sm text-slate-500">
                        <span x-show="String(productSearchState(item).query || '').trim()" x-cloak>No store products match this search.</span>
                        <span x-show="!String(productSearchState(item).query || '').trim()">Type a product name or SKU to search.</span>
                    </p>
                </div>
            </template>
        </div>

        <div x-show="variantOptions(item).length > 0">
            <x-ui.select label="Variant" aria-label="Product variant" x-model="item.source_variant_id" x-on:change="item.source_variant_id = $event.target.value; applyProductSelection(index)">
                <template x-for="variant in variantOptions(item)" :key="variant.id"><option :value="String(variant.id)" x-text="variant.name"></option></template>
            </x-ui.select>
        </div>
    </div>
</template>
