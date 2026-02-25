@php
    $editing = isset($quote);
    $savedLineItems = old('line_items_json');
    $selectedUserId = (string) old('user_id', $editing ? ($quote->user_id ?? '') : '');
    $selectedLinkedInvoiceId = (string) old('linked_invoice_id', $linkedInvoiceId ?? '');
    $quoteEmailNameSource = trim((string) ($editing ? ($quote->user?->getName() ?? $quote->billing_name ?? '') : ''));
    $quoteEmailName = trim((string) strtok($quoteEmailNameSource, ' '));
    if ($quoteEmailName === '') {
        $quoteEmailName = $quoteEmailNameSource !== '' ? $quoteEmailNameSource : 'there';
    }
    $quoteNumberForEmail = $editing ? (string) ($quote->quote_number ?? '') : 'TBD';
    $defaultQuoteEmailMessage = "Hi {$quoteEmailName},\n\nAttached is quote **{$quoteNumberForEmail}** for a workshop. Please don't hesitate to reach out if you have any questions.";

    if ($savedLineItems === null) {
        $savedLineItems = $editing ? json_encode($quote->line_items ?? []) : '[]';
    }
@endphp

<x-layout>
    <x-mast backRoute="admin.quote.index" backTitle="Quotes">{{ isset($quote) ? 'Edit' : 'Create' }} Quote</x-mast>

    <x-container class="mt-4">
        @isset($quote)
            <div class="flex justify-end mb-4 gap-3">
                <x-ui.button type="button" x-data x-on:click.prevent="window.open('{{ route('admin.quote.pdf', $quote) }}', '_blank', 'noopener,noreferrer')">Open PDF</x-ui.button>
                <form method="POST" action="{{ route('admin.quote.email', $quote) }}" x-data="{ open: @js($errors->has('recipient_emails') || $errors->has('cc_emails') || $errors->has('email_message')), emailMessage: @js((string) old('email_message', $defaultQuoteEmailMessage)), recipientEmails: @js((string) old('recipient_emails', trim((string) ($quote->user?->email ?? '')))), ccEmails: @js((string) old('cc_emails', '')) }">
                    @csrf
                    <x-ui.button type="submit" x-data x-on:click.prevent="
                        open = true;
                    ">Email Quote</x-ui.button>

                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="open = false">
                        <div class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-lg">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-lg font-semibold">Email Quote</h3>
                                <button type="button" class="text-gray-600 hover:text-black" x-on:click.prevent="open = false">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <label class="block text-sm pl-1 mt-4" for="quote-recipient-emails">Recipient Email</label>
                            <input
                                id="quote-recipient-emails"
                                name="recipient_emails"
                                type="text"
                                value="{{ (string) old('recipient_emails', trim((string) ($quote->user?->email ?? ''))) }}"
                                class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('recipient_emails') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                                x-model="recipientEmails"
                                placeholder="name@example.com, another@example.com"
                            />
                            <div class="text-xs text-gray-500 ml-2 mt-1">Use commas or semicolons to email multiple recipients.</div>
                            @if($errors->has('recipient_emails'))
                                <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('recipient_emails') }}</div>
                            @endif

                            <label class="block text-sm pl-1 mt-4" for="quote-cc-emails">CC</label>
                            <input
                                id="quote-cc-emails"
                                name="cc_emails"
                                type="text"
                                value="{{ (string) old('cc_emails', '') }}"
                                class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('cc_emails') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                                x-model="ccEmails"
                                placeholder="cc@example.com, team@example.com"
                            />
                            <div class="text-xs text-gray-500 ml-2 mt-1">Use commas or semicolons to add multiple CC recipients.</div>
                            @if($errors->has('cc_emails'))
                                <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('cc_emails') }}</div>
                            @endif

                            <label class="block text-sm pl-1 mt-4" for="quote-email-message">Message</label>
                            <textarea
                                id="quote-email-message"
                                name="email_message"
                                rows="8"
                                class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-indigo-300 focus:ring-indigo-300"
                                x-model="emailMessage"
                                placeholder="Compose the full email body. Supports placeholders like @{{name}} and @{{id}}."
                            >{{ (string) old('email_message', $defaultQuoteEmailMessage) }}</textarea>
                            <div class="text-xs text-gray-500 ml-2 mt-1">Placeholders: @{{name}}, @{{id}}, @{{total}}, @{{outstanding}}, @{{due}}, @{{pay}}</div>
                            <div class="mt-4 flex justify-end gap-2">
                                <x-ui.button type="button" color="secondary" x-on:click.prevent="open = false">Cancel</x-ui.button>
                                <x-ui.button type="button" x-on:click.prevent="$el.closest('form').submit();">Send Quote Email</x-ui.button>
                            </div>
                        </div>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                    @csrf
                    <x-ui.button type="submit">Create Invoice From Quote</x-ui.button>
                </form>
            </div>
        @endisset

        <form
            method="POST"
            action="{{ route('admin.quote.' . (isset($quote) ? 'update' : 'store'), $quote ?? []) }}"
            x-data="{
                lineItems: (() => {
                    try {
                        const parsed = JSON.parse(@js($savedLineItems));
                        if (!Array.isArray(parsed)) {
                            return [];
                        }

                        return parsed.map((item) => ({
                            description: item.description || '',
                            notes: item.notes || '',
                            quantity: parseFloat(item.quantity || 0),
                            unit_price: parseFloat(item.unit_price || 0),
                            gst_applicable: typeof item.gst_applicable === 'boolean' ? item.gst_applicable : true,
                        }));
                    } catch (e) {
                        return [];
                    }
                })(),
                serializeLineItems() {
                    const cleaned = this.lineItems
                        .map((item) => ({
                            description: (item.description || '').trim(),
                            notes: (item.notes || '').trim(),
                            quantity: parseFloat(item.quantity || 0),
                            unit_price: parseFloat(item.unit_price || 0),
                            gst_applicable: item.gst_applicable !== false,
                        }))
                        .filter((item) => item.description !== '' || item.notes !== '' || item.quantity > 0 || item.unit_price > 0);

                    this.$refs.lineItemsJson.value = JSON.stringify(cleaned);
                },
                addLineItem() {
                    this.lineItems.push({ description: '', notes: '', quantity: 1, unit_price: 0, gst_applicable: true });
                },
                removeLineItem(index) {
                    this.lineItems.splice(index, 1);
                    this.serializeLineItems();
                },
                normalizeMoney(field) {
                    const value = parseFloat(field || 0);
                    return Number.isFinite(value) ? value.toFixed(2) : '0.00';
                },
                calculateSubtotal() {
                    let subtotal = 0;
                    this.lineItems.forEach((item) => {
                        subtotal += parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0);
                    });
                    return subtotal;
                },
                calculateGst() {
                    let gst = 0;
                    this.lineItems.forEach((item) => {
                        if (item.gst_applicable !== false) {
                            gst += (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) * 0.10;
                        }
                    });
                    return gst;
                },
                subtotalAmountFormatted() { return this.normalizeMoney(this.calculateSubtotal()); },
                gstAmountFormatted() { return this.normalizeMoney(this.calculateGst()); },
                totalAmountFormatted() { return this.normalizeMoney(this.calculateSubtotal() + this.calculateGst()); },
                normalizeLineItem(index, field) {
                    const value = parseFloat(this.lineItems[index]?.[field] || 0);
                    this.lineItems[index][field] = Number.isFinite(value) ? value.toFixed(2) : 0;
                    this.serializeLineItems();
                },
            }"
            x-on:submit="serializeLineItems()">
            @isset($quote)
                @method('PUT')
            @endisset
            @csrf

            <input type="hidden" name="line_items_json" x-ref="lineItemsJson" value="{{ $savedLineItems }}" />

            <x-ui.input label="Quote Number" name="quote_number" value="{{ old('quote_number', $quote->quote_number ?? ($nextQuoteNumber ?? '')) }}" />

            <x-admin.user-selector-inline
                :users="$users ?? collect()"
                :selected-user-id="$selectedUserId"
                field-name="user_id"
                lookup-name="quote_linked_user_lookup"
                label="Linked User"
                info="Search by name/company/email. Select a suggestion to link the quote."
            />

            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <label for="linked_invoice_id" class="block text-sm pl-1">Linked Invoice</label>
                    <button
                        type="button"
                        id="open-linked-invoice-button"
                        class="text-xs text-primary-color hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed"
                        @disabled($selectedLinkedInvoiceId === '')
                        onclick="
                            const select = document.getElementById('linked_invoice_id');
                            if (!select || !select.value) { return; }
                            const option = select.options[select.selectedIndex];
                            const url = option ? option.getAttribute('data-edit-url') : '';
                            if (!url) { return; }
                            window.open(url, '_blank', 'noopener,noreferrer');
                        "
                    >
                        Open linked invoice
                    </button>
                </div>
                <select
                    id="linked_invoice_id"
                    name="linked_invoice_id"
                    onchange="
                        const button = document.getElementById('open-linked-invoice-button');
                        if (!button) { return; }
                        button.disabled = this.value === '';
                    "
                    class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('linked_invoice_id') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                >
                    <option value="">None</option>
                    @foreach(($invoices ?? collect()) as $invoiceOption)
                        <option
                            value="{{ $invoiceOption->id }}"
                            data-edit-url="{{ route('admin.invoice.edit', $invoiceOption) }}"
                            {{ $selectedLinkedInvoiceId === (string) $invoiceOption->id ? 'selected' : '' }}
                        >
                            {{ $invoiceOption->invoice_number }} - {{ trim((string) ($invoiceOption->user?->getName() ?? $invoiceOption->user?->email ?? 'No user')) }}
                        </option>
                    @endforeach
                </select>
                <div class="text-xs text-gray-500 ml-2 mt-1">Can only link invoices for the same user.</div>
                @if($errors->has('linked_invoice_id'))
                    <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('linked_invoice_id') }}</div>
                @endif
            </div>

            <x-ui.input type="date" label="Quote Date" name="quote_date" value="{{ old('quote_date', isset($quote) && $quote->quote_date ? $quote->quote_date->format('Y-m-d') : now()->format('Y-m-d')) }}" />

            <x-ui.input label="Quote Title" name="title" value="{{ old('title', $quote->title ?? '') }}" />
            <x-ui.input type="textarea" label="Quote Description" name="description" value="{{ old('description', $quote->description ?? '') }}" />

            <div class="border border-gray-400 rounded-lg p-4 mb-4" x-init="serializeLineItems()">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-lg">Line Items</h3>
                    <button type="button" class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white whitespace-nowrap text-center justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="addLineItem()">Add Item</button>
                </div>
                @if($errors->has('line_items_json'))
                    <div class="text-xs text-red-600 ml-2 mb-3">{{ $errors->first('line_items_json') }}</div>
                @endif

                <template x-if="lineItems.length === 0">
                    <div class="text-sm text-gray-500">No line items yet.</div>
                </template>

                <template x-for="(item, index) in lineItems" :key="index">
                    <div class="grid grid-cols-12 gap-3 items-end mb-4 border-b border-gray-300 pb-6">
                        <div class="col-span-5">
                            <label class="block text-sm pl-1">Description</label>
                            <input type="text" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.description" x-on:input="serializeLineItems()" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm pl-1">Qty / Hrs</label>
                            <input type="number" min="0" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.quantity" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'quantity')" />
                        </div>
                        <div class="col-span-3">
                            <label class="block text-sm pl-1">Unit Price (Ex GST)</label>
                            <input type="number" step="0.01" min="0" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.unit_price" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price')" />
                        </div>
                        <div class="col-span-1">
                            <label class="block text-sm pl-1">GST</label>
                            <x-ui.checkbox
                                :labelHidden="true"
                                :noWrapper="true"
                                class="h-12 w-12 flex"
                                inputClass="mt-0"
                                x-model="item.gst_applicable"
                                x-bind:name="'quote_line_item_gst_' + index"
                                x-bind:id="'quote_line_item_gst_' + index"
                                x-on:change="serializeLineItems()"
                            />
                        </div>
                        <div class="col-span-1">
                            <button type="button" class="text-red-600 hover:text-red-700 h-[42px]" x-on:click.prevent="removeLineItem(index)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <div class="col-span-12">
                            <label class="block text-sm pl-1">Line Item Notes</label>
                            <textarea rows="4" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.notes" x-on:input="serializeLineItems()" placeholder="Optional multiline notes for this line item"></textarea>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input type="text" label="Subtotal (Ex GST, Auto)" name="subtotal_amount_display" x-bind:value="subtotalAmountFormatted()" value="{{ old('subtotal_amount_display', $quote->subtotal_amount ?? '0.00') }}" readonly="true" />
                </div>
                <div class="flex-1">
                    <x-ui.input type="text" label="GST Amount (Auto)" name="gst_amount_display" x-bind:value="gstAmountFormatted()" value="{{ old('gst_amount_display', $quote->gst_amount ?? '0.00') }}" readonly="true" />
                </div>
            </div>

            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input type="text" label="Total Amount (Auto, Inc GST)" name="total_amount_display" x-bind:value="totalAmountFormatted()" value="{{ old('total_amount_display', $quote->total_amount ?? '0.00') }}" readonly="true" />
                </div>
                <div class="flex-1"></div>
            </div>

            <x-ui.input type="textarea" label="Notes" name="notes" value="{{ old('notes', $quote->notes ?? '') }}" />
            <x-ui.filelist
                label="Private Files"
                info="Admin-only files attached to this quote."
                name="private_files"
                editor="true"
                value="{!! isset($quote) ? $quote->files('private')->orderBy('name')->get() : '' !!}"
            />

            <div class="flex justify-end mt-8 gap-4">
                @isset($quote)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>

        </form>
    </x-container>
</x-layout>
