<x-layout>
@php($latest = $templateVersion ?? $versions->first())
@php($editorTitle = $editing ? 'Edit allocation plan: '.$latest->name : (request()->filled('template_id') ? 'Copy allocation plan: '.$latest->name : 'Create allocation plan'))
<x-mast :title="$editorTitle" />
<x-container class="py-5">
@php($defaultVersionId = $defaultVersionId ?? \Illuminate\Support\Facades\DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id'))
@php($rules = array_values(array_filter($planner->decode($latest->rules), fn ($rule) => $categories->where('active', true)->contains('id', $rule['category_id']))))
@php($prices = $planner->decode($latest->prices))

    <form method="POST" action="{{ route('admin.finance.pricing') }}" x-data="SM.allocationPlanPreview(@js($rules))" x-init="$nextTick(() => refresh($el))" x-on:input="$nextTick(() => refresh($el))" x-on:focusout="$nextTick(() => refresh($el))" x-on:change="$nextTick(() => refresh($el))" data-record-form data-record-wide data-record-title="{{ $editorTitle }}">@csrf
        <div data-record-body>
    @if($editing)<input type="hidden" name="edit_id" value="{{ $latest->id }}">@endif
        <input type="hidden" name="return_to" value="versions">
        <input type="hidden" name="clean_prices" value="1">
        <input type="hidden" name="travel_free_minutes" value="{{ $prices['travel_free_minutes'] ?? 30 }}">
        <div class="grid gap-x-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <x-ui.input name="name" label="Plan name" :value="$editing ? $latest->name : ''" placeholder="2026-27" required />
        <div class="mb-4 flex h-11 items-center"><x-ui.checkbox :noWrapper="true" :disabled="$editing && $latest->archived" name="make_default" value="1" label="Set as default" :checked="$editing && (int) $defaultVersionId === (int) $latest->id" /></div>
        </div>
        <div class="space-y-5">
            <x-finance.panel title="Cost centre allocations">
                <x-ui.table variant="listing" mobileCards>
                    <thead><tr><th class="w-2/5 min-w-44">Cost centre</th><th class="min-w-48">Basis</th><th class="w-32 text-center">Amount <span class="whitespace-nowrap">(ex GST)</span></th><th class="w-28 text-center">Suppliable</th><th class="w-12"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        <template x-for="(rule, index) in costRules" :key="rule.id">
                            <tr x-bind:data-cost-rule="rule.id">
                                <td data-label="Cost centre" data-mobile-primary>
                                    <x-ui.select x-bind:name="'rules[' + index + '][category_id]'" label="Cost centre" :noLabel="true" x-model="rule.category_id" required>
                                        <option value="" disabled>Choose cost centre</option>
                                        @foreach($categories->where('active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                                    </x-ui.select>
                                </td>
                                <td data-label="Basis" data-mobile-wide>
                                    <x-ui.select x-bind:name="'rules[' + index + '][basis]'" label="Basis" :noLabel="true" x-model="rule.basis">
                                        <option value="workshop">Per workshop</option><option value="participant">Per participant</option><option value="hour">Per delivery hour</option><option value="travel">Per 15 minutes of travel</option>
                                    </x-ui.select>
                                </td>
                                <td data-label="Amount (ex GST)"><div class="w-28 max-w-full"><x-ui.money-input x-bind:name="'rules[' + index + '][rate]'" label="Amount" :noLabel="true" x-model="rule.rate" required /></div></td>
                                <td data-label="Suppliable" class="text-center">
                                    <input type="hidden" x-bind:name="'rules[' + index + '][venue_default]'" x-bind:value="rule.venue_default ? 1 : 0">
                                    <x-ui.checkbox x-bind:name="'rules[' + index + '][suppliable]'" value="1" label="Suppliable" :labelHidden="true" :noWrapper="true" x-model="rule.suppliable" class="mb-4 justify-center" />
                                </td>
                                <td data-mobile-actions><x-ui.row-action label="Remove cost allocation" icon="fa-trash" tone="danger" x-on:click="removeCostRule(rule.id, $el.closest('form'))" x-bind:disabled="costRules.length &lt;= 1" /></td>
                            </tr>
                        </template>
                    </tbody>
                </x-ui.table>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="min-w-48 flex-1 text-sm text-slate-600">Use another allocation for a new cost centre or a second charging basis. Set an amount to zero to omit a cost.</p>
                    <x-ui.button color="outline" size="small" icon="plus" x-on:click="addCostRule($el.closest('form'))" x-bind:disabled="costRules.length &gt;= 100">Add cost allocation</x-ui.button>
                </div>
            </x-finance.panel>
            <x-finance.plan-preview :pricing-participants="$prices['pricing_participants'] ?? 10" :has-legacy-increments="($prices['rounding_step'] ?? 0) > 0 || ($prices['travel_rounding_step'] ?? 0) > 0" />
        </div>
        </div>
        <footer class="sm-dialog-footer sm-plan-preview-footer" data-record-footer data-plan-summary>
            <div x-show="preview" class="flex min-w-0 flex-1 flex-wrap gap-x-10 gap-y-2" x-cloak>
                <div class="min-w-0">
                    <p class="text-xs text-slate-600">Workshop rate / hr / participant</p>
                    <p class="flex gap-3 text-sm">
                        <span class="whitespace-nowrap"><strong class="tabular-nums" x-text="money(preview?.workshopRounding.suggested.unitEx)"></strong> <span class="text-xs">ex GST</span></span>
                        <span class="whitespace-nowrap text-slate-600"><span class="tabular-nums" x-text="money(preview?.workshopRounding.suggested.unitInc)"></span> <span class="text-xs">inc GST</span></span>
                    </p>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-600">Actual cost / participant</p>
                    <p class="flex gap-3 text-sm">
                        <span class="whitespace-nowrap"><strong class="tabular-nums" x-text="approximateMoney(preview?.costPerParticipant)"></strong> <span class="text-xs">ex GST</span></span>
                        <span class="whitespace-nowrap text-slate-600"><span class="tabular-nums" x-text="approximateMoney(preview?.costPerParticipant * (1 + SM.currentGstRate()))"></span> <span class="text-xs">inc GST</span></span>
                    </p>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-600">Example quote total</p>
                    <p class="text-sm"><strong class="tabular-nums" x-text="money(preview?.cleanInvoiceGross)"></strong> <span class="whitespace-nowrap text-xs">inc GST</span></p>
                    <p x-show="preview?.roundingIncrease" class="text-xs text-amber-800" x-text="'Includes ' + money(preview?.roundingIncrease) + ' GST cent adjustment'"></p>
                </div>
            </div>
            <p x-show="!preview" class="text-sm text-slate-600">Enter valid preview quantities to see costs.</p>
            <x-ui.button type="submit" class="ml-auto shrink-0 px-5 py-2">{{ $editing ? 'Save' : 'Create' }}</x-ui.button>
        </footer>
    </form>


</x-container>
</x-layout>
