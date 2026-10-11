<x-ui.collapsible-section title="Material measurements" variant="product" :open="true" x-show="productType === 'material'" x-cloak>
    <x-slot:summary><span x-text="purchaseUnit + (materialKind === 'length' ? ' · ' + purchaseQuantity + ' ' + materialMeasureUnit : materialKind === 'sheet' ? ' · ' + materialLength + ' × ' + materialWidth + ' mm' : '')"></span></x-slot:summary>
    <p class="mb-4 text-sm text-slate-600">Describe what you purchase. Recipes use counts, lengths and cut sizes. Materials are kept off the public store and do not need stock counts.</p>
    <div class="product-editor__fields">
        <x-ui.select name="material_kind" label="Measure as" x-model="materialKind" x-on:change="changeMaterialKind()" x-bind:disabled="productType !== 'material'">
            <option value="unit">Individual items</option><option value="length">Length (roll or dowel)</option><option value="sheet">Sheet</option>
        </x-ui.select>
        <x-ui.input name="purchase_unit" label="Purchased as" placeholder="Roll, sheet, pack or each" fieldClasses="max-w-48" x-model="purchaseUnit" x-bind:disabled="productType !== 'material'" />
        <div x-show="materialKind !== 'sheet'">
            <x-ui.input name="purchase_quantity" label="Amount in each purchase unit" type="number" min="0.0001" step="any" x-model="purchaseQuantity" x-bind:disabled="productType !== 'material' || materialKind === 'sheet'" />
        </div>
        <div x-show="materialKind === 'length'">
            <x-ui.select name="material_measure_unit" label="Length unit" x-model="materialMeasureUnit" x-bind:disabled="productType !== 'material' || materialKind !== 'length'">
                <option value="mm">Millimetres (mm)</option><option value="cm">Centimetres (cm)</option><option value="m">Metres (m)</option>
            </x-ui.select>
        </div>
        <div x-show="materialKind === 'sheet'"><x-ui.input name="material_length_mm" label="Sheet length (mm)" type="number" step="any" min="0.001" x-model="materialLength" x-bind:disabled="productType !== 'material' || materialKind !== 'sheet'" /></div>
        <div x-show="materialKind === 'sheet'"><x-ui.input name="material_width_mm" label="Sheet width (mm)" type="number" step="any" min="0.001" x-model="materialWidth" x-bind:disabled="productType !== 'material' || materialKind !== 'sheet'" /></div>
    </div>
    <div x-show="materialKind === 'sheet'" class="mt-4">
        <div class="mb-3 flex items-center justify-between gap-3"><h3 class="font-semibold">Cut sizes and usable yield</h3><x-ui.button type="button" color="primary-outline" size="small" x-on:click="addCutSize()">Add cut size</x-ui.button></div>
        <p class="mb-3 text-sm text-slate-600">Enter the number of usable pieces you normally cut from one sheet. Costs include the unused material. Reusing offcuts gives you a saving.</p>
        <template x-for="(cut, index) in cutSizes" :key="cut.key">
            <div class="mb-3 product-editor__compact-fields rounded-lg border border-slate-200 p-3">
                <input type="hidden" x-bind:name="`cut_sizes[${index}][id]`" x-bind:value="cut.id" x-bind:disabled="productType !== 'material' || materialKind !== 'sheet'">
                <label class="w-40 text-sm">Length (mm)<x-ui.input-control class="mt-1" type="number" min="0.001" step="any" x-bind:name="`cut_sizes[${index}][length_mm]`" x-model="cut.length_mm" x-bind:disabled="productType !== 'material' || materialKind !== 'sheet'" required /></label>
                <label class="w-40 text-sm">Width (mm)<x-ui.input-control class="mt-1" type="number" min="0.001" step="any" x-bind:name="`cut_sizes[${index}][width_mm]`" x-model="cut.width_mm" x-bind:disabled="productType !== 'material' || materialKind !== 'sheet'" required /></label>
                <label class="w-40 text-sm">Pieces per sheet<x-ui.input-control class="mt-1" type="number" min="1" step="1" x-bind:name="`cut_sizes[${index}][yield]`" x-model="cut.yield" x-bind:disabled="productType !== 'material' || materialKind !== 'sheet'" required /></label>
                <div class="flex self-end items-center gap-2"><x-ui.button type="button" size="small" color="outline" x-on:click="cut.yield = gridYield(cut) || cut.yield" x-bind:disabled="!gridYield(cut)" title="Use the best simple grid, allowing rotation">Use grid yield</x-ui.button><x-ui.row-action type="button" label="Remove cut size" icon="fa-solid fa-trash-can" tone="danger" x-on:click="cutSizes.splice(index, 1)" /></div>
            </div>
        </template>
        @foreach($errors->getMessages() as $field => $messages)
            @if(str_starts_with($field, 'cut_sizes'))<p class="text-sm text-red-700">{{ $messages[0] }}</p>@endif
        @endforeach
    </div>
</x-ui.collapsible-section>

<x-ui.collapsible-section title="Materials" titleExpression="isKit ? 'Kit recipe' : 'Materials'" variant="product" :open="!isset($product) || $errors->any()" x-show="isPhysicalProduct()" x-cloak>
    <x-slot:summary><span x-text="recipeRows.length ? recipeRows.length + ' item' + (recipeRows.length === 1 ? '' : 's') : 'Purchased item'"></span></x-slot:summary>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="min-w-0 grow basis-96 text-sm text-slate-600" x-text="isKit ? 'Add the items and quantities needed for one kit.' : 'Link materials used to make one item. Leave empty for items purchased ready to sell.'">Link materials used to make one item. Leave empty for items purchased ready to sell.</p>
        <x-ui.button type="button" size="small" color="primary-outline" class="shrink-0" x-on:click="addRecipeRow()" x-text="isKit ? 'Add recipe item' : 'Add material'">Add material</x-ui.button>
    </div>
    <template x-for="(row, index) in recipeRows" :key="row.key">
        <div class="product-editor__recipe-row rounded-lg border border-slate-200 p-3" x-on:product-item-link-changed="recipeItemChanged(row)">
            <div class="product-editor__recipe-item">
                <x-admin.product-item-field model="row" catalog-expression="recipeCatalog" disabled-expression="!isPhysicalProduct()" label="Item" placeholder="Type to find an item" :require-selection="true" />
                <input type="hidden" x-bind:name="`recipe[${index}][component_product_id]`" x-bind:value="row.product_id" x-bind:disabled="!isPhysicalProduct()">
            </div>
            <label class="product-editor__recipe-quantity text-sm">Quantity
                <x-ui.input-control class="mt-1 h-11" type="number" min="0.001" step="any" x-bind:name="`recipe[${index}][quantity]`" x-model="row.quantity" x-bind:disabled="!isPhysicalProduct()" required />
            </label>
            <label class="product-editor__recipe-option text-sm" x-show="recipeItem(row)?.variants?.length">Variant
                <x-ui.select-control class="mt-1 h-11" x-bind:name="`recipe[${index}][component_variant_id]`" x-model="row.component_variant_id" x-bind:disabled="!isPhysicalProduct() || !recipeItem(row)?.variants?.length">
                    <option value="" x-text="recipeItem(row)?.base_option_name || ''"></option><template x-for="variant in recipeItem(row)?.variants || []" :key="variant.id"><option x-bind:value="variant.id" x-text="variant.name"></option></template>
                </x-ui.select-control>
            </label>
            <label class="product-editor__recipe-option text-sm" x-show="recipeItem(row)?.material_kind === 'sheet'">Cut size
                <x-ui.select-control class="mt-1 h-11" x-bind:name="`recipe[${index}][cut_size_id]`" x-model="row.cut_size_id" x-bind:disabled="!isPhysicalProduct() || recipeItem(row)?.material_kind !== 'sheet'">
                    <option value="">Whole sheet</option><template x-for="cut in recipeItem(row)?.cut_sizes || []" :key="cut.id"><option x-bind:value="cut.id" x-text="cut.label"></option></template>
                </x-ui.select-control>
            </label>
            <div class="product-editor__recipe-length" x-show="recipeItem(row)?.material_kind === 'length'">
                <label class="min-w-0 flex-1 text-sm">Length each
                    <x-ui.input-control class="mt-1 h-11" type="number" min="0.0001" step="any" x-bind:name="`recipe[${index}][length]`" x-model="row.length" x-bind:disabled="!isPhysicalProduct() || recipeItem(row)?.material_kind !== 'length'" required />
                </label>
                <label class="w-16 text-sm">Unit
                    <x-ui.select-control class="mt-1 h-11" x-bind:name="`recipe[${index}][length_unit]`" x-model="row.length_unit" x-bind:disabled="!isPhysicalProduct() || recipeItem(row)?.material_kind !== 'length'">
                        <option value="mm">mm</option><option value="cm">cm</option><option value="m">m</option>
                    </x-ui.select-control>
                </label>
            </div>
            <label class="product-editor__recipe-note text-sm">Preparation note
                <x-ui.input-control class="mt-1 h-11" placeholder="Optional" maxlength="500" x-bind:name="`recipe[${index}][note]`" x-model="row.note" x-bind:disabled="!isPhysicalProduct()" />
            </label>
            <div class="product-editor__recipe-cost">
                <span class="mb-1 block text-sm">Cost <span class="text-xs text-slate-500">(ex GST)</span></span>
                <span class="flex h-11 items-center whitespace-nowrap text-sm font-semibold tabular-nums" x-text="costingMoney(recipeRowCost(row))"></span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <x-ui.button variant="soft" size="icon" color="secondary" icon="pen-ruler" label="Edit material measurements and cut sizes" x-show="recipeItem(row)?.product_type === 'material'" x-bind:href="recipeItem(row)?.url" target="_blank" class="rounded-xl" />
                <x-ui.row-action type="button" label="Remove item" icon="fa-solid fa-trash-can" tone="danger" x-on:click="recipeRows.splice(index, 1)" />
            </div>
        </div>
    </template>
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'recipe'))<p class="text-sm text-red-700">{{ $messages[0] }}</p>@endif
    @endforeach
</x-ui.collapsible-section>

<x-ui.collapsible-section title="Purchase costs and pricing" variant="product" :open="!isset($product) || $errors->any()" x-show="productType !== 'digital'" x-cloak>
    <x-slot:summary><span x-text="costingMoney(recipeMaterialCost()) + (productType === 'material' ? ' per ' + purchaseUnit : ' materials')"></span></x-slot:summary>
    <div class="product-editor__fields">
        <div x-show="!recipeRows.length || productType === 'material'">
            <x-ui.input name="unit_cost_ex_tax" label="Fallback purchase cost (ex GST)" type="number" min="0" step="0.01" :moneyFormat="true" inputmode="decimal" x-model="unitCost" x-bind:disabled="productType === 'digital'" info="Per base pack for Store Items and Kits, or per purchase unit for Materials. The latest tagged expense takes precedence. Leave blank when the cost is unknown." />
        </div>
        <div x-show="isPhysicalProduct()">
            <x-ui.input name="operational_cost_ex_tax" label="Operational allowance (ex GST)" type="number" min="0" step="0.01" :moneyFormat="true" inputmode="decimal" x-model="operationalCost" x-bind:placeholder="costingMoney(effectiveOperationalCost())" x-bind:disabled="!isPhysicalProduct()" info="Bags, labels and other small costs. Blank uses the item or kit default." />
        </div>
        <div x-show="isPhysicalProduct()">
            <x-ui.input name="time_cost_ex_tax" label="Time allowance (ex GST)" type="number" min="0" step="0.01" :moneyFormat="true" inputmode="decimal" x-model="timeCost" x-bind:placeholder="costingMoney(effectiveTimeCost())" x-bind:disabled="!isPhysicalProduct()" info="Blank uses the default. Kits have twice the item time allowance." />
        </div>
        <div x-show="isPhysicalProduct()">
            <x-ui.input name="markup_percent" label="Markup (%)" type="number" min="0" step="0.01" x-model="markup" x-bind:placeholder="costingDefaults.markup" x-bind:disabled="!isPhysicalProduct()" info="Blank uses the default markup in Site Options." />
        </div>
    </div>
    <div x-show="isPhysicalProduct()">
        <input type="hidden" name="automatic_pricing" value="0">
        <x-ui.checkbox name="automatic_pricing" label="Update the base price automatically from costs" x-model="automaticPricing" :checked="old('automatic_pricing', $product->automatic_pricing ?? false)" x-bind:disabled="!isPhysicalProduct()" />
        @error('automatic_pricing')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4" data-variant-pricing-summary>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-semibold text-sky-900">Pricing by variant</h3>
                <p class="text-xs text-slate-600">Operations + time: <strong class="tabular-nums" x-text="costingMoney(effectiveOperationalCost() + effectiveTimeCost())"></strong> ex GST per pack</p>
            </div>
            <x-ui.table variant="plain" caption="Costs and pricing for the base pack and all variants" table-class="min-w-[52rem] text-sm">
                <x-slot:header>
                    <th scope="col" class="pb-3 pr-4 text-left">Pack</th>
                    <th scope="col" class="px-3 pb-3 text-right whitespace-nowrap">Materials <span class="block text-xs font-normal text-slate-600">ex GST</span></th>
                    <th scope="col" class="px-3 pb-3 text-right whitespace-nowrap">Total cost <span class="block text-xs font-normal text-slate-600">ex GST</span></th>
                    <th scope="col" class="px-3 pb-3 text-right whitespace-nowrap">Suggested price <span class="block text-xs font-normal text-slate-600">inc GST</span></th>
                    <th scope="col" class="px-3 pb-3 text-right whitespace-nowrap">Selling price <span class="block text-xs font-normal text-slate-600">inc GST</span></th>
                    <th scope="col" class="pb-3 pl-3 text-right"><span class="sr-only">Apply suggested price</span></th>
                </x-slot:header>
                <x-slot:body>
                    <template x-for="row in pricingRows()" :key="row.key">
                        <tr class="border-t border-sky-200 align-top">
                            <td class="py-3 pr-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong x-text="row.name"></strong>
                                    <x-ui.badge color="primary" x-show="!row.variant">Base</x-ui.badge>
                                    <x-ui.badge color="gray" x-show="!row.active">Inactive</x-ui.badge>
                                </div>
                                <p class="mt-1 text-xs text-slate-600" x-text="packUnits(row.variant) === null ? 'Check pack size' : packUnits(row.variant) + (packUnits(row.variant) === 1 ? ' item per pack' : ' items per pack')"></p>
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums" x-text="costingMoney(recipeMaterialCost(row.variant))"></td>
                            <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums"><strong x-text="costingMoney(totalEstimatedCost(row.variant))"></strong></td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                <strong class="text-base text-sky-900" x-text="costingMoney(suggestedSellingPrice(row.variant))"></strong>
                                <p class="mt-1 text-xs text-slate-600" x-show="pricePerItem(row.variant, true) !== null" x-text="costingMoney(pricePerItem(row.variant, true)) + ' per item'"></p>
                                <p class="mt-1 text-xs text-slate-600" x-show="isBulkPack(row.variant)" x-text="bulkDiscount(row.variant) + '% bulk discount'"></p>
                                <p class="mt-1 text-xs text-slate-600" x-show="isBulkPack(row.variant)" x-text="'Minimum: ' + costingMoney(costBasedMinimumPrice(row.variant))"></p>
                                <x-ui.badge color="warning" class="mt-2" x-show="minimumPriceApplied(row.variant)">Minimum price applied</x-ui.badge>
                                <x-ui.badge color="danger" class="mt-2" x-show="isBelowCost(row.variant, true)">Below cost</x-ui.badge>
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                <strong class="text-base" x-text="costingMoney(sellingPrice(row.variant), 'Price needed')"></strong>
                                <p class="mt-1 text-xs text-slate-600" x-show="row.variant && String(row.variant.price ?? '').trim() === ''">Uses base price</p>
                                <p class="mt-1 text-xs text-slate-600" x-show="pricePerItem(row.variant) !== null" x-text="costingMoney(pricePerItem(row.variant)) + ' per item'"></p>
                                <p class="mt-1 text-xs text-slate-600" x-show="row.variant" x-text="savingLabel(row.variant)"></p>
                                <x-ui.badge color="danger" class="mt-2" x-show="isBelowCost(row.variant)">Below cost</x-ui.badge>
                            </td>
                            <td class="py-3 pl-3 text-right">
                                <x-ui.badge color="primary" x-show="row.automatic">Automatic</x-ui.badge>
                                <p class="mt-2 text-xs text-slate-600" x-show="row.automatic && suggestedSellingPrice(row.variant) === null">Last price kept until costs are known.</p>
                                <x-ui.button size="small" color="primary-outline" x-show="!row.automatic" x-bind:disabled="!canUseSuggestedPrice(row.variant)" x-bind:aria-label="'Use suggested price for ' + row.name" x-on:click="useSuggestedPrice(row.variant)">Use suggested price</x-ui.button>
                            </td>
                        </tr>
                    </template>
                </x-slot:body>
            </x-ui.table>
        </div>
        <p class="mt-2 text-xs text-slate-600">Larger packs scale the base selling price by their quantity, then apply the bulk discount (20% by default). The minimum covers the pack's materials, allowances, markup and GST. Prices round up to .95. Base and other variant prices use costs plus markup and GST.</p>
        <p class="mt-2 text-xs text-slate-600">Per-item savings compare selling prices with the base pack after rounding and any minimum price adjustment. Set each variant's bulk discount and automatic pricing in Variants.</p>
        <p class="mt-2 text-xs text-slate-600" x-show="automaticPricing || variants.some(variant => variant.automatic_pricing)">Automatic pricing keeps the last price if a cost becomes unknown. It updates the related Consumables, Operational and Owner remuneration allocations; review the remaining allocation.</p>
        @foreach($errors->getMessages() as $field => $messages)
            @if(str_starts_with($field, 'variants.') && (str_ends_with($field, '.bulk_discount_percent') || str_ends_with($field, '.automatic_pricing')))
                <p class="mt-2 text-sm text-red-700" role="alert">{{ $messages[0] }}</p>
            @endif
        @endforeach
    </div>
    @if(isset($product) && $product->purchases->isNotEmpty())
        <div class="mt-5">
            <h3 class="mb-3 font-semibold">Tagged purchases</h3>
            <x-ui.table variant="listing" caption="Tagged purchases" table-class="min-w-[44rem]">
                <x-slot:header>
                    <th scope="col" class="text-left whitespace-nowrap">Date</th>
                    <th scope="col" class="text-left">Expense</th>
                    <th scope="col" class="text-right whitespace-nowrap">Quantity</th>
                    <th scope="col" class="text-left whitespace-nowrap">Purchased as</th>
                    <th scope="col" class="text-right whitespace-nowrap">Unit cost <span class="block text-xs font-normal">ex GST</span></th>
                    <th scope="col" class="text-right whitespace-nowrap">Line total <span class="block text-xs font-normal">ex GST</span></th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($product->purchases->sortByDesc('id')->take(12) as $purchase)
                        @php
                            $unitCost = $purchase->quantity > 0 ? $purchase->total_cost_ex_tax / $purchase->quantity : null;
                            $unitCostDecimals = $unitCost !== null && round($unitCost, 4) !== round($unitCost, 2) ? 4 : 2;
                            $purchaseUnit = $purchase->individual_units ? 'Individual units' : ($product->isPhysical() ? ($purchase->variant?->displayName() ?? $product->baseOptionName()) : $product->purchaseUnitLabel());
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $purchase->expense?->paid_on?->format('j M Y') ?? 'Undated' }}</td>
                            <td>
                                <x-ui.button type="link" :href="route('admin.expense.edit', $purchase->expense_id)" class="font-medium hover:underline">{{ $purchase->expense?->supplier ?: 'Expense #'.$purchase->expense_id }}</x-ui.button>
                                <div class="mt-1 text-xs text-slate-500">{{ $purchase->expense?->invoice_id ?: 'Expense #'.$purchase->expense_id }}</div>
                            </td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ \App\Models\ProductRecipeComponent::formatNumber($purchase->quantity) }}</td>
                            <td>{{ $purchaseUnit }}</td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ $unitCost === null ? '—' : '$'.number_format($unitCost, $unitCostDecimals) }}</td>
                            <td class="text-right whitespace-nowrap tabular-nums">${{ number_format($purchase->total_cost_ex_tax, 2) }}</td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            @if($product->purchases->count() > 12)
                <p class="mt-2 text-xs text-slate-500">Showing 12 of {{ $product->purchases->count() }} tagged purchases.</p>
            @endif
        </div>
    @endif
</x-ui.collapsible-section>
