<div class="flex items-start gap-3 p-3 text-sm" x-data="SM.invoiceFollowup(@js(route('admin.workplan.invoice.follow-up', $invoice)), @js((string) $invoice->notes), @js($invoice->follow_up_contacted_at?->format('j M Y, g:ia')))">
    <i class="fa-solid fa-file-invoice-dollar mt-0.5 w-4 text-red-600" aria-hidden="true"></i>
    <x-ui.checkbox bare small labelHidden :label="'Done for now: invoice '.$invoice->invoice_number" title="Done for now" :checked="$invoice->follow_up_contacted_at !== null" x-bind:checked="contacted !== null" x-bind:disabled="saving" @change="save({ checked: $event.target.checked }, $event.target)" />
    <div class="min-w-0 flex-1">
        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="font-semibold text-gray-900 hover:underline">Overdue invoice {{ $invoice->invoice_number }} · {{ $invoice->user?->getName() ?: $invoice->billing_name }}</a>
        <span class="block text-xs text-red-600">{{ money((float) $invoice->displayOutstandingAmount()) }} outstanding · due {{ $invoice->due_date?->format('j M') }}</span>
        <span class="mt-1 block whitespace-pre-line break-words text-xs text-gray-500" x-show="notes" x-text="notes">{{ $invoice->notes }}</span>
    </div>
    <x-ui.button variant="plain" class="text-gray-500 hover:text-primary-color" :data-open-dialog="'invoice-follow-up-'.$invoice->id" @click="draft = notes" :title="'Private notes for invoice '.$invoice->invoice_number" :aria-label="'Private notes for invoice '.$invoice->invoice_number">
        <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
    </x-ui.button>
    <x-ui.list-dialog :id="'invoice-follow-up-'.$invoice->id" :title="'Invoice '.$invoice->invoice_number.' · Private notes'">
        <form @submit.prevent="save({ notes: draft, original_notes: notes }, null, $el.closest('dialog'))">
            <div class="p-5">
                <p class="mb-3 text-sm text-gray-500">Internal notes, shared with the invoice editor.</p>
                <x-ui.input type="textarea" :name="'follow_up_notes_'.$invoice->id" label="Private notes" x-model="draft" rows="6" maxlength="10000" />
            </div>
            <div class="sm-dialog-footer"><x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button><x-ui.button type="submit" x-bind:disabled="saving">Save notes</x-ui.button></div>
        </form>
    </x-ui.list-dialog>
</div>
