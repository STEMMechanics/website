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
<section class="my-6 rounded-xl border border-slate-200 bg-white p-5" x-data="SM.allocationTally(@js(['values' => $values, 'enabled' => $enabled, 'exact' => true, 'totalInput' => 'expense-total-amount', 'gstInput' => 'expense-gst-amount', 'supplierInput' => 'expense-supplier', 'defaults' => $defaults]))" x-on:input.window="refreshTotal($event)" x-effect="if (!enabled) refreshDefaults()">
    <h2 class="mb-4 text-lg font-semibold">Cost centre allocation</h2>
    <input type="hidden" name="allocation_editor" value="1">
    <x-ui.checkbox name="allocation_override" value="1" label="Set an allocation for this expense" x-model="enabled" :checked="$enabled" />
    @error('splits')<p role="alert" class="my-3 text-sm text-red-700">{{ $message }}</p>@enderror
    <div class="mt-4">
        <x-finance.allocation-fields :categories="$allocationCategories" />
    </div>
</section>
