@php
    $plan = \App\Services\Finance\PricingVersion::forDate(today()->toDateString());
    $categories = \Illuminate\Support\Facades\DB::table('finance_categories')->orderBy('priority')->get();
@endphp
<x-finance.panel title="Estimated cost centre allocation">
    <div x-data="{ allocationRules: @js(json_decode($plan->rules, true)) }">
        <p class="text-sm text-slate-500" x-show="lineItems.length === 0">Add line items, then save the invoice to calculate cost centre allocations. Workshop and travel estimates appear here before saving.</p>
        <p class="text-sm text-slate-500" x-show="lineItems.length > 0 && !Object.values(SM.lineCostAllocations(lineItems, allocationRules)).some(amount => amount > 0)" x-cloak>Save the invoice to calculate product and other cost centre allocations. Workshop and travel estimates appear here when their details are complete.</p>
        <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            @foreach($categories as $category)
                <div class="flex justify-between gap-3" x-show="(SM.lineCostAllocations(lineItems, allocationRules)['{{ $category->id }}'] || 0) > 0">
                    <dt>{{ $category->name }}</dt><dd class="tabular-nums" x-text="'$' + ((SM.lineCostAllocations(lineItems, allocationRules)['{{ $category->id }}'] || 0) / 100).toFixed(2)"></dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-3 text-xs text-slate-500">Excluding GST, using {{ $plan->name }}. Save the invoice to apply these allocations or enter a manual override. Actual funding depends on payments received.</p>
    </div>
</x-finance.panel>
