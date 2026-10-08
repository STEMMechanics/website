@props([
    'expenses' => [],
    'supplier' => '',
    'expenseId' => '',
    'fieldPrefix' => '',
    'scope' => 'stock-receipt',
    'useOldValues' => true,
])

@php
    $supplierField = $fieldPrefix.'supplier';
    $expenseIdField = $fieldPrefix.'expense_id';
    $initialSupplier = $useOldValues ? old($supplierField, $supplier) : $supplier;
    $initialExpenseId = $useOldValues ? old($expenseIdField, $expenseId) : $expenseId;
@endphp

<div
    x-id="['stock-receipt-supplier', 'stock-receipt-expenses', 'stock-receipt-supplier-help']"
    x-data="SM.stockReceiptExpenseEditor(@js($initialSupplier), @js($initialExpenseId), @js($expenses), @js($scope))"
    x-on:keydown.escape.window="open = false"
    x-on:resize.window="if (open) position($refs.expenseTrigger)"
    x-on:scroll.window="if (open) position($refs.expenseTrigger)"
>
    <label class="block text-sm pl-1" x-bind:for="$id('stock-receipt-supplier')">Supplier or expense</label>
    <div class="relative mt-1 flex items-center">
        <x-ui.input-control
            x-bind:id="$id('stock-receipt-supplier')"
            type="text"
            class="h-11 pr-20!"
            x-show="!linkedExpense"
            x-bind:value="supplier"
            autocomplete="off"
            placeholder="Enter a supplier or link an expense"
            x-bind:aria-describedby="$id('stock-receipt-supplier-help')"
            x-on:input="editSupplier($event.target.value)"
        />
        <button
            x-cloak
            x-show="!linkedExpense && supplier"
            type="button"
            class="absolute right-10 flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            aria-label="Clear supplier"
            title="Clear supplier"
            x-on:click="clearValue()"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div x-cloak x-show="linkedExpense" class="flex h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 py-1 pl-3 pr-1.5 text-sm text-slate-800" x-bind:title="linkedExpense?.summary">
            <i class="fa-solid fa-link shrink-0 text-xs text-sky-600" aria-hidden="true"></i>
            <span class="min-w-0 flex-1 truncate leading-5" x-text="linkedExpense?.summary"></span>
            <button type="button" class="flex size-8 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-slate-900" aria-label="Change linked expense" title="Change linked expense" x-on:click="browse($el)">
                <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
            </button>
            <button type="button" class="flex size-8 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-slate-900" aria-label="Clear supplier or expense" title="Clear supplier or expense" x-on:click="clearValue()">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <x-ui.button
            x-show="!linkedExpense"
            type="button"
            variant="plain"
            class="absolute right-0 size-11 p-0! text-slate-500"
            x-ref="expenseTrigger"
            aria-label="Search and link an expense"
            x-bind:title="linkedExpense ? 'Linked to ' + linkedExpense.label : 'Search and link an expense'"
            x-bind:aria-expanded="open"
            x-on:click="browse($el)"
        >
            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
        </x-ui.button>
        <input type="hidden" name="{{ $supplierField }}" x-bind:value="supplier">
        <input type="hidden" name="{{ $expenseIdField }}" x-bind:value="expenseId">
    </div>
    <p x-bind:id="$id('stock-receipt-supplier-help')" class="mt-1 text-xs text-slate-500">Enter a supplier, or search saved expenses by number, supplier or description.</p>
    @error($supplierField)
        <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @enderror
    @error($expenseIdField)
        <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @enderror

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-on:click.outside="open = false"
            class="fixed z-50 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
            x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px' }"
        >
                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Saved expenses</p>
            <x-ui.input-control
                aria-label="Find an expense to link"
                placeholder="Search by supplier, description or expense number"
                x-model="query"
                x-on:input="selected = 0"
                x-on:keydown.arrow-down.prevent.stop="move(1)"
                x-on:keydown.arrow-up.prevent.stop="move(-1)"
                x-on:keydown.enter.prevent.stop="if (matches[selected]) choose(matches[selected])"
            />
            <div class="mt-2 max-h-64 overflow-y-auto" role="listbox" x-bind:id="$id('stock-receipt-expenses')">
                <template x-for="(expense, expenseIndex) in matches" :key="expense.id">
                    <button
                        type="button"
                        role="option"
                        class="block w-full rounded-lg px-3 py-2 text-left"
                        x-bind:class="expenseIndex === selected ? 'bg-sky-50 text-sky-800' : 'text-slate-700 hover:bg-slate-50'"
                        x-bind:aria-selected="expenseIndex === selected"
                        x-on:mouseenter="selected = expenseIndex"
                        x-on:click="choose(expense)"
                    >
                        <span class="block text-sm font-medium" x-text="expense.label"></span>
                        <span class="mt-0.5 block text-xs text-slate-500" x-text="expense.detail"></span>
                    </button>
                </template>
                <p x-show="!matches.length" class="px-3 py-2 text-sm text-slate-500">No matching expenses in the recent list. You can enter a supplier without linking an expense.</p>
            </div>
            <button
                type="button"
                class="mt-2 w-full rounded-lg border-t border-slate-100 px-3 pt-2 text-left text-xs font-semibold text-slate-600 hover:text-slate-900"
                x-show="expenseId"
                x-on:click="choose(null)"
            >
                Remove expense link
            </button>
        </div>
    </template>
</div>
