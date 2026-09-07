@props(['inclusive' => false])
@php
    $plan = \App\Services\Finance\PricingVersion::forDate(today()->toDateString());
    $supplyCategories = \Illuminate\Support\Facades\DB::table('finance_categories')->pluck('name', 'id');
    $supplyRules = collect(json_decode($plan->rules, true))->filter(fn ($rule) => ($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour')->unique('category_id');
    $planPricing = array_merge(json_decode($plan->prices, true), ['rules' => json_decode($plan->rules, true)]);
@endphp
<div class="col-span-full space-y-3" x-data="{ planPricing: @js($planPricing), priceInclusive: @js($inclusive) }" x-init="if (item.kind === 'travel') SM.hydrateTravelLine(item); SM.registerLinePlan(item, planPricing, priceInclusive); if (item.kind === 'multi_workshop') SM.updateWorkshopLine(item)">
    <x-finance.multi-workshop-fields :supply-rules="$supplyRules" :supply-categories="$supplyCategories" />
    <div x-show="item.kind !== 'multi_workshop'" class="flex flex-wrap items-end gap-3">
        <label x-show="item.kind === 'workshop'" class="w-24 text-sm">Hours
            <x-ui.input-control class="mt-1 h-11" type="number" min="0.01" max="24" step="0.01" x-model="item.workshop_hours" x-bind:required="item.kind === 'workshop' &amp;&amp; (!item.legacy_workshop || !!item.workshop_seats)" x-on:input="SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
        </label>
        <label x-show="item.kind === 'workshop'" class="w-24 text-sm">Seats
            <x-ui.input-control class="mt-1 h-11" type="number" min="1" max="10000" step="1" x-model="item.workshop_seats" x-bind:required="item.kind === 'workshop' &amp;&amp; (!item.legacy_workshop || !!item.workshop_hours)" x-on:input="SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
        </label>
        <label x-show="item.kind === 'travel'" class="w-40 text-sm">Travel hours
            <x-ui.input-control class="mt-1 h-11" type="number" min="0" max="2500" step="0.25" x-model="item.travel_hours" x-on:input="SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
        </label>
    @if($supplyRules->isNotEmpty() || isset($options))
        <div class="flex min-h-11 min-w-48 flex-1 flex-wrap items-center gap-x-5 gap-y-2">
            @isset($options){{ $options }}@endisset
            @foreach($supplyRules as $rule)
                <div class="shrink-0" x-show="item.kind === '{{ $rule['basis'] === 'travel' ? 'travel' : 'workshop' }}'" x-init="item.supplied_categories ??= {}; item.supplied_categories['{{ $rule['category_id'] }}'] ??= {{ ($rule['venue_default'] ?? false) || $rule['basis'] === 'venue_hour' ? '!!item.venue_supplied' : 'false' }}">
                    <x-ui.checkbox :small="true" :noWrapper="true" :label="($supplyCategories[$rule['category_id']] ?? 'Cost centre').' supplied'" x-model="item.supplied_categories['{{ $rule['category_id'] }}']" x-on:change="SM.updateWorkshopLine(item, planPricing, priceInclusive); serializeLineItems()" />
                </div>
            @endforeach
        </div>
    @endif
    </div>
    @if(trim((string) $slot) !== '')
    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))] items-end gap-4 [&>label]:w-auto [&>label]:min-w-0 [&>dl]:min-w-0">{{ $slot }}</div>
    @endif
</div>
