@props(['invoice', 'inline' => true])
<x-ui.button type="button" color="outline" class="size-10 p-0!" aria-label="Calculate cost centre allocation" title="Calculate cost centre allocation" aria-haspopup="dialog" :aria-controls="'invoice-allocation-calculator-'.$invoice->id.($inline ? '-inline' : '-editor')" :data-calculator-id="'invoice-allocation-calculator-'.$invoice->id.($inline ? '-inline' : '-editor')" x-data x-on:click="$dispatch('open-allocation-calculator', { dialogId: $el.dataset.calculatorId })">
    <i class="fa-solid fa-calculator" aria-hidden="true"></i>
</x-ui.button>
