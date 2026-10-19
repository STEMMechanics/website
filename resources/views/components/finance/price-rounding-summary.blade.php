@props(['documentLabel' => 'document'])
<div data-price-rounding-review x-data="{ get roundingSummary() { return SM.priceRoundingSummary(lineItems); } }" x-cloak x-show="!isLocked &amp;&amp; (roundingSummary.pending || roundingSummary.adjusted)" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm" role="status">
    <div class="min-w-0">
        <div x-show="roundingSummary.pending">
            <p class="font-medium text-amber-900" x-text="roundingSummary.pending + (roundingSummary.pending === 1 ? ' rate needs' : ' rates need') + ' a rounding review at the current quantity.'"></p>
            <p class="mt-1 text-slate-700">Current {{ $documentLabel }} total: <strong class="whitespace-nowrap tabular-nums" x-text="'$' + normalizeMoney(SM.documentAmounts(lineItems, savedTotals).gross)"></strong>. Rounded total: <strong class="whitespace-nowrap tabular-nums" x-text="'$' + normalizeMoney(roundingSummary.roundedTotal)"></strong> (inc GST).</p>
            <p class="mt-1 text-slate-600">Apply rounded rates to update the unit prices and totals.</p>
        </div>
        <p x-show="roundingSummary.adjusted" x-bind:class="{ 'mt-2': roundingSummary.pending }" class="text-slate-800">Price rounding included in total: <strong class="whitespace-nowrap tabular-nums" x-text="(roundingSummary.increase &gt;= 0 ? '+' : '−') + '$' + Math.abs(roundingSummary.increase).toFixed(2)"></strong></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="button" color="outline" size="small" x-on:click="$dispatch('review-price-rounding', { item: roundingSummary.first })">Review rates</x-ui.button>
        <x-ui.button type="button" size="small" x-show="roundingSummary.pending" x-on:click="$dispatch('apply-price-rounding')">Apply rounded rates</x-ui.button>
    </div>
</div>
