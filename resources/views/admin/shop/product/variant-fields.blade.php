<div class="product-variant__body">
    <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id">
    <input type="hidden" :name="`variants[${index}][is_active]`" :value="variant.is_active ? 1 : 0">
    <input type="hidden" :name="`variants[${index}][is_base]`" :value="variant.is_base ? 1 : 0">
    <input type="hidden" :name="`variants[${index}][original_option]`" :value="variant.original_option ? 1 : 0">
    <input type="hidden" :name="`variants[${index}][sort_order]`" :value="variant.sort_order">

    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <x-ui.checkbox label="Is Active" x-model="variant.is_active" x-on:change="variantActiveChanged(variant)" noWrapper="true" />
        <x-ui.radio label="Base variant" name="base_variant_choice" x-bind:value="variant.editor_key" x-bind:checked="variant.is_base" x-bind:disabled="!variant.is_active" x-on:change="selectBaseVariant(variant)" noWrapper="true" />
    </div>

    <div class="product-variant__identity">
        <x-ui.input label="Variant Name" x-bind:name="`variants[${index}][name]`" x-model="variant.name" x-on:blur="syncVariantSku(index)" />
        <x-ui.input label="SKU" x-bind:name="`variants[${index}][sku]`" x-model="variant.sku" />
    </div>

    <div class="product-variant__columns border-t border-gray-200 pt-5">
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Pricing</h3>
            <div class="pb-3" x-show="isPhysicalProduct()" x-cloak x-bind:data-validation-field="`variants[${index}][automatic_pricing]`">
                <input type="hidden" x-bind:name="`variants[${index}][automatic_pricing]`" value="0" x-bind:disabled="!isPhysicalProduct()">
                <x-ui.checkbox label="Automatic pricing from costs" x-bind:name="`variants[${index}][automatic_pricing]`" x-model="variant.automatic_pricing" x-bind:disabled="!isPhysicalProduct()" noWrapper="true" />
                <div data-validation-error class="mt-2 text-xs text-red-600" role="alert" hidden></div>
            </div>
            <p class="text-sm text-gray-700" x-show="isPhysicalProduct() && variant.automatic_pricing" x-cloak>
                Selling price: <strong x-text="costingMoney(sellingPrice(variant), 'Price needed')"></strong>
                <span class="text-xs text-gray-500" x-show="suggestedSellingPrice(variant) === null">Last price kept until costs are known.</span>
            </p>
            <div class="product-variant__numbers">
                <div x-show="!isPhysicalProduct() || !variant.automatic_pricing" x-cloak>
                    <x-ui.input label="Price" labelInfo="(inc GST)" type="number" step="0.01" min="0" :moneyFormat="true" inputmode="decimal" x-bind:name="`variants[${index}][price]`" x-model="variant.price" x-bind:readonly="isPhysicalProduct() && variant.automatic_pricing" placeholder="Use fallback price" />
                </div>
                <div x-show="!isPhysicalProduct() || !variant.automatic_pricing" x-cloak>
                    <x-ui.input label="Recommended Price" labelInfo="(inc GST)" type="number" step="0.01" min="0" :moneyFormat="true" inputmode="decimal" x-bind:name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price" placeholder="Optional" />
                </div>
                <div x-show="isPhysicalProduct()" x-cloak>
                    <input type="hidden" x-bind:name="`variants[${index}][bulk_discount_percent]`" x-bind:value="variant.bulk_discount_percent ?? ''" x-bind:disabled="!isPhysicalProduct() || isBulkPack(variant)">
                    <x-ui.input label="Bulk discount" labelInfo="(%)" type="number" min="0" max="100" step="0.01" inputmode="decimal" x-bind:name="`variants[${index}][bulk_discount_percent]`" x-model="variant.bulk_discount_percent" x-bind:disabled="!isBulkPack(variant)" x-bind:placeholder="effectiveProductBulkDiscount()" info="Blank inherits the product discount; 0 means no discount." />
                </div>
                <div x-show="isPhysicalProduct()" x-cloak>
                    <x-ui.input label="Purchase cost" labelInfo="(ex GST)" type="number" min="0" step="0.0001" inputmode="decimal" x-bind:name="`variants[${index}][unit_cost_ex_tax]`" x-model="variant.unit_cost_ex_tax" x-bind:disabled="!isPhysicalProduct()" placeholder="Use shared cost" info="Tagged expenses take precedence." />
                </div>
            </div>
        </div>

        <div class="space-y-4" x-show="isPhysicalProduct()" x-cloak data-variant-inventory>
            <h3 class="text-sm font-semibold text-gray-900">Stock</h3>
            <div class="product-variant__numbers">
                <div data-variant-pack-units>
                    <x-ui.input label="Units per pack" type="number" min="1" step="1" x-bind:name="`variants[${index}][inventory_units]`" x-model="variant.inventory_units" x-bind:disabled="!isPhysicalProduct()" info="e.g. 10 for a 10-pack." />
                </div>
                <div x-show="!sharedInventory" x-cloak>
                    <x-ui.input label="Packs in stock" type="number" min="0" step="1" x-bind:name="`variants[${index}][inventory_quantity]`" x-model="variant.inventory_quantity" x-bind:disabled="!isPhysicalProduct()" placeholder="Untracked" info="Leave blank for untracked stock." />
                </div>
                <div x-show="!sharedInventory" x-cloak>
                    <x-ui.input label="Low-stock alert" labelInfo="(packs)" type="number" min="1" step="1" x-bind:name="`variants[${index}][low_stock_threshold]`" x-model="variant.low_stock_threshold" x-bind:disabled="!isPhysicalProduct()" placeholder="Use shared default" />
                </div>
            </div>
            <p class="text-xs text-gray-600" x-text="packStockSummary(sharedInventory ? baseInventoryQuantity : variant.inventory_quantity, variant.inventory_units, sharedInventory)"></p>
        </div>
    </div>

    <div class="space-y-6 border-t border-gray-200 pt-5">
        <div>
            <label class="mb-2 block text-sm font-semibold text-gray-900" x-bind:for="`variant-description-${index}`">Notes</label>
            <x-ui.textarea-control rows="4" x-bind:id="`variant-description-${index}`" x-bind:class="variantTextareaClasses" x-bind:name="`variants[${index}][description]`" x-model="variant.description" x-bind:placeholder="productType === 'digital' ? 'Who this licence covers and where it may be used.' : 'Optional notes for this option.'"></x-ui.textarea-control>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-100 px-3 py-2">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Product detail overrides</h3>
                    <p class="text-xs text-gray-500">Matching names replace shared product values; new names are appended.</p>
                </div>
                <x-ui.button type="button" color="primary-outline" x-on:click="addVariantProductDetail(index)" size="small">Add detail</x-ui.button>
            </div>
            <x-ui.table variant="plain" table-class="w-full table-fixed border-collapse" x-show="(variant.product_details || []).length > 0" x-cloak>
                <thead class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-2/5 px-3 py-2">Detail</th>
                        <th class="px-3 py-2">Override value</th>
                        <th class="w-12 px-2 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template x-for="(detail, detailIndex) in (variant.product_details || [])" :key="detailIndex">
                        <tr>
                            <td class="border-r border-gray-200 p-0">
                                <x-ui.input-control type="text" x-bind:name="`variants[${index}][product_details][${detailIndex}][key]`" x-model="detail.key" x-on:blur="variant.product_details = skuLastDetails(variant.product_details || [])" x-bind:data-variant-detail-key="`${index}-${detailIndex}`" class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-300" placeholder="e.g. Pack size" />
                            </td>
                            <td class="border-r border-gray-200 p-0">
                                <x-ui.input-control type="text" x-bind:name="`variants[${index}][product_details][${detailIndex}][value]`" x-model="detail.value" class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-300" placeholder="Variant value" x-on:keydown.tab="addVariantProductDetailAfterTab($event, index, detailIndex)" />
                            </td>
                            <td class="p-0 text-center">
                                <x-ui.button variant="plain" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600" x-on:click="removeVariantProductDetail(index, detailIndex)" title="Remove detail" aria-label="Remove detail">
                                    <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                                </x-ui.button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </x-ui.table>
            <p class="px-3 py-3 text-sm text-gray-500" x-show="(variant.product_details || []).length === 0">No detail overrides.</p>
        </div>
    </div>

    @if($hasCalculatedShipping)
    <div class="border-t border-gray-200 pt-4" x-show="isPhysicalProduct()" x-cloak>
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-gray-900">Packaging overrides</h3>
            <p class="mt-1 text-xs text-gray-500">Only fill these in when this pack has different packed measurements from the shared defaults.</p>
        </div>
        <div class="product-editor__compact-fields">
            <x-ui.input label="Packed Length" labelInfo="(mm)" type="number" min="1" max="10000" x-bind:name="`variants[${index}][length_mm]`" x-model="variant.length_mm" placeholder="Use shared default" class="w-40" />
            <x-ui.input label="Packed Width" labelInfo="(mm)" type="number" min="1" max="10000" x-bind:name="`variants[${index}][width_mm]`" x-model="variant.width_mm" placeholder="Use shared default" class="w-40" />
            <x-ui.input label="Packed Height" labelInfo="(mm)" type="number" min="1" max="10000" x-bind:name="`variants[${index}][height_mm]`" x-model="variant.height_mm" placeholder="Use shared default" class="w-40" />
            <x-ui.input label="Packed Weight" labelInfo="(g)" type="number" min="0" x-bind:name="`variants[${index}][weight_grams]`" x-model="variant.weight_grams" placeholder="Use shared default" class="w-40" />
        </div>
        <x-product-postage-preview :variant="true" />
    </div>

    @endif

    <div class="border-t border-gray-200 pt-5" x-show="isPhysicalProduct()" x-cloak>
        <x-ui.checkbox label="Allow back ordering" x-bind:name="`variants[${index}][allow_backorder]`" x-model="variant.allow_backorder" noWrapper="true" />
        <div class="mt-4 product-editor__compact-fields" x-show="variant.allow_backorder" x-cloak>
            <x-ui.select label="Backorder Estimate Type" x-bind:name="`variants[${index}][backorder_shipping_estimate_type]`" x-model="variant.backorder_shipping_estimate_type" class="w-56">
                <option value="static">Specific date</option>
                <option value="dynamic">Days from today</option>
            </x-ui.select>
            <div x-show="variant.backorder_shipping_estimate_type === 'dynamic'" x-cloak>
                <x-ui.input label="Days from today" type="number" min="1" step="1" x-bind:name="`variants[${index}][backorder_shipping_offset_days]`" x-model="variant.backorder_shipping_offset_days" />
            </div>
            <div x-show="variant.backorder_shipping_estimate_type !== 'dynamic'" x-cloak>
                <x-ui.input label="Shipping Date" type="date" x-bind:name="`variants[${index}][backorder_shipping_estimate]`" x-model="variant.backorder_shipping_estimate" />
            </div>
        </div>
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600" x-show="isPhysicalProduct() && (Number(variant.awaiting_fulfilment || 0) > 0 || Number(variant.reserved_quantity || 0) > 0)" x-cloak>
        <span>Awaiting fulfilment: <strong x-text="Number(variant.awaiting_fulfilment || 0)"></strong></span>
        <span>Reserved now: <strong x-text="Number(variant.reserved_quantity || 0)"></strong></span>
    </div>
</div>
