@php
    $inline = $inline ?? false;
    $allocationKey = 'workshop-'.$workshop->id;
    $values = $allocation['categories']->mapWithKeys(fn ($category) => [$category->id => number_format(($allocation['targets'][$category->id] ?? 0) / 100, 2, '.', '')])->all();
    $rules = json_decode($allocation['version']->rules, true);
    $suppliable = collect($rules)->filter(fn ($rule) => ($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour')->unique('category_id');
    $selected = $suppliable->mapWithKeys(fn ($rule) => [$rule['category_id'] => (bool) ($allocation['assumptions']['supplied_categories'][$rule['category_id']] ?? ((($rule['venue_default'] ?? false) || $rule['basis'] === 'venue_hour') && $allocation['assumptions']['venue_supplied']))])->all();
    $planner = app(\App\Services\Finance\FinancePlanner::class);
    $workshopDefaults = [
        'selected' => $inline ? old('workshop_allocations.'.$workshop->id.'.supplied_categories', $selected) : $selected,
        'supplied' => $planner->targets($rules, array_replace($allocation['assumptions'], ['supplied_categories' => array_fill_keys(array_keys($selected), true)])),
        'notSupplied' => $planner->targets($rules, array_replace($allocation['assumptions'], ['supplied_categories' => array_fill_keys(array_keys($selected), false)])),
    ];
    $planningTotal = $allocation['fundingLines']->isNotEmpty() ? max($allocation['total'], $allocation['invoicedFunding']) : $allocation['total'];
    $values = $inline ? old('workshop_allocations.'.$workshop->id.'.targets', $values) : $values;
    $enabled = $inline ? (bool) old('workshop_allocations.'.$workshop->id.'.override', $allocation['budget']->manual ?? false) : (bool) ($allocation['budget']->manual ?? false);
@endphp
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><h3 class="font-semibold">{{ $workshop->title }}</h3><p class="mt-1 text-sm text-slate-500" x-data="{ participants: @js($allocation['assumptions']['participants']), hours: @js($allocation['assumptions']['hours']) }" @if($inline) x-on:workshop-allocation-inputs.window="if ($event.detail.id === @js($workshop->id)) { participants = $event.detail.participants; hours = $event.detail.hours; }" @endif>{{ $allocation['version']->name }} · <span x-text="participants"></span> participants · <span x-text="hours"></span> hours</p></div>
    @unless($inline)<x-ui.badge :color="$state['current'] ? 'success' : 'gray'">{{ $state['status'] }}</x-ui.badge>@endunless
</div>
@unless($state['status'] === 'No allocation required')
    @if(!$inline && $allocation['fundingLines']->isNotEmpty())
        <div class="mb-5 border-b border-slate-200 pb-4 text-sm text-slate-600">
            <span>Funding:</span>
            @foreach($allocation['fundingLines']->unique('invoice_id') as $fundingLine)
                <a class="ml-2 text-primary-color underline underline-offset-2" href="{{ route('admin.invoice.edit', $fundingLine->invoice) }}">Invoice {{ $fundingLine->invoice->invoice_number }}</a>
                <span class="ml-1">{{ $fundingLine->invoice->billing_name }}</span>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('admin.workshop.allocation.store', $workshop) }}"
        @if($inline) data-workshop-allocation="{{ $workshop->id }}" data-allocation-ready="{{ $state['ready'] ? '1' : '0' }}" data-allocation-changed="{{ $state['ready'] && !$state['current'] ? '1' : '0' }}" @endif
        x-data="SM.allocationTally(@js(['workshopId' => $workshop->id, 'received' => $allocation['income']['net'], 'workshopInputs' => $allocation['assumptions'], 'workshopRules' => $rules, 'values' => $values, 'workshopDefaults' => $workshopDefaults, 'total' => $planningTotal, 'exact' => false, 'enabled' => $enabled]))"
        @if($inline) x-on:workshop-allocation-inputs.window="previewWorkshop($event.detail)" x-on:invoice-lines-updated.window="previewWorkshopFunding($event.detail)" @endif
        x-effect="@if($inline) $el.dataset.allocationChanged = {{ $state['ready'] && !$state['current'] ? 'true' : 'false' }} || allocationChanged ? '1' : '0'; @endif $dispatch('allocation-plan-updated', { key: '{{ $allocationKey }}', values: { ...values }, funding: total, inspect: {{ !$state['ready'] || ($allocation['budget']?->finalised_at && !$state['current']) ? 'true' : 'false' }} })">
        @csrf
        <input type="hidden" name="source_hash" value="{{ $state['hash'] }}">
        <input type="hidden" name="revision" value="{{ $allocation['budget'] ? hash('sha256', json_encode((array) $allocation['budget'])) : '' }}">
        <fieldset @if($inline && !$state['ready']) disabled @endif>
            @if($suppliable->isNotEmpty())
                <fieldset class="mb-6" x-on:change="refreshWorkshopDefaults()">
                    <legend class="mb-2 text-sm font-semibold">Supplied items</legend>
                    <x-finance.supplied-options :rules="$rules" :categories="$allocation['categories']" :values="$selected" model="supplied" />
                </fieldset>
            @endif
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">Allocation amounts (ex GST)</h3>
                @if($inline)
                    <input type="hidden" name="override" x-bind:value="enabled ? '1' : '0'">
                    <x-ui.button type="button" color="outline" class="inline-flex items-center gap-2" x-on:click="enabled = false; refreshWorkshopDefaults()" x-bind:disabled="!enabled"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Use defaults</x-ui.button>
                @else
                    <x-ui.checkbox :no-wrapper="true" name="override" value="1" label="Override defaults" x-model="enabled" x-on:change="refreshWorkshopDefaults()" />
                @endif
            </div>
            <x-finance.allocation-fields :categories="$allocation['categories']" prefix="targets" :id-prefix="$allocationKey.'-allocation'" :exact="false" :shortfall="true" :show-totals="false" :editable-defaults="$inline" />
        </fieldset>
        <div class="mt-4 grid gap-x-10 lg:grid-cols-2">
            <dl class="space-y-2 border-t border-slate-200 pt-4 text-sm lg:col-start-2" aria-live="polite">
                <div class="flex justify-between gap-4"><dt>{{ $allocation['fundingLines']->isNotEmpty() ? 'Invoiced funding (ex GST)' : 'Income (ex GST)' }}</dt><dd class="font-semibold tabular-nums" x-text="money(total)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Received (ex GST)</dt><dd class="text-right font-semibold tabular-nums">${{ number_format($allocation['income']['net'] / 100, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Allocation targets (ex GST)</dt><dd class="font-semibold tabular-nums" x-text="money(allocated)"></dd></div>
                <div class="flex justify-between gap-4" :class="remaining < 0 ? 'text-red-700' : (remaining === 0 ? 'text-emerald-700' : 'text-amber-700')"><dt x-text="remaining < 0 ? 'Shortfall (ex GST)' : 'Unallocated (ex GST)'"></dt><dd class="font-semibold tabular-nums" x-text="money(Math.abs(remaining))"></dd></div>
            </dl>
        </div>
        <div class="mt-6 flex flex-wrap items-center justify-end gap-4">
            @unless($state['ready'])
                <p class="text-sm text-slate-600">{{ $workshop->effectiveEndsAt()?->isFuture() ? 'Finalise after the workshop ends.' : 'Resolve draft invoices or outstanding ticket payment outcomes before finalising.' }}</p>
            @else
                @if($inline)<p class="text-sm text-slate-500">{{ $state['current'] ? 'Changes are saved with the invoice.' : 'Save the invoice to finalise this allocation plan.' }}</p>@endif
            @endunless
            @unless($inline)
                <x-ui.button type="submit" :disabled="!$state['ready'] || (bool) $state['current']" x-bind:disabled="{{ !$state['ready'] ? 'true' : ($state['current'] ? '!allocationChanged' : 'false') }}">{{ $state['current'] ? 'Update allocation' : 'Finalise allocation' }}</x-ui.button>
            @endunless
        </div>
    </form>
    @if($allocation['budget']?->finalised_at)<p class="mt-4 text-xs text-slate-500">Last finalised {{ $allocation['budget']->finalised_at }}. Previous revisions are retained.</p>@endif
@endunless
