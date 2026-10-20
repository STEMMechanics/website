@php
    $allocationEditor = app(\App\Services\Finance\ProductAllocationEditor::class);
    $allocationCategories = $allocationEditor->categories();
    $allocationEditorConfig = ['categories' => $allocationCategories->pluck('id')->all(), 'centres' => app(\App\Services\Finance\ProductCostAllocation::class)->centres(), 'taxRate' => (float) ($product->tax_rate ?? \App\Services\Finance\GstRate::current()), 'data' => $allocationEditor->data($product ?? null), 'old' => old('product_allocations_json') ? json_decode(old('product_allocations_json'), true) : null];
@endphp
<div x-data="SM.productAllocationEditor(@js($allocationEditorConfig))" id="cost-centre-allocation">
    <x-ui.collapsible-section title="Cost centre allocation" variant="product" :open="!isset($product) || $errors->has('product_allocations_json')">
        <x-slot:summary><span x-text="summary" x-bind:class="summary.includes('need allocation review') ? 'text-amber-800' : ''"></span></x-slot:summary>
        <input type="hidden" name="product_allocations_json" x-bind:value="payload" x-bind:disabled="automaticAllocation">
        <p class="text-sm text-slate-600" x-text="automaticAllocation ? 'Calculated per pack from purchase costs, allowances and the remaining-funds rule, excluding GST.' : 'Manual amounts per pack, excluding GST. Enable automatic allocation in Purchase costs and pricing.'"></p>
        <p class="text-sm text-slate-600" x-show="!columns.length" x-cloak>No active variants.</p>
        @error('product_allocations_json')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="md:hidden">
            <x-ui.select label="Product option" name="allocation_option" id="allocation_option" x-model="selectedColumn">
                <template x-for="column in columns" :key="column.key"><option x-bind:value="column.key" x-text="column.name"></option></template>
            </x-ui.select>
        </div>
        <div class="max-w-full overflow-x-auto" role="region" aria-label="Cost centre allocations by product option" tabindex="0" x-show="columns.length">
            <x-ui.table variant="plain" table-class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left align-top">
                        <th scope="col" class="sticky left-0 z-10 min-w-28 bg-white py-3 pr-3 font-semibold md:min-w-44">Cost centre</th>
                        <template x-for="column in columns" :key="column.key">
                            <th scope="col" class="min-w-40 px-3 py-3 font-normal" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'">
                                <div class="font-semibold" x-text="column.name"></div>
                                <x-ui.badge color="primary" x-show="column.variant?.is_base">Base</x-ui.badge>
                                <div class="mt-1 text-xs text-slate-500" x-text="'$' + Number(column.price || 0).toFixed(2) + ' inc GST'"></div>

                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allocationCategories as $category)
                        <tr class="border-b border-slate-100" data-category-name="{{ $category->name }}" x-show="!automaticAllocation || columns.some(column => Number(values(column)[{{ $category->id }}].amount) > 0)">
                            <th scope="row" class="sticky left-0 z-10 bg-white py-3 pr-3 text-left font-medium">{{ $category->name }}</th>
                            <template x-for="column in columns" :key="column.key">
                                <td class="px-3 py-3 align-top" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'">
                                    <div x-show="automaticAllocation" x-cloak>
                                        <span class="block py-2 font-medium tabular-nums" x-text="total(column).unknown ? '—' : '$' + values(column)[{{ $category->id }}].amount"></span>
                                        <span class="block text-xs text-slate-500" x-text="contribution(column, {{ $category->id }})"></span>
                                    </div>
                                    <div class="flex items-center gap-1" x-show="!automaticAllocation">
                                        <div class="relative w-32 shrink-0">
                                            <span class="pointer-events-none absolute left-3 top-2 text-slate-500">$</span>
                                            <x-ui.input-control type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" class="h-9 pl-7 pr-2 text-right tabular-nums" x-bind:aria-label="column.name + ' — ' + $el.closest('tr').dataset.categoryName + ' amount'" x-bind:value="values(column)[{{ $category->id }}].amount" x-on:input="$event.target.value = setValue(column, {{ $category->id }}, $event.target.value)" x-on:blur="format(column, {{ $category->id }})" />
                                        </div>
                                        <x-ui.button variant="plain" type="button" class="inline-flex size-9 shrink-0 items-center justify-center rounded border border-slate-300 text-primary-color hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40" x-on:click="allocateRemaining(column, {{ $category->id }})" x-bind:disabled="!Number.isFinite(total(column).remaining)" x-bind:aria-label="'Balance allocation for ' + $el.closest('tr').dataset.categoryName + ' for ' + column.name" title="Balance allocation for {{ $category->name }}">
                                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                        </x-ui.button>
                                    </div>
                                </td>
                            </template>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr x-show="automaticAllocation && columns.some(column => !total(column).unknown && total(column).remaining > 0)" x-cloak class="border-b border-slate-100">
                        <th scope="row" class="sticky left-0 bg-white py-3 pr-3 text-left font-medium">Available business cash</th>
                        <template x-for="column in columns" :key="column.key">
                            <td class="px-3 py-3 tabular-nums" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'" x-text="total(column).unknown ? '—' : '$' + (total(column).remaining / 100).toFixed(2)"></td>
                        </template>
                    </tr>
                    <tr class="align-top">
                        <th scope="row" class="sticky left-0 z-10 bg-white py-4 pr-3 text-left font-semibold">Allocated</th>
                        <template x-for="column in columns" :key="column.key">
                            <td class="px-3 py-4" x-bind:class="mobileColumn === column.key ? '' : 'hidden md:table-cell'">
                                <div class="whitespace-nowrap font-semibold tabular-nums" x-text="total(column).unknown ? 'Costs or cost centres needed' : '$' + ((total(column).allocated + (automaticAllocation ? total(column).remaining : 0)) / 100).toFixed(2) + ' / $' + (total(column).net / 100).toFixed(2)"></div>
                                <div class="mt-2" x-show="total(column).missing">
                                    <x-ui.badge tone="warning"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i><span x-text="total(column).unknown ? 'Review costs and settings' : total(column).excessive ? 'Costs exceed price' : '$' + (total(column).remaining / 100).toFixed(2) + ' unallocated'"></span></x-ui.badge>
                                </div>
                            </td>
                        </template>
                    </tr>
                </tfoot>
            </x-ui.table>
        </div>
    </x-ui.collapsible-section>
</div>
