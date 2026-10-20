@props(['invoiceLayout' => false])
@php
    $workshopCatalog = app(\App\Support\RequestMemo::class)->remember('workshop-funding-catalog', fn () => app(\App\Services\Finance\WorkshopFunding::class)->catalog());
@endphp

<div x-init="if (!isLocked) SM.initializeWorkshopText(item)" {{ $attributes->merge(['class' => 'sm-invoice-line-details']) }}>
    <x-finance.product-line-fields />
    <div class="mb-3" x-show="item.kind === 'product'"><x-ui.input label="Description" type="text" x-model="item.description" readonly aria-readonly="true" /></div>
    <div class="mb-3" x-show="item.kind !== 'product'"><x-ui.input label="Description" type="text" x-model="item.description" x-on:input="serializeLineItems()" /></div>
    <div class="mb-3" x-show="item.kind === 'workshop'"><label class="mb-1 block text-sm">Workshop</label><x-finance.workshop-funding-fields /></div>
    <x-finance.workshop-line-fields :inclusive="true" :invoice-layout="$invoiceLayout" />
    <div x-show="item.kind === 'workshop'" class="mt-3 max-w-xs"><x-ui.input label="Workshop date" type="date" x-model="item.workshop_date" x-on:change="serializeLineItems()" /></div>
    <div class="mt-4 flex items-center justify-between">
        <label class="block text-sm" x-text="['workshop', 'multi_workshop'].includes(item.kind) ? 'Workshop text' : 'Line item notes'">Line item notes</label>
        <x-ui.button variant="plain" type="button" class="text-sm text-sky-600 hover:text-sky-800" x-show="['workshop', 'multi_workshop'].includes(item.kind)" x-on:click="SM.refreshWorkshopNotes(item); serializeLineItems()" title="Restore the default workshop text">↻ Reset template</x-ui.button>
    </div>
    <x-ui.textarea-control aria-label="Line item notes" x-bind:aria-label="['workshop', 'multi_workshop'].includes(item.kind) ? 'Workshop text' : 'Line item notes'" rows="4" class="mt-2 w-full resize-y" x-model="item.notes" x-on:input="SM.markWorkshopNotesEdited(item); serializeLineItems()" />
    <div x-show="['workshop', 'multi_workshop'].includes(item.kind)" class="mt-2 space-y-2 text-xs text-slate-500">
        <p>Use <code>{date}</code>, <code>{workshop}</code>, <code>{duration}</code> and <code>{seats}</code>. Duration and seats include their units. Placeholder lines repeat for each workshop. Leave blank to hide the workshop text.</p>
        <div x-show="item.notes" class="rounded-lg bg-slate-50 p-3">
            <div class="mb-1 font-medium text-slate-600">Preview</div>
            <div class="whitespace-pre-line text-slate-700" x-text="SM.workshopNotesPreview(item, @js($workshopCatalog))"></div>
        </div>
    </div>
</div>
