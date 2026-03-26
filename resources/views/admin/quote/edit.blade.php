@php
    $editing = isset($quote);
    $quoteContext = $editing && is_array($quote->context_payload ?? null) ? $quote->context_payload : [];
    $quoteCustomer = is_array($quoteContext['customer'] ?? null) ? $quoteContext['customer'] : [];
    $savedLineItems = old('line_items_json');
    $selectedUserId = (string) old('user_id', $editing ? ($quote->user_id ?? '') : '');
    $quoteEmailNameSource = trim((string) ($editing ? ($quoteCustomer['billing_name'] ?? $quote->user?->getName() ?? '') : ''));
    $quoteEmailName = trim((string) strtok($quoteEmailNameSource, ' '));
    if ($quoteEmailName === '') {
        $quoteEmailName = $quoteEmailNameSource !== '' ? $quoteEmailNameSource : 'there';
    }
    $quoteNumberForEmail = $editing ? (string) ($quote->quote_number ?? '') : 'TBD';
    $defaultQuoteEmailMessage = $editing && (string) ($quote->context_type ?? '') === \App\Models\Quote::CONTEXT_STORE_MANUAL_SHIPPING
        ? "Hi {$quoteEmailName},\n\nAttached is quote **{$quoteNumberForEmail}** for your store items. You can review it online and choose to accept it using the link below.\n\nIf you accept the quote, we'll proceed with processing your request.\n\n{{action}}"
        : "Hi {$quoteEmailName},\n\nAttached is quote **{$quoteNumberForEmail}** for your request. You can review it online and choose to accept it using the link below.\n\nIf you accept the quote, we'll proceed with processing your request.\n\n{{action}}";

    if ($savedLineItems === null) {
        $savedLineItems = $editing ? json_encode($quote->line_items ?? []) : '[]';
    }

    $privateFinanceFiles = $editing ? $quote->privateFinanceFiles : collect();
    $linkedInvoices = $editing ? ($linkedInvoices ?? collect()) : collect();
@endphp

<x-layout>
    <x-mast backRoute="admin.quote.index" backTitle="Quotes">{{ isset($quote) ? 'Edit' : 'Create' }} Quote</x-mast>

    <x-container class="mt-4">
        @isset($quote)
            <div class="flex justify-end mb-4 gap-3">
                <x-ui.button type="button" x-data x-on:click.prevent="window.open('{{ route('admin.quote.pdf', $quote) }}', '_blank', 'noopener,noreferrer')">Open PDF</x-ui.button>
                <form method="POST" action="{{ route('admin.quote.email', $quote) }}" x-data="{ open: @js(session()->has('quote-email-open') || $errors->has('recipient_emails') || $errors->has('cc_emails') || $errors->has('email_message')), emailMessage: @js((string) old('email_message', $defaultQuoteEmailMessage)), recipientEmails: @js((string) old('recipient_emails', trim((string) ($quoteCustomer['billing_email'] ?? $quote->user?->email ?? '')))), ccEmails: @js((string) old('cc_emails', '')) }">
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
                                value="{{ (string) old('recipient_emails', trim((string) ($quoteCustomer['billing_email'] ?? $quote->user?->email ?? ''))) }}"
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
                            <div class="text-xs text-gray-500 ml-2 mt-1">Placeholders: @{{name}}, @{{id}}, @{{total}}, @{{outstanding}}, @{{due}}, @{{pay}}, @{{action}}</div>
                            <div class="mt-4 flex justify-end gap-2">
                                <x-ui.button type="button" color="secondary" x-on:click.prevent="open = false">Cancel</x-ui.button>
                                <x-ui.button type="button" x-on:click.prevent="$el.closest('form').submit();">Send Quote Email</x-ui.button>
                            </div>
                        </div>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                    @csrf
                    <x-ui.button type="submit">{{ isset($quote) && $quote->hasStoreProductLines() ? 'Create Order & Invoice' : 'Create Invoice From Quote' }}</x-ui.button>
                </form>
            </div>
        @endisset

        <form
            method="POST"
            action="{{ route('admin.quote.' . (isset($quote) ? 'update' : 'store'), $quote ?? []) }}"
            x-data="{
                quoteStatus: @js((string) old('status', $quote->status ?? \App\Models\Quote::STATUS_OPEN)),
                catalogProducts: @js($catalogProducts ?? []),
                lineItems: (() => {
                    try {
                        const parsed = JSON.parse(@js($savedLineItems));
                        if (!Array.isArray(parsed)) {
                            return [];
                        }

                        return parsed.map((item) => {
                            const kind = (item.kind || 'custom').toString().trim() || 'custom';
                            const storeContext = item.store_context || {};
                            const sourceId = item.source_id ?? storeContext.product_id ?? '';
                            const sourceVariantId = item.source_variant_id ?? storeContext.variant_id ?? 0;
                            const gstApplicable = typeof item.gst_applicable === 'boolean' ? item.gst_applicable : true;
                            const taxMultiplier = gstApplicable ? 1.1 : 1.0;
                            const quantity = parseFloat(item.quantity || 0);
                            const legacyUnitEx = parseFloat(item.unit_price_ex_tax ?? item.unit_price ?? 0);
                            const unitPriceInc = Number.isFinite(parseFloat(item.unit_price_inc_tax ?? ''))
                                ? parseFloat(item.unit_price_inc_tax || 0)
                                : (Number.isFinite(legacyUnitEx) ? legacyUnitEx * taxMultiplier : 0);
                            const unitPriceEx = Number.isFinite(parseFloat(item.unit_price_ex_tax ?? ''))
                                ? parseFloat(item.unit_price_ex_tax || 0)
                                : (taxMultiplier > 0 ? unitPriceInc / taxMultiplier : unitPriceInc);
                            const lineTotalEx = Number.isFinite(quantity) && Number.isFinite(unitPriceEx)
                                ? quantity * unitPriceEx
                                : 0;
                            const lineTotalInc = Number.isFinite(quantity) && Number.isFinite(unitPriceInc)
                                ? quantity * unitPriceInc
                                : 0;

                            return {
                                ...item,
                                kind,
                                source_id: sourceId !== '' && sourceId !== null && sourceId !== undefined
                                    ? String(parseInt(sourceId || 0, 10) || '')
                                    : '',
                                source_variant_id: String(parseInt(sourceVariantId || 0, 10) || 0),
                                description: item.description || '',
                                notes: item.notes || '',
                                quantity,
                                unit_price: Number.isFinite(unitPriceEx) ? unitPriceEx.toFixed(2) : '0.00',
                                unit_price_ex_tax: Number.isFinite(unitPriceEx) ? unitPriceEx.toFixed(2) : '0.00',
                                unit_price_inc_tax: Number.isFinite(unitPriceInc) ? unitPriceInc.toFixed(2) : '0.00',
                                line_total: Number.isFinite(lineTotalEx) ? lineTotalEx : 0,
                                line_total_ex_tax: Number.isFinite(lineTotalEx) ? lineTotalEx : 0,
                                line_total_inc_tax: Number.isFinite(lineTotalInc) ? lineTotalInc : 0,
                                gst_applicable: gstApplicable,
                            };
                        });
                    } catch (e) {
                        return [];
                    }
                })(),
                itemTypeOptions: [
                    { value: 'product', label: 'Store Product', icon: 'fa-box' },
                    { value: 'shipping', label: 'Shipping', icon: 'fa-truck' },
                    { value: 'workshop', label: 'Workshop Delivery', icon: 'fa-chalkboard-user' },
                    { value: 'travel', label: 'Travel Fee', icon: 'fa-route' },
                    { value: 'custom', label: 'Custom', icon: 'fa-pen-to-square' },
                ],
                defaultDescriptionForKind(kind) {
                    return {
                        shipping: 'Shipping',
                        workshop: 'Workshop Delivery',
                        travel: 'Travel Fee',
                    }[kind] ?? '';
                },
                defaultLineItem(kind = 'custom') {
                    return {
                        kind,
                        source_id: '',
                        source_variant_id: 0,
                        description: this.defaultDescriptionForKind(kind),
                        notes: '',
                        quantity: 1,
                        unit_price: '0.00',
                        unit_price_ex_tax: '0.00',
                        unit_price_inc_tax: '0.00',
                        line_total: 0,
                        line_total_ex_tax: 0,
                        line_total_inc_tax: 0,
                        gst_applicable: true,
                    };
                },
                addLineItem(kind = 'custom') {
                    this.lineItems.push(this.defaultLineItem(kind));
                    this.serializeLineItems();
                },
                findProduct(productId) {
                    return this.catalogProducts.find((product) => parseInt(product.id || 0) === parseInt(productId || 0)) || null;
                },
                normalizeSelectionValue(value, fallback = '') {
                    const numeric = parseInt(value || 0, 10);
                    if (Number.isNaN(numeric) || numeric < 0) {
                        return fallback;
                    }

                    return String(numeric);
                },
                itemTypeFor(kind) {
                    return this.itemTypeOptions.find((option) => option.value === kind) || this.itemTypeOptions[this.itemTypeOptions.length - 1];
                },
                itemTypeLabel(kind) {
                    return this.itemTypeFor(kind)?.label || 'Custom';
                },
                itemTypeIcon(kind) {
                    return this.itemTypeFor(kind)?.icon || 'fa-pen-to-square';
                },
                variantOptions(item) {
                    const product = this.findProduct(item.source_id);
                    if (!product || !product.has_option_choices) {
                        return [];
                    }

                    return [
                        {
                            id: 0,
                            name: product.base_option_name || product.title,
                            sku: product.sku || '',
                            summary: product.summary || '',
                        },
                        ...(Array.isArray(product.variants) ? product.variants : []),
                    ];
                },
                displayProductTitle(product, variant = null) {
                    if (!product) {
                        return '';
                    }

                    if (variant && variant.name) {
                        return `${product.title} - ${variant.name}`;
                    }

                    return product.title || '';
                },
                applyKind(index) {
                    const item = this.lineItems[index];
                    if (!item) {
                        return;
                    }

                    item.source_id = '';
                    item.source_variant_id = 0;
                    item.description = this.defaultDescriptionForKind(item.kind);
                    item.notes = item.kind === 'custom' ? item.notes : '';
                    if (item.kind === 'product') {
                        item.description = '';
                        item.notes = '';
                    }

                    this.serializeLineItems();
                },
                selectItemType(index, kind) {
                    const item = this.lineItems[index];
                    if (!item) {
                        return;
                    }

                    item.kind = kind;
                    this.applyKind(index);
                },
                applyProductSelection(index) {
                    const item = this.lineItems[index];
                    const product = this.findProduct(item?.source_id);
                    if (!item || !product) {
                        return;
                    }

                    const variant = this.variantOptions(item).find((entry) => parseInt(entry.id || 0) === parseInt(item.source_variant_id || 0)) || null;
                    item.description = this.displayProductTitle(product, variant);
                    item.gst_applicable = parseFloat(product.tax_rate || 0) > 0;
                    item.unit_price_inc_tax = this.formatUnitPriceValue(product.price || 0);
                    if ((item.notes || '').trim() === '') {
                        item.notes = (variant?.summary || product.summary || '').trim();
                    }

                    this.serializeLineItems();
                },
                preparedItem(item) {
                    const quantity = parseFloat(item.quantity || 0);
                    const unitPriceInc = Number.isFinite(parseFloat(item.unit_price_inc_tax ?? item.unit_price ?? 0))
                        ? parseFloat(item.unit_price_inc_tax ?? item.unit_price ?? 0)
                        : 0;
                    const unitPriceEx = item.gst_applicable !== false
                        ? unitPriceInc / 1.1
                        : unitPriceInc;
                    const lineTotalEx = quantity * unitPriceEx;
                    const lineTotalInc = quantity * unitPriceInc;
                    const cleaned = {
                        ...item,
                        kind: (item.kind || 'custom').toString().trim() || 'custom',
                        description: (item.description || '').trim(),
                        notes: (item.notes || '').trim(),
                        quantity,
                        unit_price: Number.isFinite(unitPriceEx) ? unitPriceEx : 0,
                        unit_price_ex_tax: Number.isFinite(unitPriceEx) ? unitPriceEx : 0,
                        unit_price_inc_tax: Number.isFinite(unitPriceInc) ? unitPriceInc : 0,
                        line_total: Number.isFinite(lineTotalEx) ? lineTotalEx : 0,
                        line_total_ex_tax: Number.isFinite(lineTotalEx) ? lineTotalEx : 0,
                        line_total_inc_tax: Number.isFinite(lineTotalInc) ? lineTotalInc : 0,
                        gst_applicable: item.gst_applicable !== false,
                    };

                    if (cleaned.kind === 'product') {
                        const product = this.findProduct(item.source_id);
                        const variant = this.variantOptions(item).find((entry) => parseInt(entry.id || 0) === parseInt(item.source_variant_id || 0)) || null;
                        const variantId = variant && parseInt(variant.id || 0) > 0 ? parseInt(variant.id || 0) : null;
                        cleaned.source_id = product ? parseInt(product.id || 0) : null;
                        cleaned.source_variant_id = variantId;
                        cleaned.store_context = product ? {
                            ...(item.store_context || {}),
                            product_id: parseInt(product.id || 0),
                            variant_id: variantId,
                            product_title: product.title || '',
                            product_slug: product.slug || '',
                            variant_name: variant?.name || product.base_option_name || '',
                            product_sku: product.sku || '',
                            variant_sku: variantId ? (variant?.sku || '') : (product.sku || ''),
                            product_type: product.product_type || '',
                            box_only: !!product.box_only,
                            unit_shipping_units: parseFloat(product.shipping_units || 0),
                            unit_min_satchel_rank: product.min_satchel_rank ?? null,
                            unit_weight_grams: product.weight_grams ?? null,
                            tax_rate: parseFloat(product.tax_rate || 0),
                            unit_price_inc_tax: Number.isFinite(parseFloat(cleaned.unit_price_inc_tax || 0)) ? parseFloat(cleaned.unit_price_inc_tax || 0) : 0,
                            line_price_inc_tax: Number.isFinite(parseFloat(cleaned.line_total_inc_tax || 0)) ? parseFloat(cleaned.line_total_inc_tax || 0) : 0,
                        } : (item.store_context || null);
                    } else {
                        cleaned.source_id = null;
                        cleaned.source_variant_id = null;
                        cleaned.store_context = null;
                    }

                    return cleaned;
                },
                serializeLineItems() {
                    const cleaned = this.lineItems
                        .map((item) => this.preparedItem(item))
                        .filter((item) => item.description !== '' || item.notes !== '' || item.quantity > 0 || item.unit_price_inc_tax > 0);

                    this.$refs.lineItemsJson.value = JSON.stringify(cleaned);
                },
                removeLineItem(index) {
                    this.lineItems.splice(index, 1);
                    this.serializeLineItems();
                },
                normalizeMoney(field) {
                    const value = parseFloat(field || 0);
                    return Number.isFinite(value) ? value.toFixed(2) : '0.00';
                },
                formatUnitPriceValue(value) {
                    const parsed = parseFloat(value || 0);
                    return Number.isFinite(parsed) ? parsed.toFixed(2) : '0.00';
                },
                lineItemAmount(item, basis = 'ex') {
                    const quantity = parseFloat(item.quantity || 0);
                    const unitPrice = basis === 'inc'
                        ? parseFloat(item.unit_price_inc_tax ?? item.unit_price ?? 0)
                        : this.lineItemExPrice(item);
                    if (!Number.isFinite(quantity) || !Number.isFinite(unitPrice)) {
                        return 0;
                    }

                    return quantity * unitPrice;
                },
                lineItemExPrice(item) {
                    const unitPriceInc = parseFloat(item.unit_price_inc_tax ?? item.unit_price ?? 0);
                    const multiplier = this.lineItemGstMultiplier(item);

                    if (!Number.isFinite(unitPriceInc)) {
                        return 0;
                    }

                    return multiplier > 0 ? unitPriceInc / multiplier : unitPriceInc;
                },
                lineItemGstMultiplier(item) {
                    return item.gst_applicable !== false ? 1.10 : 1.00;
                },
                unitPriceIncGst(item) {
                    return this.normalizeMoney(parseFloat(item.unit_price_inc_tax ?? item.unit_price ?? 0));
                },
                unitPriceExGst(item) {
                    return this.normalizeMoney(this.lineItemExPrice(item));
                },
                subtotalIncGst(item) {
                    return this.normalizeMoney(this.lineItemAmount(item, 'inc'));
                },
                calculateSubtotal() {
                    let subtotal = 0;
                    this.lineItems.forEach((item) => {
                        subtotal += this.lineItemAmount(item, 'ex');
                    });
                    return subtotal;
                },
                calculateGst() {
                    let gst = 0;
                    this.lineItems.forEach((item) => {
                        if (item.gst_applicable !== false) {
                            gst += this.lineItemAmount(item, 'inc') - this.lineItemAmount(item, 'ex');
                        }
                    });
                    return gst;
                },
                subtotalAmountFormatted() { return this.normalizeMoney(this.calculateSubtotal()); },
                gstAmountFormatted() { return this.normalizeMoney(this.calculateGst()); },
                totalAmountFormatted() { return this.normalizeMoney(this.calculateSubtotal() + this.calculateGst()); },
                hasStoreProductLines() {
                    return this.lineItems.some((item) => item.kind === 'product' && this.findProduct(item.source_id));
                },
                createOrderDisabledReason() {
                    return this.hasStoreProductLines()
                        ? ''
                        : 'Store order creation is only available when the quote includes at least one store product line item.';
                },
                normalizeLineItem(index, field) {
                    const value = parseFloat(this.lineItems[index]?.[field] || 0);
                    if (!Number.isFinite(value)) {
                        this.lineItems[index][field] = field === 'quantity' ? 0 : '0.00';
                    } else if (field === 'quantity') {
                        this.lineItems[index][field] = value;
                    } else {
                        this.lineItems[index][field] = value.toFixed(2);
                    }
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

            @if($editing)
                <div class="mb-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm pl-1">Linked Invoices</label>
                        <span class="text-xs text-gray-500">{{ $linkedInvoices->count() }} linked</span>
                    </div>
                    @if($linkedInvoices->isEmpty())
                        <div class="mt-1 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-3 text-sm text-gray-600">
                            No invoices linked yet. Create a new invoice from this quote, or link an existing invoice from the invoice edit page.
                        </div>
                    @else
                        <div class="mt-1 rounded-lg border border-gray-300 bg-white">
                            @foreach($linkedInvoices as $linkedInvoice)
                                <a
                                    href="{{ route('admin.invoice.edit', $linkedInvoice) }}"
                                    class="flex items-center justify-between px-3 py-3 text-sm text-gray-900 transition hover:bg-gray-50 {{ $loop->last ? '' : 'border-b border-gray-200' }}"
                                >
                                    <span>
                                        <span class="font-medium">{{ $linkedInvoice->invoice_number }}</span>
                                        <span class="text-gray-500">· {{ $linkedInvoice->issue_date?->format('M j, Y') ?? 'No issue date' }}</span>
                                    </span>
                                    <span class="text-gray-500">{{ money($linkedInvoice->total_amount) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="text-xs text-gray-500 ml-2 mt-1">Each invoice can link to one quote. A quote can link to multiple invoices for staged or progress billing.</div>
                </div>

                <div class="mb-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm pl-1">Linked Orders</label>
                        <span class="text-xs text-gray-500">{{ ($quote->storeOrders ?? collect())->count() }} linked</span>
                    </div>
                    @if(($quote->storeOrders ?? collect())->isEmpty())
                        <div class="mt-1 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-3 text-sm text-gray-600">
                            No store orders linked yet.
                        </div>
                    @else
                        <div class="mt-1 rounded-lg border border-gray-300 bg-white">
                            @foreach($quote->storeOrders as $linkedOrder)
                                <a
                                    href="{{ route('admin.shop.order.edit', $linkedOrder) }}"
                                    class="flex items-center justify-between px-3 py-3 text-sm text-gray-900 transition hover:bg-gray-50 {{ $loop->last ? '' : 'border-b border-gray-200' }}"
                                >
                                    <span>
                                        <span class="font-medium">{{ $linkedOrder->order_number }}</span>
                                        <span class="text-gray-500">· {{ $linkedOrder->statusLabel() }}</span>
                                    </span>
                                    <span class="text-gray-500">{{ money($linkedOrder->total_amount) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="text-xs text-gray-500 ml-2 mt-1">Store orders created from this quote are linked here.</div>
                </div>
            @endif

            <x-ui.select label="Status" name="status" x-model="quoteStatus">
                @foreach(\App\Models\Quote::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', $quote->status ?? \App\Models\Quote::STATUS_OPEN) === $status)>{{ \App\Models\Quote::statusLabelFor($status) }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input type="date" label="Quote Date" name="quote_date" value="{{ old('quote_date', isset($quote) && $quote->quote_date ? $quote->quote_date->format('Y-m-d') : now()->format('Y-m-d')) }}" />
            <x-ui.input label="Purchase Order Number" name="purchase_order_number" value="{{ old('purchase_order_number', $quote->purchase_order_number ?? '') }}" />

            <x-ui.input label="Quote Title" name="title" value="{{ old('title', $quote->title ?? '') }}" />
            <x-ui.input type="textarea" label="Quote Description" name="description" value="{{ old('description', $quote->description ?? '') }}" />

            <div class="mb-4 rounded-lg border border-gray-300 p-4">
                <div class="text-sm font-semibold text-gray-900">Customer Response</div>
                <div class="mt-1 text-xs text-gray-500">These options control what happens when the customer accepts the emailed quote.</div>

                <x-ui.checkbox
                    name="acceptance_emails_invoice"
                    label="Email an invoice to the customer when they accept"
                    :checked="old('acceptance_emails_invoice', $editing ? ($quote->acceptance_emails_invoice ?? false) : false)"
                />

                <x-ui.checkbox
                    name="acceptance_creates_order"
                    label="Create a store order when they accept"
                    :checked="old('acceptance_creates_order', $editing ? ($quote->acceptance_creates_order ?? false) : false) && ($editing ? $quote->hasStoreProductLines() : false)"
                    x-bind:disabled="!hasStoreProductLines()"
                />

                <div class="text-xs text-gray-500 ml-2" x-show="!hasStoreProductLines()" x-cloak>
                    Store order creation is only available when the quote includes at least one store product line item.
                </div>
            </div>

            <div class="border border-gray-400 rounded-lg p-4 mb-4" x-init="serializeLineItems()">
                <div class="flex flex-col gap-3 mb-3 md:flex-row md:items-center md:justify-between">
                    <h3 class="font-bold text-lg">Line Items</h3>
                    <button type="button" class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white whitespace-nowrap text-center justify-center rounded-md px-4 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="addLineItem('custom')">
                        <i class="fa-solid fa-plus mr-2"></i>Add Item
                    </button>
                </div>
                @if($errors->has('line_items_json'))
                    <div class="text-xs text-red-600 ml-2 mb-3">{{ $errors->first('line_items_json') }}</div>
                @endif

                <template x-if="lineItems.length === 0">
                    <div class="text-sm text-gray-500">No line items yet.</div>
                </template>

                <template x-for="(item, index) in lineItems" :key="index">
                    <div class="mb-4 rounded-xl border border-gray-300 bg-gray-50/60 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start">
                            <div class="relative w-full md:w-56 shrink-0" x-data="{ open: false }" @click.outside="open = false">
                                <label class="block text-sm pl-1">Item Type</label>
                                <button
                                    type="button"
                                    class="mt-1 flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm text-gray-900 shadow-sm transition hover:bg-gray-50"
                                    x-on:click.stop.prevent="open = !open"
                                >
                                    <span class="flex items-center gap-2">
                                        <i class="fa-solid text-gray-600" x-bind:class="itemTypeIcon(item.kind)"></i>
                                        <span x-text="itemTypeLabel(item.kind)"></span>
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-gray-400"></i>
                                </button>
                                <div x-show="open" x-cloak class="absolute z-20 mt-2 w-64 rounded-xl border border-gray-200 bg-white p-2 shadow-xl">
                                    <template x-for="option in itemTypeOptions" :key="option.value">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-gray-50"
                                            x-on:click.stop.prevent="open = false; selectItemType(index, option.value)"
                                        >
                                            <i class="fa-solid w-4 text-gray-500" x-bind:class="option.icon"></i>
                                            <span x-text="option.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="flex-1">
                                <template x-if="item.kind === 'product'">
                                    <div class="grid grid-cols-12 gap-3">
                                        <div class="col-span-12 md:col-span-7">
                                            <label class="block text-sm pl-1">Store Product</label>
                                            <select class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.source_id" x-on:change="item.source_variant_id = 0; applyProductSelection(index)">
                                                <option value="">Select a product</option>
                                                <template x-for="product in catalogProducts" :key="product.id">
                                                    <option :value="String(product.id)" :selected="String(item.source_id || '') === String(product.id || '')" x-text="product.title"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="col-span-12 md:col-span-5" x-show="variantOptions(item).length > 0">
                                            <label class="block text-sm pl-1">Variant</label>
                                            <select class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.source_variant_id" x-on:change="applyProductSelection(index)">
                                                <template x-for="variant in variantOptions(item)" :key="variant.id">
                                                    <option :value="String(variant.id)" :selected="String(item.source_variant_id || '0') === String(variant.id || '')" x-text="variant.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="item.kind !== 'product'">
                                    <div>
                                        <label class="block text-sm pl-1">Description</label>
                                        <input type="text" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.description" x-on:input="serializeLineItems()" placeholder="Workshop Delivery, Travel Fee, Shipping or custom text" />
                                    </div>
                                </template>
                            </div>

                            <button type="button" class="self-start text-red-600 hover:text-red-700 md:pt-8" x-on:click.prevent="removeLineItem(index)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>

                        <div class="mt-3 grid grid-cols-12 gap-3 items-end">
                            <div class="col-span-12 md:col-span-5" x-show="item.kind === 'product'" x-cloak>
                                <label class="block text-sm pl-1">Description</label>
                                <input type="text" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.description" x-on:input="serializeLineItems()" />
                            </div>
                            <div class="col-span-6 md:col-span-2">
                                <label class="block text-sm pl-1">Qty / Hrs</label>
                                <input type="number" step="any" min="0" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.quantity" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'quantity')" />
                            </div>
                            <div class="col-span-6 md:col-span-3">
                                <label class="block text-sm pl-1">Unit Price (Inc GST)</label>
                                <input type="text" inputmode="decimal" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.unit_price_inc_tax" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price_inc_tax')" />
                            </div>
                            <div class="col-span-6 md:col-span-2">
                                <label class="block text-sm pl-1">Unit Price (Ex GST, Auto)</label>
                                <input type="text" readonly tabindex="-1" class="disabled:bg-gray-100 bg-gray-100 block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-700 rounded-lg border border-gray-300" x-bind:value="unitPriceExGst(item)" />
                            </div>
                            <div class="col-span-6 md:col-span-2">
                                <label class="block text-sm pl-1">Sub Total (Inc GST)</label>
                                <input type="text" readonly tabindex="-1" class="disabled:bg-gray-100 bg-gray-100 block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-700 rounded-lg border border-gray-300" x-bind:value="subtotalIncGst(item)" />
                            </div>
                            <div class="col-span-12 md:col-span-2">
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
                        </div>

                        <div class="mt-3">
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
                    <x-ui.input type="text" label="Total Amount (Auto, incl GST)" name="total_amount_display" x-bind:value="totalAmountFormatted()" value="{{ old('total_amount_display', $quote->total_amount ?? '0.00') }}" readonly="true" />
                </div>
                <div class="flex-1"></div>
            </div>

            <x-ui.input type="textarea" label="Notes" name="notes" value="{{ old('notes', $quote->notes ?? '') }}" />
            <x-ui.input type="textarea" label="Private Notes" name="private_notes" value="{{ old('private_notes', $quote->private_notes ?? '') }}" info="Internal only. Copied to linked invoices and store orders." />
            <x-admin.finance-file-manager
                label="Private Files"
                info="Admin-only files attached to this quote."
                field-name="private_file_ids"
                upload-name="private_file_upload"
                upload-id="quote-private-file-upload"
                context-type="quote"
                context-id="{{ isset($quote) ? (string) $quote->id : '' }}"
                :files="$privateFinanceFiles"
            />

            <div class="flex justify-end mt-8 gap-4">
                @isset($quote)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')">Delete</x-ui.button>
                    <x-ui.button
                        type="submit"
                        color="primary-outline"
                        name="save_and_email"
                        value="1"
                        x-bind:disabled="quoteStatus !== @js(\App\Models\Quote::STATUS_OPEN)"
                    >
                        Save and Email
                    </x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>

        </form>
    </x-container>
</x-layout>
