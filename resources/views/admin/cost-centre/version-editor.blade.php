<x-layout>
<x-mast :title="$editing ? 'Edit allocation plan' : 'Create allocation plan'" />
<x-container class="py-5">
@php($defaultVersionId = $defaultVersionId ?? \Illuminate\Support\Facades\DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id'))
@php($latest = $templateVersion ?? $versions->first())
@php($rules = array_values(array_filter($planner->decode($latest->rules), fn ($rule) => $categories->where('active', true)->contains('id', $rule['category_id']))))
@php($prices = $planner->decode($latest->prices))

    <form method="POST" action="{{ route('admin.finance.pricing') }}" x-data="SM.allocationPlanPreview()" x-init="$nextTick(() => refresh($el))" x-on:focusout="$nextTick(() => refresh($el))" x-on:change="$nextTick(() => refresh($el))" data-record-form data-record-wide>@csrf
    @if($editing)<input type="hidden" name="edit_id" value="{{ $latest->id }}">@endif
        <input type="hidden" name="return_to" value="versions">
        <input type="hidden" name="travel_free_minutes" value="{{ $prices['travel_free_minutes'] ?? 30 }}">
        <div class="grid gap-x-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <x-ui.input name="name" label="Plan name" :value="$editing ? $latest->name : ''" placeholder="2026-27" required />
        <div class="mb-4 flex h-11 items-center"><x-ui.checkbox :noWrapper="true" :disabled="$editing && $latest->archived" name="make_default" value="1" label="Set as default" :checked="$editing && (int) $defaultVersionId === (int) $latest->id" /></div>
        </div>
        <div class="space-y-5">
        <x-finance.panel title="Cost centre allocations">
        <x-ui.table variant="listing"><thead><tr><th class="w-2/5 min-w-48">Category</th><th class="min-w-52">Basis</th><th class="w-32 text-center">Amount <span class="whitespace-nowrap">(ex GST)</span></th><th class="w-24 text-center">Suppliable</th></tr></thead><tbody>
            @foreach($rules as $key => $rule)<tr><td><x-ui.select :name="'rules['.$key.'][category_id]'" label="Category" :noLabel="true">@foreach($categories->where('active', true) as $category)<option value="{{ $category->id }}" @selected($category->id === $rule['category_id'])>{{ $category->name }}</option>@endforeach</x-ui.select></td><td><x-ui.select :name="'rules['.$key.'][basis]'" label="Basis" :noLabel="true">@foreach(['workshop' => 'Per workshop', 'participant' => 'Per participant', 'hour' => 'Per delivery hour', 'travel' => 'Per billable travel unit'] as $value => $label)<option value="{{ $value }}" @selected($value === ($rule['basis'] === 'venue_hour' ? 'hour' : $rule['basis']))>{{ $label }}</option>@endforeach</x-ui.select></td><td class="w-32"><div class="w-28"><x-ui.money-input :name="'rules['.$key.'][rate]'" label="Amount" :noLabel="true" :value="$rule['rate_cents'] / 100" required /></div></td><td class="text-center"><input type="hidden" name="rules[{{ $key }}][venue_default]" value="{{ ($rule['venue_default'] ?? false) || $rule['basis'] === 'venue_hour' ? 1 : 0 }}"><x-ui.checkbox :name="'rules['.$key.'][suppliable]'" value="1" label="Suppliable" :labelHidden="true" :noWrapper="true" :checked="($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour'" class="mb-4 justify-center" /></td></tr>@endforeach
        </tbody></x-ui.table>
        <template x-for="i in extra" :key="i"><div class="mt-4 grid gap-x-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_7rem_6rem]">
            <x-ui.select x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][category_id]'" label="Extra category">@foreach($categories->where('active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select>
            <x-ui.select x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][basis]'" label="Basis"><option value="workshop">Per workshop</option><option value="participant">Per participant</option><option value="hour">Per delivery hour</option><option value="travel">Per billable travel unit</option></x-ui.select>
            <x-ui.money-input x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][rate]'" label="Amount" value="0.00" required />
            <x-ui.checkbox x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][suppliable]'" value="1" label="Suppliable" />
        </div></template>
        <x-ui.button color="outline" class="my-4" x-on:click="extra++">Add cost rule</x-ui.button>
        <p class="text-sm text-slate-600">Set an amount to zero to omit a cost. Each billable travel unit is 15 minutes.</p>
        </x-finance.panel>
        <div class="grid gap-5 lg:grid-cols-2">
            <x-finance.panel title="Workshop pricing">
                <x-ui.input name="pricing_participants" label="Pricing participants" type="number" min="1" max="10000" :value="$prices['pricing_participants'] ?? 10" info="Ticket pricing uses this figure, or Max tickets when it is lower. Duration still applies to hourly costs." />
                <x-ui.select name="rounding_step" label="Round price up (including GST)">
                    @foreach([0 => 'No rounding', 10 => 'Next $0.10', 50 => 'Next $0.50', 100 => 'Next $1', 500 => 'Next $5'] as $step => $label)
                        <option value="{{ $step }}" @selected(($prices['rounding_step'] ?? 0) == $step)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-finance.panel>
            <x-finance.panel title="Travel pricing">
                <x-ui.money-input name="travel_price" label="Calculated travel price (including GST, before rounding)" readonly x-bind:value="travelBase" :value="array_sum(array_column(array_filter($rules, fn ($rule) => $rule['basis'] === 'travel'), 'rate_cents')) * 1.1 / 100" />
                <x-ui.select name="travel_rounding_step" label="Round travel price up (including GST)">
                    @foreach([0 => 'No rounding', 10 => 'Next $0.10', 50 => 'Next $0.50', 100 => 'Next $1', 500 => 'Next $5'] as $step => $label)
                        <option value="{{ $step }}" @selected(($prices['travel_rounding_step'] ?? 0) == $step)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-finance.panel>
        </div>
        <x-ui.select name="rounding_category_id" label="Allocate rounding differences to" info="Applies to both workshop and travel pricing.">
            <option value="">Leave unallocated</option>
            @foreach($categories->where('active', true) as $category)
                <option value="{{ $category->id }}" @selected(($prices['rounding_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-ui.select>
        </div>
        <x-finance.plan-preview />
        <x-finance.save>{{ $editing ? 'Save' : 'Create' }}</x-finance.save>
    </form>


</x-container>
</x-layout>
