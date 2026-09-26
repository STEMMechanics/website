@props(['expense' => null])
@php
    $defaults = \App\Models\Supplier::get(['supplier', 'splits'])->mapWithKeys(fn ($supplier) => [mb_strtolower(trim($supplier->supplier)) => $supplier->splits])->all();
    $allocationCategories = \Illuminate\Support\Facades\DB::table('finance_categories')->where('kind', 'cost')->orderBy('priority')->get();
    $manual = $expense ? \Illuminate\Support\Facades\DB::table('finance_expense_splits')->where('expense_id', $expense->id)->get() : collect();
    $splits = $manual->isNotEmpty() ? $manual->pluck('cents', 'category_id')->all() : ($expense ? app(\App\Services\Finance\FinancePlanner::class)->expenseSplits($expense) : []);
    $values = $allocationCategories->mapWithKeys(fn ($category) => [$category->id => number_format(($splits[$category->id] ?? 0) / 100, 2, '.', '')])->all();
    $oldValues = old('splits', $values);
    $values = is_array($oldValues) ? collect($values)->map(fn ($value, $id) => is_scalar($oldValues[$id] ?? null) ? (string) $oldValues[$id] : $value)->all() : $values;
    $enabled = (bool) old('allocation_override', $manual->isNotEmpty());
@endphp
<section data-validation-field="splits" class="my-6 rounded-xl border border-slate-200 bg-white p-5" x-data="SM.allocationTally(@js(['values' => $values, 'enabled' => $enabled, 'exact' => true, 'totalInput' => 'expense-total-amount', 'gstInput' => 'expense-gst-amount', 'supplierInput' => 'expense-supplier', 'defaults' => $defaults]))" x-on:input.window="refreshTotal($event)" x-effect="if (!enabled) refreshDefaults()">
    <div x-data="{ allocationOpen: @js($errors->has('splits')) }" x-effect="if (enabled && !valid) allocationOpen = true">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 text-left"
            x-on:click="allocationOpen = !allocationOpen"
            x-bind:aria-expanded="allocationOpen"
            aria-controls="expense-allocation-content"
        >
            <span class="flex min-w-0 items-center gap-3">
                <i class="fa-solid fa-chevron-right shrink-0 text-sm text-slate-500 transition-transform" x-bind:class="allocationOpen ? 'rotate-90' : ''" aria-hidden="true"></i>
                <span class="min-w-0">
                    <span class="block text-lg font-semibold">Cost centre allocation</span>
                    <span class="mt-1 block text-sm text-slate-600" aria-live="polite">
                        <span x-text="money(allocated)"></span> of <span x-text="money(total)"></span> allocated (excluding GST)
                    </span>
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span x-show="remaining > 0" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800" role="status">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Unallocated <span x-text="money(remaining)"></span>
                </span>
                <span x-show="remaining < 0" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800" role="status">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Over by <span x-text="money(Math.abs(remaining))"></span>
                </span>
                <span x-show="remaining === 0" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Balanced
                </span>
            </span>
        </button>

        <div id="expense-allocation-content" x-show="allocationOpen" x-cloak class="mt-5 border-t border-slate-200 pt-4">
            <input type="hidden" name="allocation_editor" value="1">
            <x-ui.checkbox name="allocation_override" value="1" label="Set an allocation for this expense" x-model="enabled" :checked="$enabled" />
            @if(!$expense)
                <p class="mt-2 text-sm text-gray-500">For a new supplier, this allocation is also saved as its default percentages for future expenses.</p>
            @endif
            <p data-validation-error id="expense-splits-error" role="alert" class="my-3 text-sm text-red-700" @if(!$errors->has('splits')) hidden @endif>{{ $errors->first('splits') }}</p>
            <div class="mt-4">
                <x-finance.allocation-fields :categories="$allocationCategories" />
            </div>
        </div>
    </div>
</section>
