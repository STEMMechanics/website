@php
    $allocationEditor = app(\App\Services\Finance\ProductAllocationEditor::class);
    $allocationCategories = $allocationEditor->categories();
    $allocationEditorConfig = ['categories' => $allocationCategories->pluck('id')->all(), 'taxRate' => (float) ($product->tax_rate ?? 0.1), 'data' => $allocationEditor->data($product ?? null), 'old' => old('product_allocations_json') ? json_decode(old('product_allocations_json'), true) : null];
@endphp
<div x-data="SM.productAllocationEditor(@js($allocationEditorConfig))" id="cost-centre-allocation">
    <x-ui.collapsible-section title="Cost centre allocation" variant="product" :open="!isset($product) || $errors->has('product_allocations_json')">
        <x-slot:summary><span x-text="summary" x-bind:class="summary !== 'All options allocated' ? 'text-amber-800' : ''"></span></x-slot:summary>
        <input type="hidden" name="product_allocations_json" x-bind:value="payload">
        <p class="text-sm text-slate-600">Amounts per pack, excluding GST.</p>
        @error('product_allocations_json')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="md:hidden">
            <x-ui.select label="Product option" name="allocation_option" id="allocation_option" x-model="selectedColumn">
                <template x-for="column in columns" :key="column.key"><option x-bind:value="column.key" x-text="column.name"></option></template>
            </x-ui.select>
        </div>
        <div class="max-w-full overflow-x-auto" role="region" aria-label="Cost centre allocations by product option" tabindex="0">
            <x-ui.table variant="plain" table-class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left align-top">
                        <th scope="col" class="sticky left-0 z-10 min-w-28 bg-white py-3 pr-3 font-semibold md:min-w-44">Cost centre</th>
                        <template x-for="column in columns" :key="column.key">
                            <th scope="col" class="min-w-40 px-3 py-3 font-normal" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'">
                                <div class="font-semibold" x-text="column.name"></div>
                                <div class="mt-1 text-xs text-slate-500" x-text="'$' + Number(column.price || 0).toFixed(2) + ' inc GST'"></div>

                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allocationCategories as $category)
                        <tr class="border-b border-slate-100" data-category-name="{{ $category->name }}">
                            <th scope="row" class="sticky left-0 z-10 bg-white py-3 pr-3 text-left font-medium">{{ $category->name }}</th>
                            <template x-for="column in columns" :key="column.key">
                                <td class="px-3 py-3 align-top" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'">
                                    <div class="w-32 space-y-2">
                                        <div class="relative">
                                            <span class="pointer-events-none absolute left-3 top-2 text-slate-500">$</span>
                                            <x-ui.input-control type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" class="h-9 pl-7 pr-2 text-right tabular-nums" x-bind:aria-label="column.name + ' — ' + $el.closest('tr').dataset.categoryName + ' amount'" x-bind:value="values(column)[{{ $category->id }}].amount" x-on:input="setValue(column, {{ $category->id }}, $event.target.value)" x-on:blur="format(column, {{ $category->id }})" />
                                        </div>
                                    </div>
                                </td>
                            </template>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="align-top">
                        <th scope="row" class="sticky left-0 z-10 bg-white py-4 pr-3 text-left font-semibold">Allocated</th>
                        <template x-for="column in columns" :key="column.key">
                            <td class="px-3 py-4" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'">
                                <div class="whitespace-nowrap font-semibold tabular-nums" x-text="'$' + (total(column).allocated / 100).toFixed(2) + ' / $' + (total(column).net / 100).toFixed(2)"></div>
                                <div class="mt-2" x-show="total(column).missing">
                                    <x-ui.badge tone="warning"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i><span x-text="total(column).excessive ? 'Check amounts' : '$' + (total(column).remaining / 100).toFixed(2) + ' unallocated'"></span></x-ui.badge>
                                </div>
                            </td>
                        </template>
                    </tr>
                </tfoot>
            </x-ui.table>
        </div>
    </x-ui.collapsible-section>
</div>
