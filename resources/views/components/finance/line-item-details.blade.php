@props(['invoiceLayout' => false])

<div {{ $attributes->merge(['class' => 'sm-invoice-line-details']) }}>
    <x-finance.product-line-fields />
    <div class="mb-3" x-show="item.kind !== 'workshop'"><x-ui.input label="Description" type="text" x-model="item.description" x-on:input="serializeLineItems()" /></div>
    <div class="mb-3" x-show="item.kind === 'workshop'"><label class="mb-1 block text-sm">Workshop</label><x-finance.workshop-funding-fields /></div>
    <x-finance.workshop-line-fields :inclusive="true" :invoice-layout="$invoiceLayout" />
    <div x-show="item.kind === 'workshop'" class="mt-3 max-w-xs"><x-ui.input label="Workshop date" type="date" x-model="item.workshop_date" x-on:change="serializeLineItems()" /></div>
    <div class="mt-4 flex items-center justify-between">
        <label class="block text-sm">Line item notes</label>
        <button type="button" class="text-sm text-sky-600 hover:text-sky-800" x-show="['workshop', 'multi_workshop'].includes(item.kind)" x-on:click="SM.refreshWorkshopNotes(item); serializeLineItems()" title="Regenerate notes from workshop data">↻ Refresh</button>
    </div>
    <x-ui.textarea-control aria-label="Line item notes" rows="4" class="mt-2 w-full resize-y" x-model="item.notes" x-on:input="SM.markWorkshopNotesEdited(item); serializeLineItems()" />
</div>
