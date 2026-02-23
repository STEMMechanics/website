@php
    $savedLineItems = old('line_items_json');
    $selectedUserId = (string) old('user_id', $quote->user_id ?? '');

    if ($savedLineItems === null) {
        $savedLineItems = isset($quote) ? json_encode($quote->line_items ?? []) : '[]';
    }
@endphp

<x-layout>
    <x-mast backRoute="admin.quote.index" backTitle="Quotes">{{ isset($quote) ? 'Edit' : 'Create' }} Quote</x-mast>

    <x-container class="mt-4">
        @isset($quote)
            <div class="flex justify-end mb-4 gap-3">
                <x-ui.button type="button" x-data x-on:click.prevent="window.open('{{ route('admin.quote.pdf', $quote) }}', '_blank', 'noopener,noreferrer')">Open PDF</x-ui.button>
                <form method="POST" action="{{ route('admin.quote.email', $quote) }}" x-data="{ open: false, emailMessage: '' }">
                    @csrf
                    <input type="hidden" name="email_message" x-ref="emailMessage">
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
                            <label class="block text-sm pl-1" for="quote-email-message">Message (optional)</label>
                            <textarea
                                id="quote-email-message"
                                rows="8"
                                class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-indigo-300 focus:ring-indigo-300"
                                x-model="emailMessage"
                                placeholder="Add an optional message to include in the quote email."
                            ></textarea>
                            <div class="mt-4 flex justify-end gap-2">
                                <x-ui.button type="button" color="secondary" x-on:click.prevent="open = false">Cancel</x-ui.button>
                                <x-ui.button type="button" x-on:click.prevent="$refs.emailMessage.value = emailMessage; $el.closest('form').submit();">Send Quote Email</x-ui.button>
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

            <x-ui.input type="date" label="Quote Date" name="quote_date" value="{{ old('quote_date', isset($quote) && $quote->quote_date ? $quote->quote_date->format('Y-m-d') : now()->format('Y-m-d')) }}" />

            <x-ui.input label="Quote Title" name="title" value="{{ old('title', $quote->title ?? '') }}" />
            <x-ui.input type="textarea" label="Quote Description" name="description" value="{{ old('description', $quote->description ?? '') }}" />

            <div class="border rounded-lg p-4 mb-4" x-init="serializeLineItems()">
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
                    <div class="grid grid-cols-12 gap-3 items-end mb-4 border-b pb-3">
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
                            <label class="h-[42px] flex items-center justify-center mt-1 border border-gray-300 rounded-lg bg-white cursor-pointer">
                                <x-ui.checkbox
                                    label="GST applicable"
                                    :labelHidden="true"
                                    :noWrapper="true"
                                    inputClass="w-4 h-4 mt-0"
                                    x-model="item.gst_applicable"
                                    x-bind:name="'quote_line_item_gst_' + index"
                                    x-bind:id="'quote_line_item_gst_' + index"
                                    x-on:change="serializeLineItems()"
                                />
                            </label>
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

            <div class="flex justify-end mt-8 gap-4">
                @isset($quote)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>

        </form>
    </x-container>
</x-layout>
