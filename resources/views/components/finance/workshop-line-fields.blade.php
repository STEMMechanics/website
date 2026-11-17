@props(['inclusive' => false, 'invoiceLayout' => false, 'isLocked' => false, 'pricingPlans' => []])
@php
    $supplyRules = collect($pricingPlans)
        ->flatMap(fn ($plan) => collect($plan['pricing']['rules'] ?? []))
        ->filter(fn ($rule) => ($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour')
        ->unique(fn ($rule) => (string) ($rule['category_id'] ?? ''))
        ->values();
@endphp
<div class="col-span-full space-y-3" x-data="{ priceInclusive: @js($inclusive), get planPricing() { return this.selectedPricingPlan?.pricing ?? null; }, hasPlanCategory(categoryId, kind) { return (this.planPricing?.rules || []).some(rule => Number(rule.category_id) === Number(categoryId) &amp;&amp; (rule.suppliable || rule.basis === 'venue_hour') &amp;&amp; (kind === 'travel' ? rule.basis === 'travel' : rule.basis !== 'travel')); }, hasSuppliedCategories(kind) { return (this.planPricing?.rules || []).some(rule => kind === 'travel' ? rule.basis === 'travel' &amp;&amp; rule.suppliable : rule.basis !== 'travel' &amp;&amp; (rule.suppliable || rule.basis === 'venue_hour')); } }" x-init="if (item.kind === 'travel') SM.hydrateTravelLine(item); if (item.kind === 'multi_travel') SM.hydrateMultiTravelLine(item); if (planPricing) { SM.initializePricingSupplies(item, planPricing); SM.registerLinePlan(item, planPricing, priceInclusive); } if (['multi_workshop', 'multi_travel'].includes(item.kind)) SM.updateWorkshopLine(item)" x-effect="if (planPricing) { SM.initializePricingSupplies(item, planPricing); SM.registerLinePlan(item, planPricing, priceInclusive); }">
    <x-finance.multi-workshop-fields :supply-rules="$supplyRules" :invoice-layout="$invoiceLayout" :is-locked="$isLocked" />
    <x-finance.multi-travel-fields :supply-rules="$supplyRules" :invoice-layout="$invoiceLayout" :is-locked="$isLocked" />
    <div x-show="!['multi_workshop', 'multi_travel'].includes(item.kind)" class="flex flex-wrap items-end gap-3">
        <label x-show="item.kind === 'workshop'" class="w-24 text-sm">Hours
            <x-ui.input-control class="mt-1 h-11" type="number" min="0.01" max="24" step="0.01" x-model="item.workshop_hours" x-bind:required="item.kind === 'workshop' &amp;&amp; (!item.legacy_workshop || !!item.workshop_seats)" x-on:input="SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
        </label>
        <div x-show="item.kind === 'workshop'" class="w-32 text-sm"><span class="mb-1 block">Seats</span>
            <x-finance.workshop-seats-field />
        </div>
        <div x-show="item.kind === 'travel'" class="w-64 text-sm"><span class="mb-1 block">Workshop or location</span>
            <x-finance.workshop-funding-fields model="item" :billing-locked="$isLocked" :travel-row="true" />
        </div>
        <label x-show="item.kind === 'travel'" class="w-40 text-sm">Travel hours
            <x-ui.input-control class="mt-1 h-11" type="number" min="0" max="2500" step="0.25" x-model="item.travel_hours" x-on:input="item.travel_hours_user_changed = true; item.travel_hours_defaulted = false; item.details_json.travel ??= {}; item.details_json.travel.hours_user_changed = true; item.details_json.travel.hours_defaulted = false; SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
        </label>
    @if($supplyRules->isNotEmpty() || isset($options))
        <div x-cloak x-show="(item.kind === 'travel' && hasSuppliedCategories('travel')) || (item.kind === 'workshop' && hasSuppliedCategories('workshop'))" class="flex min-h-11 min-w-48 flex-1 flex-wrap items-center gap-x-5 gap-y-2">
            @isset($options){{ $options }}@endisset
            @foreach($supplyRules as $rule)
                <div class="shrink-0" x-show="item.kind === 'travel' ? hasPlanCategory(@js($rule['category_id']), 'travel') : (item.kind === 'workshop' &amp;&amp; hasPlanCategory(@js($rule['category_id']), 'workshop'))">
                    <x-ui.checkbox :small="true" :noWrapper="true" :label="($rule['category_name'] ?? 'Cost centre').' supplied'" x-model="item.supplied_categories['{{ $rule['category_id'] }}']" x-on:change="SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
                </div>
            @endforeach
        </div>
    @endif
    </div>
    @if(trim((string) $slot) !== '')
    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))] items-end gap-4 [&>label]:w-auto [&>label]:min-w-0 [&>dl]:min-w-0">{{ $slot }}</div>
    @endif
</div>
