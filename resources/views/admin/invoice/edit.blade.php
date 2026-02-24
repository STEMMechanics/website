@php
    $savedLineItems = old('line_items_json');
    $isLocked = isset($invoice) && ! $invoice->canEditContents();
    $userLookupOptions = collect($users ?? [])->map(function ($user) {
        $name = trim((string) $user->getName());
        $email = trim((string) ($user->email ?? ''));
        $company = trim((string) ($user->company ?? ''));

        $label = $name !== '' ? $name : $email;
        if ($company !== '') {
            $label .= ' - '.$company;
        }
        if ($email !== '') {
            $label .= ' ('.$email.')';
        }

        return [
            'id' => (string) $user->id,
            'label' => $label,
        ];
    })->values();
    $userLookupMap = $userLookupOptions->mapWithKeys(fn ($item) => [$item['label'] => $item['id']])->all();
    $selectedUserId = (string) old('user_id', $invoice->user_id ?? '');
    $selectedUser = $userLookupOptions->first(fn ($item) => $item['id'] === $selectedUserId);
    $selectedUserLabel = is_array($selectedUser) ? ($selectedUser['label'] ?? '') : '';
    $selectedQuoteId = (string) old('quote_id', $invoice->quote_id ?? '');
    if ($savedLineItems === null) {
        $savedLineItems = json_encode($lineItemsSeed ?? []);
    }
    $invoiceSettlementKind = isset($invoice) ? $invoice->expectedSettlementKind() : \App\Models\Payment::KIND_PAYMENT;
    $invoiceAllocatedAmount = isset($invoice)
        ? (float) $invoice->settledAmount()
        : 0.0;
    $invoiceDueAmount = isset($invoice) ? (float) $invoice->dueAmount() : 0.0;
    $invoiceRemainingAmount = isset($invoice) ? (float) $invoice->outstandingAmount() : 0.0;
    $invoiceProgressPercent = isset($invoice) && $invoiceDueAmount > 0
        ? max(0, min(100, round(($invoiceAllocatedAmount / $invoiceDueAmount) * 100, 1)))
        : 0.0;
    $invoiceAllocations = isset($invoice)
        ? $invoice->allocations
            ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0)
            ->filter(fn ($allocation) => (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $invoiceSettlementKind)
            ->sortByDesc(fn ($allocation) => optional($allocation->customerPayment?->received_on)->timestamp ?? optional($allocation->customerPayment?->created_at)->timestamp ?? 0)
        : collect();
    $invoiceAdjustments = isset($invoice)
        ? $invoice->taxAdjustments->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0)
        : collect();
@endphp

<x-layout>
    <x-mast backRoute="admin.invoice.index" backTitle="Invoices">{{ isset($invoice) ? 'Edit' : 'Create' }} Invoice</x-mast>

    <x-container class="mt-4">
        @isset($invoice)
            <div class="flex justify-end mb-4 gap-2">
                @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                    <x-ui.button type="button" x-data x-on:click.prevent="window.open('{{ route('admin.invoice.pdf', $invoice) }}', '_blank', 'noopener,noreferrer')">Open PDF</x-ui.button>
                    <form method="POST" action="{{ route('admin.invoice.email', $invoice) }}" x-data="{ open: @js($errors->has('recipient_emails') || $errors->has('email_message')), emailMessage: @js((string) old('email_message', '')), recipientEmails: @js((string) old('recipient_emails', trim((string) ($invoice->billing_email ?: $invoice->user?->email ?? '')))) }">
                        @csrf
                        <x-ui.button type="submit" x-on:click.prevent="open = true">Email Invoice</x-ui.button>

                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="open = false">
                            <div class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-lg">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold">Email Invoice</h3>
                                    <button type="button" class="text-gray-600 hover:text-black" x-on:click.prevent="open = false">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                <label class="block text-sm pl-1" for="invoice-recipient-emails">Recipient Email(s)</label>
                                <input
                                    id="invoice-recipient-emails"
                                    name="recipient_emails"
                                    type="text"
                                    class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('recipient_emails') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                                    x-model="recipientEmails"
                                    placeholder="name@example.com, another@example.com"
                                />
                                <div class="text-xs text-gray-500 ml-2 mt-1">Use commas or semicolons to email multiple recipients.</div>
                                @if($errors->has('recipient_emails'))
                                    <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('recipient_emails') }}</div>
                                @endif

                                <label class="block text-sm pl-1 mt-4" for="invoice-email-message">Message (optional)</label>
                                <textarea
                                    id="invoice-email-message"
                                    name="email_message"
                                    rows="8"
                                    class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-indigo-300 focus:ring-indigo-300"
                                    x-model="emailMessage"
                                    placeholder="Add an optional message to include in the invoice email."
                                ></textarea>
                                <div class="mt-4 flex justify-end gap-2">
                                    <x-ui.button type="button" color="secondary" x-on:click.prevent="open = false">Cancel</x-ui.button>
                                    <x-ui.button type="button" x-on:click.prevent="$el.closest('form').submit();">Send Invoice Email</x-ui.button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <x-ui.button type="link" href="{{ route('admin.payment.create', ['invoice' => $invoice->invoice_number]) }}">Record Payment</x-ui.button>
                @endif
            </div>
            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
            <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap gap-6 text-sm">
                    <div><strong>Total:</strong> ${{ number_format((float) $invoice->total_amount, 2) }}</div>
                    <div><strong>Net Due:</strong> ${{ number_format($invoiceDueAmount, 2) }}</div>
                    <div><strong>Allocated:</strong> ${{ number_format($invoiceAllocatedAmount, 2) }}</div>
                    <div><strong>Remaining:</strong> ${{ number_format($invoiceRemainingAmount, 2) }}</div>
                </div>
                <div class="mt-3">
                    <div class="h-2 w-full rounded bg-gray-100 overflow-hidden">
                        <div class="h-2 bg-primary-color" style="width: {{ $invoiceProgressPercent }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-gray-600">{{ number_format($invoiceProgressPercent, 1) }}% net allocated</div>
                </div>
                <div class="mt-3">
                    <h3 class="font-semibold mb-2">Associated Payments</h3>
                    @if($invoiceAllocations->isEmpty())
                        <div class="text-sm text-gray-500">No payments allocated to this invoice yet.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 pr-3">Date</th>
                                        <th class="text-left py-2 pr-3">Payment #</th>
                                        <th class="text-left py-2 pr-3">Method</th>
                                        <th class="text-right py-2 pr-3">Allocated</th>
                                        <th class="text-left py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoiceAllocations as $allocation)
                                        @php $payment = $allocation->customerPayment; @endphp
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 pr-3">{{ $payment?->received_on?->format('M j, Y g:i a') ?? $payment?->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                                            <td class="py-2 pr-3">{{ $payment?->id ? '#'.$payment->id : '-' }}</td>
                                            <td class="py-2 pr-3">{{ $payment?->payment_method ? \App\Models\Payment::paymentMethodLabel((string) $payment->payment_method) : '-' }}</td>
                                            <td class="py-2 pr-3 text-right">${{ number_format((float) $allocation->allocated_amount, 2) }}</td>
                                            <td class="py-2">
                                                @if($payment)
                                                    <a href="{{ route('admin.payment.edit', $payment) }}" class="hover:text-primary-color mr-2" title="Open payment"><i class="fa-solid fa-pen-to-square"></i></a>
                                                    <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $payment]) }}" target="_blank" class="hover:text-primary-color mr-2" title="View receipt"><i class="fa-regular fa-file-lines"></i></a>
                                                    <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $payment, 'download' => 1]) }}" class="hover:text-primary-color" title="Download receipt"><i class="fa-solid fa-download"></i></a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="mt-6" id="tax-adjustments">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold">Tax Adjustment Notes</h3>
                        @if($isLocked)
                            <x-ui.button type="link" color="danger" href="{{ route('admin.tax_adjustment.create', ['invoice' => $invoice]) }}">Create Tax Adjustment Note</x-ui.button>
                        @endif
                    </div>
                    @if($invoiceAdjustments->isEmpty())
                        <div class="text-sm text-gray-500">No tax adjustment notes linked to this invoice yet.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 pr-3">Document #</th>
                                        <th class="text-left py-2 pr-3">Issue Date</th>
                                        <th class="text-right py-2 pr-3">Total</th>
                                        <th class="text-left py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoiceAdjustments as $adjustment)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 pr-3">{{ $adjustment->adjustment_number }}</td>
                                            <td class="py-2 pr-3">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</td>
                                            <td class="py-2 pr-3 text-right">${{ number_format((float) $adjustment->total_amount, 2) }}</td>
                                            <td class="py-2">
                                                <a href="{{ route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" class="hover:text-primary-color" title="Open tax adjustment">
                                                    <i class="fa-solid fa-up-right-from-square"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        @endisset

        <form
            method="POST"
            action="{{ route('admin.invoice.' . (isset($invoice) ? 'update' : 'store'), $invoice ?? []) }}"
            x-data="{
                isLocked: @js($isLocked),
                issueDate: @js(old('issue_date', isset($invoice) && $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : now()->format('Y-m-d'))),
                dueDate: @js(old('due_date', isset($invoice) && $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '')),
                lineItems: (() => {
                    try {
                        const parsed = JSON.parse(@js($savedLineItems));
                        if (!Array.isArray(parsed)) {
                            return [];
                        }

                        return parsed.map((item) => ({
                            kind: item.kind || 'generic',
                            description: item.description || '',
                            notes: item.notes || '',
                            quantity: parseFloat(item.quantity || 0),
                            unit_price: (() => {
                                const parsedUnit = parseFloat(item.unit_price_ex_tax ?? item.unit_price ?? 0);
                                return Number.isFinite(parsedUnit) ? parsedUnit.toFixed(2) : '0.00';
                            })(),
                            gst_applicable: typeof item.gst_applicable === 'boolean' ? item.gst_applicable : ((parseFloat(item.tax_rate ?? 0.1) || 0) > 0),
                        }));
                    } catch (e) {
                        return [];
                    }
                })(),
                serializeLineItems() {
                    const cleaned = this.lineItems
                        .map((item) => ({
                            kind: (item.kind || 'generic').trim() || 'generic',
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
                    if (this.isLocked) {
                        return;
                    }
                    this.lineItems.push({ kind: 'generic', description: '', notes: '', quantity: 1, unit_price: 0, gst_applicable: true });
                },
                removeLineItem(index) {
                    if (this.isLocked) {
                        return;
                    }
                    this.lineItems.splice(index, 1);
                    this.serializeLineItems();
                },
                setDueDateDefault(force = false) {
                    if (this.isLocked) {
                        return;
                    }
                    if (!this.issueDate) {
                        return;
                    }

                    if (!force && this.dueDate !== '') {
                        return;
                    }

                    const dueDate = new Date(this.issueDate + 'T00:00:00');
                    dueDate.setDate(dueDate.getDate() + 28);
                    this.dueDate = dueDate.toISOString().split('T')[0];
                },
                normalizeMoney(field) {
                    const value = parseFloat(field || 0);
                    return Number.isFinite(value) ? value.toFixed(2) : '0.00';
                },
                calculateSubtotal() {
                    let subtotal = 0;
                    this.lineItems.forEach((item) => {
                        const qty = parseFloat(item.quantity || 0);
                        const price = parseFloat(item.unit_price || 0);
                        subtotal += qty * price;
                    });
                    return subtotal;
                },
                calculateGst() {
                    let gst = 0;
                    this.lineItems.forEach((item) => {
                        const qty = parseFloat(item.quantity || 0);
                        const price = parseFloat(item.unit_price || 0);
                        if (item.gst_applicable !== false) {
                            gst += (qty * price) * 0.10;
                        }
                    });
                    return gst;
                },
                lineTotalExFormatted(item) {
                    const qty = parseFloat(item?.quantity || 0);
                    const price = parseFloat(item?.unit_price || 0);
                    return this.normalizeMoney(qty * price);
                },
                subtotalAmountFormatted() {
                    return this.normalizeMoney(this.calculateSubtotal());
                },
                gstAmountFormatted() {
                    return this.normalizeMoney(this.calculateGst());
                },
                totalAmountFormatted() {
                    return this.normalizeMoney(this.calculateSubtotal() + this.calculateGst());
                },
                normalizeLineItem(index, field) {
                    const value = parseFloat(this.lineItems[index]?.[field] || 0);
                    if (!Number.isFinite(value)) {
                        this.lineItems[index][field] = 0;
                    } else {
                        this.lineItems[index][field] = value.toFixed(2);
                    }
                    this.serializeLineItems();
                },
                linkedUserLabel: @js($selectedUserLabel),
                linkedUserMap: @js($userLookupMap),
                linkedUsers: @js($userLookupOptions->all()),
                linkedUserOpen: false,
                linkedUserSelectedIndex: -1,
                linkedUserFiltered: [],
                createUserOpen: false,
                createUserSubmitting: false,
                createUserError: '',
                createUserTab: 'contact',
                shippingSameBilling: true,
                newUser: {
                    firstname: '',
                    surname: '',
                    company: '',
                    email: '',
                    phone: '',
                    billing_address: '',
                    billing_address2: '',
                    billing_city: '',
                    billing_state: '',
                    billing_postcode: '',
                    billing_country: '',
                    shipping_address: '',
                    shipping_address2: '',
                    shipping_city: '',
                    shipping_state: '',
                    shipping_postcode: '',
                    shipping_country: '',
                },
                syncLinkedUserId() {
                    const matched = this.linkedUsers.find((option) => option.label === this.linkedUserLabel);
                    const userId = matched?.id || this.linkedUserMap[this.linkedUserLabel] || '';
                    this.$refs.linkedUserId.value = userId;
                },
                refreshLinkedUsers() {
                    const needle = String(this.linkedUserLabel || '').toLowerCase().trim();
                    if (needle === '') {
                        this.linkedUserFiltered = [];
                        this.linkedUserSelectedIndex = -1;
                        this.linkedUserOpen = false;
                        return;
                    }
                    this.linkedUserFiltered = this.linkedUsers
                        .filter((option) => String(option?.label || '').toLowerCase().includes(needle))
                        .slice(0, 8);
                    this.linkedUserSelectedIndex = this.linkedUserFiltered.length > 0 ? 0 : -1;
                    this.linkedUserOpen = this.linkedUserFiltered.length > 0;
                },
                moveLinkedUser(step) {
                    if (!this.linkedUserOpen) {
                        this.refreshLinkedUsers();
                        return;
                    }
                    const len = this.linkedUserFiltered.length;
                    if (!len) {
                        return;
                    }
                    this.linkedUserSelectedIndex = (this.linkedUserSelectedIndex + step + len) % len;
                },
                chooseLinkedUser(option) {
                    this.linkedUserLabel = option?.label || '';
                    this.$refs.linkedUserId.value = option?.id || '';
                    this.linkedUserOpen = false;
                    this.linkedUserSelectedIndex = -1;
                },
                confirmLinkedUser() {
                    if (!this.linkedUserOpen) {
                        return;
                    }
                    if (this.linkedUserSelectedIndex < 0 || this.linkedUserSelectedIndex >= this.linkedUserFiltered.length) {
                        return;
                    }
                    this.chooseLinkedUser(this.linkedUserFiltered[this.linkedUserSelectedIndex]);
                },
                openCreateUser() {
                    this.createUserOpen = true;
                    this.createUserTab = 'contact';
                    this.shippingSameBilling = true;
                    this.createUserError = '';
                    this.newUser = {
                        firstname: '',
                        surname: '',
                        company: '',
                        email: '',
                        phone: '',
                        billing_address: '',
                        billing_address2: '',
                        billing_city: '',
                        billing_state: '',
                        billing_postcode: '',
                        billing_country: '',
                        shipping_address: '',
                        shipping_address2: '',
                        shipping_city: '',
                        shipping_state: '',
                        shipping_postcode: '',
                        shipping_country: '',
                    };
                },
                closeCreateUser() {
                    this.createUserOpen = false;
                    this.createUserError = '';
                },
                syncShippingAddress() {
                    this.newUser.shipping_address = this.newUser.billing_address;
                    this.newUser.shipping_address2 = this.newUser.billing_address2;
                    this.newUser.shipping_city = this.newUser.billing_city;
                    this.newUser.shipping_state = this.newUser.billing_state;
                    this.newUser.shipping_postcode = this.newUser.billing_postcode;
                    this.newUser.shipping_country = this.newUser.billing_country;
                },
                async submitCreateUser() {
                    if (this.createUserSubmitting) {
                        return;
                    }
                    this.createUserSubmitting = true;
                    this.createUserError = '';
                    try {
                        if (this.shippingSameBilling) {
                            this.syncShippingAddress();
                        }
                        const response = await fetch('{{ route('admin.user.store-inline') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify(this.newUser),
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload?.success || !payload?.user) {
                            const firstError = payload?.errors ? Object.values(payload.errors)?.[0]?.[0] : null;
                            throw new Error(firstError || payload?.message || 'Unable to create user.');
                        }
                        const user = payload.user;
                        this.linkedUserMap[user.label] = user.id;
                        this.linkedUsers = [...this.linkedUsers, { id: user.id, label: user.label }]
                            .filter((value, index, array) => array.findIndex((item) => item.id === value.id) === index);
                        this.linkedUserLabel = user.label;
                        this.$refs.linkedUserId.value = user.id;
                        this.refreshLinkedUsers();
                        this.closeCreateUser();
                    } catch (error) {
                        this.createUserError = error?.message || 'Unable to create user.';
                    } finally {
                        this.createUserSubmitting = false;
                    }
                },
            }"
            x-init="setDueDateDefault(); syncLinkedUserId()"
            x-on:submit="serializeLineItems()">
            @isset($invoice)
                @method('PUT')
            @endisset
            @csrf

            <input type="hidden" name="line_items_json" x-ref="lineItemsJson" value="{{ $savedLineItems }}" />

            @if($isLocked)
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                    This document is issued and locked. To change amounts/items, create a tax adjustment note.
                </div>
            @endif
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input
                        label="Invoice Number"
                        name="invoice_number"
                        value="{{ old('invoice_number', $invoice->invoice_number ?? ($nextInvoiceNumber ?? '')) }}"
                        :disabled="$isLocked"
                    />
                </div>
                <div class="flex-1"></div>
            </div>

            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                <div><strong>Status:</strong> {{ \App\Models\Invoice::statusLabel((string) ($invoice->status ?? \App\Models\Invoice::STATUS_DRAFT)) }}</div>
                @if(! $isLocked)
                    <div class="mt-2">
                        <x-ui.checkbox
                            name="issue_now"
                            value="1"
                            label="Finalize invoice (move out of draft)"
                            :checked="old('issue_now', false)"
                            :noWrapper="true"
                            :inline="true"
                            inputClass="h-4 w-4 mt-0"
                            labelClass="pt-0"
                        />
                    </div>
                @endif
            </div>

            <x-ui.input label="Purchase Order Number" name="purchase_order_number" value="{{ old('purchase_order_number', $invoice->purchase_order_number ?? '') }}" />
            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <label for="quote_id" class="block text-sm pl-1">Linked Quote</label>
                    <button
                        type="button"
                        id="open-linked-quote-button"
                        class="text-xs text-primary-color hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed"
                        @disabled($selectedQuoteId === '')
                        onclick="
                            const select = document.getElementById('quote_id');
                            if (!select || !select.value) { return; }
                            const option = select.options[select.selectedIndex];
                            const url = option ? option.getAttribute('data-edit-url') : '';
                            if (!url) { return; }
                            window.open(url, '_blank', 'noopener,noreferrer');
                        "
                    >
                        Open linked quote
                    </button>
                </div>
                <select
                    id="quote_id"
                    name="quote_id"
                    onchange="
                        const button = document.getElementById('open-linked-quote-button');
                        if (!button) { return; }
                        button.disabled = this.value === '';
                    "
                    class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('quote_id') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                >
                    <option value="">None</option>
                    @foreach(($quotes ?? collect()) as $quoteOption)
                        <option
                            value="{{ $quoteOption->id }}"
                            data-edit-url="{{ route('admin.quote.edit', $quoteOption) }}"
                            {{ $selectedQuoteId === (string) $quoteOption->id ? 'selected' : '' }}
                        >
                            {{ $quoteOption->quote_number }} - {{ trim((string) ($quoteOption->user?->getName() ?? $quoteOption->user?->email ?? 'No user')) }}
                        </option>
                    @endforeach
                </select>
                <div class="text-xs text-gray-500 ml-2 mt-1">Can only link quotes for the same user.</div>
                @if($errors->has('quote_id'))
                    <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('quote_id') }}</div>
                @endif
            </div>

            <fieldset @if($isLocked) disabled @endif>

            <x-admin.user-selector-inline
                :users="$users ?? collect()"
                :selected-user-id="$selectedUserId"
                field-name="user_id"
                lookup-name="invoice_linked_user_lookup"
                label="Linked User"
                info="Search by name/company/email. Select a suggestion to link the invoice."
                :disabled="$isLocked"
            />

            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input
                        type="date"
                        label="Issue Date"
                        name="issue_date"
                        x-model="issueDate"
                        x-on:change="setDueDateDefault(true)"
                        value="{{ old('issue_date', isset($invoice) && $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                    />
                </div>
                <div class="flex-1">
                    <div class="mb-4">
                        <div class="flex items-center justify-between">
                            <label for="due_date" class="block text-sm pl-1">Due Date</label>
                            @if(! $isLocked)
                                <a href="#" class="text-xs text-primary-color hover:underline" x-on:click.prevent="setDueDateDefault(true)">Use default (+28 days)</a>
                            @endif
                        </div>
                        <input
                            id="due_date"
                            type="date"
                            name="due_date"
                            x-model="dueDate"
                            value="{{ old('due_date', isset($invoice) && $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '') }}"
                            class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 {{ $errors->has('due_date') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                        />
                        @if($errors->has('due_date'))
                            <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('due_date') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border rounded-lg p-4 mb-4" x-init="serializeLineItems()">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-lg">Line Items</h3>
                    @if(! $isLocked)
                        <button type="button" class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white whitespace-nowrap text-center justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="addLineItem()">Add Item</button>
                    @endif
                </div>

                <template x-if="lineItems.length === 0">
                    <div class="text-sm text-gray-500">No line items yet.</div>
                </template>

                <template x-for="(item, index) in lineItems" :key="index">
                    <div class="grid grid-cols-12 gap-3 mb-4 border-b pb-3 items-start">
                        <div class="col-span-2">
                            <label class="block text-sm pl-1">Type</label>
                            <select class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.kind" x-on:change="serializeLineItems()">
                                <option value="generic">Generic</option>
                                <option value="ticket">Ticket</option>
                                <option value="product">Product</option>
                            </select>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-sm pl-1">Description</label>
                            <input type="text" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.description" x-on:input="serializeLineItems()" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm pl-1">Qty / Hrs</label>
                            <input type="number" step="0.01" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.quantity" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'quantity')" />
                        </div>
                        <div class="col-span-3">
                            <label class="block text-sm pl-1">Unit Price (Ex GST)</label>
                            <input type="number" step="0.01" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.unit_price" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price')" />
                            <div class="mt-1 text-xs text-gray-600">
                                Total (Ex GST): $<span x-text="lineTotalExFormatted(item)"></span>
                            </div>
                        </div>
                        <div class="col-span-1">
                            <label class="block text-sm pl-1">GST</label>
                                <x-ui.checkbox
                                    label="GST applicable"
                                    :labelHidden="true"
                                    :noWrapper="true"
                                    :disabled="$isLocked"
                                    x-model="item.gst_applicable"
                                    x-bind:name="'line_item_gst_' + index"
                                    x-bind:id="'line_item_gst_' + index"
                                    x-on:change="serializeLineItems()"
                                />
                        </div>
                        <div class="col-span-1">
                            @if(! $isLocked)
                                <button type="button" class="text-red-600 hover:text-red-700 h-[42px]" x-on:click.prevent="removeLineItem(index)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            @endif
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
                    <x-ui.input
                        type="text"
                        label="Subtotal (Ex GST, Auto)"
                        name="subtotal_amount_display"
                        x-bind:value="subtotalAmountFormatted()"
                        value="{{ old('subtotal_amount_display', $invoice->subtotal_amount ?? '0.00') }}"
                        readonly="true"
                    />
                </div>
                <div class="flex-1">
                    <x-ui.input
                        type="text"
                        label="GST Amount (Auto)"
                        name="gst_amount_display"
                        x-bind:value="gstAmountFormatted()"
                        value="{{ old('gst_amount_display', $invoice->gst_amount ?? '0.00') }}"
                        readonly="true"
                    />
                </div>
            </div>

            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input
                        type="text"
                        label="Total Amount (Auto, Inc GST)"
                        name="total_amount_display"
                        x-bind:value="totalAmountFormatted()"
                        value="{{ old('total_amount_display', $invoice->total_amount ?? '0.00') }}"
                        readonly="true"
                    />
                </div>
                <div class="flex-1"></div>
            </div>

            </fieldset>

            <x-ui.input type="textarea" label="Notes" name="notes" value="{{ old('notes', $invoice->notes ?? '') }}" />
            <x-ui.filelist
                label="Private Files"
                info="Admin-only files attached to this invoice."
                name="private_files"
                editor="true"
                value="{!! isset($invoice) ? $invoice->files('private')->orderBy('name')->get() : '' !!}"
            />

            <div class="flex justify-end mt-8 gap-4">
                @if(isset($invoice))
                <x-ui.button
                    type="button"
                    color="danger"
                    x-data
                    x-on:click.prevent="SM.confirmDelete(
                        '{{ csrf_token() }}',
                        '{{ (string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT ? 'Delete draft invoice?' : 'Cancel invoice?' }}',
                        '{{ (string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT ? 'This will permanently delete this draft invoice. Continue?' : 'This will cancel the invoice and keep it for audit records. Continue?' }}',
                        '{{ route('admin.invoice.destroy', $invoice) }}'
                    )"
                >{{ (string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT ? 'Delete Draft' : 'Cancel Invoice' }}</x-ui.button>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>

        </form>
    </x-container>
</x-layout>
