@props(['invoice', 'allocation', 'inline' => true, 'totalLabel' => 'Invoice total excluding GST'])
@php
    $calculatorPlans = \Illuminate\Support\Facades\DB::table('finance_pricing_versions')->where('archived', false)->where('is_snapshot', false)->orderByDesc('effective_from')->orderByDesc('id')->get()
        ->map(fn ($plan) => ['id' => $plan->id, 'name' => $plan->name, 'rules' => json_decode($plan->rules, true), 'participants' => json_decode($plan->prices, true)['pricing_participants'] ?? 10]);
    $calculatorId = 'invoice-allocation-calculator-'.$invoice->id.($inline ? '-inline' : '-editor');
    $calculatorConfig = ['dialogId' => $calculatorId, 'plans' => $calculatorPlans, 'planId' => $calculatorPlans->contains('id', $allocation['version']->id) ? $allocation['version']->id : $calculatorPlans->first()['id'] ?? '', 'categories' => $allocation['categories']->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])];
@endphp
<div x-data="SM.invoiceAllocationCalculator(@js($calculatorConfig))" x-on:open-allocation-calculator.window="if ($event.detail.dialogId === dialogId) launch($el.closest('form') ? Alpine.$data($el.closest('form')).total : 0)">
    <x-ui.list-dialog :id="$calculatorId" title="Calculate cost centre allocation" kind="bulk">
        <x-slot:headerActions>
            <x-ui.button type="button" variant="plain" x-on:click="minimise()" aria-label="Minimise allocation calculator" title="Minimise" class="h-11 w-11 rounded-lg p-0! text-slate-500"><i class="fa-solid fa-minus" aria-hidden="true"></i></x-ui.button>
        </x-slot:headerActions>
        <div class="space-y-6 p-5" x-on:keydown.enter="if ($event.target.matches('input')) $event.preventDefault()">
            <fieldset x-bind:disabled="!isOpen" class="space-y-5" x-on:input="recalculate()" x-on:change="recalculate()">
                <div>
                    <label for="{{ $calculatorId }}-plan" class="block text-sm font-medium">Allocation plan</label>
                    <select id="{{ $calculatorId }}-plan" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" x-model="planId">
                        <template x-for="plan in plans" :key="plan.id"><option :value="plan.id" x-text="plan.name"></option></template>
                    </select>
                </div>
                <div class="space-y-5">
                    <template x-for="(row, index) in rows" :key="row.id">
                        <section>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold" x-text="'Workshop group ' + (index + 1)"></h3>
                                <x-ui.button type="button" variant="plain" x-on:click="removeRow(row.id)" x-bind:disabled="rows.length === 1" x-bind:aria-label="'Remove workshop group ' + (index + 1)" class="size-9 p-0!"><i class="fa-solid fa-trash" aria-hidden="true"></i></x-ui.button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <label class="flex flex-col justify-end text-sm">Workshops<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="number" min="1" max="1000" step="1" x-model="row.count"></label>
                                <label class="flex flex-col justify-end text-sm">Hours / workshop<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="number" min="0.01" max="24" step="0.01" x-model="row.hours"></label>
                                <label class="flex flex-col justify-end text-sm">Participants / workshop<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="number" min="1" max="10000" step="1" x-model="row.participants"></label>
                                <label class="flex flex-col justify-end text-sm">Billable travel hours / workshop<input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" type="number" min="0" max="2500" step="0.25" x-model="row.travel"></label>
                            </div>
                            <div x-show="supplies.length" class="mt-3 flex flex-wrap gap-4">
                                <template x-for="category in supplies" :key="category.id">
                                    <label class="flex items-center gap-2 text-sm"><x-ui.checkbox :bare="true" x-model="row.supplied[category.id]" /><span x-text="category.name + ' supplied'"></span></label>
                                </template>
                            </div>
                        </section>
                    </template>
                </div>
                <x-ui.button type="button" color="outline" x-on:click="addRow()" x-bind:disabled="rows.length >= 50"><i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>Add workshop group</x-ui.button>
            </fieldset>
            <fieldset x-bind:disabled="!isOpen">
                <legend class="mb-4 text-sm font-semibold">Cost centre amounts <span class="font-normal">(excluding GST)</span></legend>
                <p x-show="!inputsValid" class="mb-3 text-sm text-amber-700">Choose a plan and enter valid workshop details.</p>
                <x-finance.allocation-fields :categories="$allocation['categories']" prefix="calculator_targets" :id-prefix="$calculatorId.'-amount'" :exact="false" :shortfall="true" :total-label="$totalLabel" />
            </fieldset>
        </div>
        <div class="sm-dialog-footer">
            <x-ui.button type="button" color="outline" data-close-dialog>Cancel</x-ui.button>
            <x-ui.button type="button" x-on:click="apply()" x-bind:disabled="!canApply">Apply to invoice</x-ui.button>
        </div>
    </x-ui.list-dialog>
    <template x-teleport="body">
        <div x-cloak x-show="minimised" class="fixed inset-x-3 bottom-3 z-50 flex items-center rounded-xl border border-slate-300 bg-white shadow-lg sm:left-auto sm:right-5 sm:w-80">
            <button type="button" x-ref="restoreCalculator" x-on:click="$dispatch('open-allocation-calculator', { dialogId })" aria-label="Restore allocation calculator" aria-haspopup="dialog" aria-controls="{{ $calculatorId }}" class="flex min-h-12 min-w-0 flex-1 items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-primary-color">
                <i class="fa-solid fa-calculator text-primary-color" aria-hidden="true"></i>
                <span class="flex-1">Allocation calculator</span>
                <i class="fa-solid fa-window-restore text-slate-500" aria-hidden="true"></i>
            </button>
        </div>
    </template>
</div>
