<div class="relative">
    <template x-if="quantityIsCalculated(item)">
        <div class="flex h-11 flex-col justify-center px-2 pr-11 text-center" title="Calculated from the workshop details">
            <output aria-label="Calculated hours or quantity" class="text-sm font-semibold tabular-nums text-slate-900" x-text="normalizeMoney(item.quantity)"></output>
            <span class="text-xs text-slate-500">Calculated</span>
        </div>
    </template>
    <template x-if="!quantityIsCalculated(item)">
        <x-ui.input-control aria-label="Hours or quantity" type="number" step="any" class="h-11 pr-11!" x-model="item.quantity" x-on:input="if (item.kind === 'travel') { item.travel_hours = item.quantity; SM.updateWorkshopLine(item); } serializeLineItems()" />
    </template>
    <x-finance.line-refresh />
</div>
