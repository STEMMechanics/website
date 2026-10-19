<div class="relative">
    <span class="pointer-events-none absolute left-2 top-3">$</span>
    <x-ui.input-control aria-label="Unit price including GST" type="number" step="any" class="h-11 pl-6! pr-14!" x-model="item.unit_price_inc_tax" x-on:input="item.auto_pricing = false; delete item.details_json.inclusive_unit_price; delete item.details_json.price_rounding; serializeLineItems()" x-on:blur="finishUnitPrice(item, index)" />
    <div class="absolute inset-y-0 right-1 flex items-center">
        <x-ui.button type="button" variant="plain" class="flex h-8 w-6 items-center justify-center rounded text-amber-600 hover:bg-amber-50 focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Review price rounding" aria-haspopup="dialog" aria-controls="finance-price-rounding" x-cloak x-show="priceRoundingInfo(item) &amp;&amp; !priceRoundingInfo(item).clean" x-bind:title="priceRoundingTitle(item)" x-on:click="openPriceRounding(item)"><i class="fa-solid fa-triangle-exclamation text-xs" aria-hidden="true"></i></x-ui.button>
        <x-finance.line-refresh :price="true" :compact="true" />
    </div>
</div>
