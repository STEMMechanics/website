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
    $selectedUserId = (string) old('user_id', isset($invoice) ? ($invoice->user_id ?? '') : '');
    $selectedUser = $userLookupOptions->first(fn ($item) => $item['id'] === $selectedUserId);
    $selectedUserLabel = is_array($selectedUser) ? ($selectedUser['label'] ?? '') : '';
    $selectedQuoteId = (string) old('quote_id', isset($invoice) ? ($invoice->quote_id ?? '') : '');
    if ($savedLineItems === null) {
        $savedLineItems = json_encode($lineItemsSeed ?? []);
    }
    $invoiceSettlementKind = isset($invoice) ? $invoice->expectedSettlementKind() : \App\Models\Payment::KIND_PAYMENT;
    $invoiceGrossAllocatedAmount = isset($invoice)
        ? round((float) $invoice->allocations
            ->filter(function ($allocation) use ($invoiceSettlementKind) {
                if (! $allocation->customerPayment) {
                    return false;
                }

                return (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $invoiceSettlementKind
                    && ((float) $allocation->allocated_amount) > 0;
            })
            ->sum('allocated_amount'), 2)
        : 0.0;
    $invoiceRefundedAmount = isset($invoice)
        ? round(abs((float) $invoice->allocations
            ->filter(function ($allocation) use ($invoiceSettlementKind) {
                if (! $allocation->customerPayment) {
                    return false;
                }

                return (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $invoiceSettlementKind
                    && ((float) $allocation->allocated_amount) < 0;
            })
            ->sum('allocated_amount')), 2)
        : 0.0;
    $invoiceNetAllocatedAmount = round(max(0, $invoiceGrossAllocatedAmount - $invoiceRefundedAmount), 2);
    $invoiceDueAmount = isset($invoice) ? (float) $invoice->displayDueAmount() : 0.0;
    $invoiceRemainingAmount = isset($invoice) && (string) $invoice->status === \App\Models\Invoice::STATUS_CANCELLED
        ? 0.0
        : round(max(0, $invoiceDueAmount - $invoiceNetAllocatedAmount), 2);
    $invoiceProgressPercent = isset($invoice) && $invoiceDueAmount > 0 && (string) $invoice->status !== \App\Models\Invoice::STATUS_CANCELLED
        ? max(0, min(100, round(($invoiceNetAllocatedAmount / $invoiceDueAmount) * 100, 1)))
        : 0.0;
    $invoicePaymentRows = isset($invoice)
        ? $invoice->allocations
            ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0)
            ->filter(fn ($allocation) => (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $invoiceSettlementKind)
            ->groupBy('payment_id')
            ->map(function ($allocations) {
                $payment = $allocations->first()?->customerPayment;

                return [
                    'payment' => $payment,
                    'allocated_amount' => round((float) $allocations->sum('allocated_amount'), 2),
                    'refunds' => $payment instanceof \App\Models\Payment
                        ? $payment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0)->values()
                        : collect(),
                ];
            })
            ->sortByDesc(fn ($row) => optional($row['payment']?->received_on)->timestamp ?? optional($row['payment']?->created_at)->timestamp ?? 0)
            ->values()
        : collect();
    $invoiceAdjustments = isset($invoice)
        ? $invoice->taxAdjustments->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0)
        : collect();
    $invoiceEmailNameSource = trim((string) (isset($invoice) ? ($invoice->user?->getName() ?? $invoice->billing_name ?? '') : ''));
    $invoiceEmailName = trim((string) strtok($invoiceEmailNameSource, ' '));
    if ($invoiceEmailName === '') {
        $invoiceEmailName = $invoiceEmailNameSource !== '' ? $invoiceEmailNameSource : 'there';
    }
    $invoiceNumberForEmail = isset($invoice) ? (string) ($invoice->invoice_number ?? '') : 'TBD';
    $invoiceTotalDisplay = '$'.number_format((float) (isset($invoice) ? ($invoice->total_amount ?? 0) : 0), 2);
    $invoiceDueDisplay = isset($invoice) ? ($invoice->due_date?->format('M j, Y') ?? 'the due date on file') : 'the due date on file';
    if (isset($invoice) && $invoice->isTicketInvoice()) {
        $defaultInvoiceEmailMessage = "Hi {$invoiceEmailName},\n\nAttached is invoice **{$invoiceNumberForEmail}** for your workshop ticket booking. The total cost is {$invoiceTotalDisplay} and is due on {$invoiceDueDisplay}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
    } else {
        $defaultInvoiceEmailMessage = "Hi {$invoiceEmailName},\n\nAttached is invoice **{$invoiceNumberForEmail}** for your workshop program and materials. The total cost is {$invoiceTotalDisplay} and is due on {$invoiceDueDisplay}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
    }
    $privateFinanceFiles = isset($invoice) ? $invoice->privateFinanceFiles : collect();
    $invoiceEmailDefaultPayload = $invoiceEmailDefaultPayload ?? [
        'action' => '',
        'invoice_number' => '',
        'recipient_emails' => '',
        'subject_line' => '',
        'cc_emails' => '',
        'email_message' => '',
    ];
@endphp

<x-layout>
    <x-mast backRoute="admin.invoice.index" backTitle="Invoices">{{ isset($invoice) ? 'Edit' : 'Create' }} Invoice</x-mast>

<x-container
    class="mt-4"
    x-data="{
        invoiceEmailModalOpen: {{ session()->has('invoice-email-open') || $errors->has('recipient_emails') || $errors->has('subject_line') || $errors->has('cc_emails') || $errors->has('email_message') ? 'true' : 'false' }},
        invoiceEmailAction: {{ json_encode((string) ($invoiceEmailDefaultPayload['action'] ?? '')) }},
        invoiceEmailInvoiceNumber: {{ json_encode((string) ($invoiceEmailDefaultPayload['invoice_number'] ?? ($invoice->invoice_number ?? ''))) }},
        invoiceEmailRecipientEmails: {{ json_encode((string) old('recipient_emails', session('invoice-email-recipient-emails', $invoiceEmailDefaultPayload['recipient_emails'] ?? ''))) }},
        invoiceEmailSubjectLine: {{ json_encode((string) old('subject_line', session('invoice-email-subject-line', $invoiceEmailDefaultPayload['subject_line'] ?? ''))) }},
        invoiceEmailCcEmails: {{ json_encode((string) old('cc_emails', session('invoice-email-cc-emails', ''))) }},
        invoiceEmailMessage: {{ json_encode((string) old('email_message', session('invoice-email-message', $invoiceEmailDefaultPayload['email_message'] ?? ''))) }},
        invoiceEmailSubjectOpen: false,
        invoiceEmailCcOpen: false,
        invoiceEmailHelpOpen: false,
        openInvoiceEmailModal(payload) {
            this.invoiceEmailAction = payload?.action || this.invoiceEmailAction || '';
            this.invoiceEmailInvoiceNumber = payload?.invoice_number || this.invoiceEmailInvoiceNumber || '';
            this.invoiceEmailRecipientEmails = payload?.recipient_emails || this.invoiceEmailRecipientEmails || '';
            this.invoiceEmailSubjectLine = payload?.subject_line || this.invoiceEmailSubjectLine || '';
            this.invoiceEmailCcEmails = payload?.cc_emails || '';
            this.invoiceEmailMessage = payload?.email_message || this.invoiceEmailMessage || '';
            this.invoiceEmailModalOpen = true;
            this.invoiceEmailHelpOpen = false;
            this.invoiceEmailSubjectOpen = false;
            this.invoiceEmailCcOpen = false;
        },
        closeInvoiceEmailModal() {
            this.invoiceEmailModalOpen = false;
            this.invoiceEmailHelpOpen = false;
        },
        }"
>
        @isset($invoice)
            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                <x-ui.toolbar break="md" class="mb-4">
                    <x-slot:right>
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                            <x-ui.button type="button" x-data x-on:click.prevent="window.open('{{ route('admin.invoice.pdf', $invoice) }}', '_blank', 'noopener,noreferrer')" class="w-full sm:w-auto">Open PDF</x-ui.button>
                            <x-ui.button type="button" x-on:click.prevent="openInvoiceEmailModal({{ json_encode($invoiceEmailDefaultPayload) }})" class="w-full sm:w-auto">Email Invoice</x-ui.button>
                            <x-admin.invoice-email-modal />
                            <x-ui.button
                                type="button"
                                color="secondary"
                                x-data
                                x-on:click.prevent="
                                    fetch('{{ route('admin.invoice.payment-link', $invoice) }}', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Accept': 'application/json'
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (!data || !data.url) {
                                            throw new Error('Unable to generate payment link.');
                                        }
                                        SM.copyToClipboard(data.url);
                                        SM.alert('Payment Link Copied', 'Invoice payment link copied to clipboard.', 'success');
                                    })
                                    .catch((error) => {
                                        SM.alert('Copy Failed', error?.message || 'Unable to generate payment link.', 'danger');
                                    });
                                "
                                class="w-full sm:w-auto"
                            >Copy Payment Link</x-ui.button>
                            <x-ui.button href="{{ route('admin.payment.create', ['invoice' => $invoice->invoice_number]) }}" class="w-full sm:w-auto">Record Payment</x-ui.button>
                        </div>
                    </x-slot:right>
                </x-ui.toolbar>
            @endif
            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
            <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap gap-6 text-sm">
                    <div><strong>Total:</strong> ${{ number_format((float) $invoice->total_amount, 2) }}</div>
                    <div><strong>Due (after adjustments):</strong> ${{ number_format($invoiceDueAmount, 2) }}</div>
                    <div><strong>Allocated:</strong> ${{ number_format($invoiceGrossAllocatedAmount, 2) }}</div>
                    <div><strong>Refunded:</strong> ${{ number_format($invoiceRefundedAmount, 2) }}</div>
                    <div><strong>Net Allocated:</strong> ${{ number_format($invoiceNetAllocatedAmount, 2) }}</div>
                    <div><strong>Remaining:</strong> ${{ number_format($invoiceRemainingAmount, 2) }}</div>
                </div>
                <div class="mt-3">
                    <div class="h-2 w-full rounded bg-gray-100 overflow-hidden">
                        <div class="h-2 bg-primary-color" style="width: {{ $invoiceProgressPercent }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-gray-600">{{ number_format($invoiceProgressPercent, 1) }}% due covered (net)</div>
                </div>
                <div class="mt-3">
                    <h3 class="font-semibold mb-2">Associated Payments</h3>
                    @if($invoicePaymentRows->isEmpty())
                        <div class="text-sm text-gray-500">No payments allocated to this invoice yet.</div>
                    @else
                        <div class="space-y-3 md:hidden">
                            @foreach($invoicePaymentRows as $row)
                                @php
                                    $payment = $row['payment'];
                                    $refunds = $row['refunds'];
                                @endphp
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">{{ $payment?->id ? '#'.$payment->id : '-' }}</div>
                                            <div class="mt-0.5 text-xs text-gray-600">{{ $payment?->payment_method ? \App\Models\Payment::paymentMethodLabel((string) $payment->payment_method) : '-' }}</div>
                                        </div>
                                        <div class="text-right text-sm font-semibold text-gray-900">${{ number_format((float) $row['allocated_amount'], 2) }}</div>
                                    </div>
                                    <div class="mt-3 grid gap-2 text-xs text-gray-600">
                                        <div><span class="font-semibold text-gray-500">Date:</span> {{ $payment?->received_on?->format('M j, Y g:i a') ?? $payment?->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                        <div><span class="font-semibold text-gray-500">Method:</span> {{ $payment?->payment_method ? \App\Models\Payment::paymentMethodLabel((string) $payment->payment_method) : '-' }}</div>
                                        <div><span class="font-semibold text-gray-500">Invoice effect:</span> ${{ number_format((float) $row['allocated_amount'], 2) }}</div>
                                    </div>
                                    <div class="mt-3 flex items-center gap-3">
                                        @if($payment)
                                            <a href="{{ route('admin.payment.edit', $payment) }}" class="text-sm text-primary-color hover:underline" title="Open payment">Open payment</a>
                                            <a href="{{ route('admin.payment.receipt', ['payment' => $payment]) }}" target="_blank" class="text-sm text-primary-color hover:underline" title="View receipt">Receipt</a>
                                            <a href="{{ route('admin.payment.receipt', ['payment' => $payment, 'download' => 1]) }}" class="text-sm text-primary-color hover:underline" title="Download receipt">Download</a>
                                        @endif
                                    </div>
                                </div>
                                @foreach($refunds as $refund)
                                    <div class="ml-4 rounded-lg border border-gray-200 bg-white p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900">#{{ $refund->id }}</div>
                                                <div class="mt-0.5 text-xs text-gray-600">Refund for #{{ $payment?->id ?? '-' }}</div>
                                            </div>
                                            <div class="text-right text-sm font-semibold text-gray-900">-${{ number_format((float) $refund->total_amount, 2) }}</div>
                                        </div>
                                        <div class="mt-3 grid gap-2 text-xs text-gray-600">
                                            <div><span class="font-semibold text-gray-500">Date:</span> {{ $refund->received_on?->format('M j, Y g:i a') ?? $refund->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                            <div><span class="font-semibold text-gray-500">Method:</span> {{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</div>
                                            <div><span class="font-semibold text-gray-500">Type:</span> Refund</div>
                                        </div>
                                        <div class="mt-3 flex items-center gap-3">
                                            <a href="{{ route('admin.payment.edit', $refund) }}" class="text-sm text-primary-color hover:underline" title="Open refund record">Open refund</a>
                                            <a href="{{ route('admin.payment.receipt', ['payment' => $refund]) }}" target="_blank" class="text-sm text-primary-color hover:underline" title="View refund receipt">Receipt</a>
                                            <a href="{{ route('admin.payment.receipt', ['payment' => $refund, 'download' => 1]) }}" class="text-sm text-primary-color hover:underline" title="Download refund receipt">Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full min-w-[52rem] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 pr-3">Date</th>
                                        <th class="text-left py-2 pr-3">Payment #</th>
                                        <th class="text-left py-2 pr-3">Method</th>
                                        <th class="text-right py-2 pr-3">Invoice Effect</th>
                                        <th class="text-left py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoicePaymentRows as $row)
                                        @php
                                            $payment = $row['payment'];
                                            $refunds = $row['refunds'];
                                        @endphp
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 pr-3">{{ $payment?->received_on?->format('M j, Y g:i a') ?? $payment?->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                                            <td class="py-2 pr-3">{{ $payment?->id ? '#'.$payment->id : '-' }}</td>
                                            <td class="py-2 pr-3">{{ $payment?->payment_method ? \App\Models\Payment::paymentMethodLabel((string) $payment->payment_method) : '-' }}</td>
                                            <td class="py-2 pr-3 text-right">
                                                ${{ number_format((float) $row['allocated_amount'], 2) }}
                                            </td>
                                            <td class="py-2">
                                                @if($payment)
                                                    <a href="{{ route('admin.payment.edit', $payment) }}" class="hover:text-primary-color mr-2" title="Open payment"><i class="fa-solid fa-pen-to-square"></i></a>
                                                    <a href="{{ route('admin.payment.receipt', ['payment' => $payment]) }}" target="_blank" class="hover:text-primary-color mr-2" title="View receipt"><i class="fa-regular fa-file-lines"></i></a>
                                                    <a href="{{ route('admin.payment.receipt', ['payment' => $payment, 'download' => 1]) }}" class="hover:text-primary-color" title="Download receipt"><i class="fa-solid fa-download"></i></a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                        @foreach($refunds as $refund)
                                            <tr class="border-b border-gray-100 bg-gray-50">
                                                <td class="py-2 pr-3">{{ $refund->received_on?->format('M j, Y g:i a') ?? $refund->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                                                <td class="py-2 pr-3">
                                                    #{{ $refund->id }}
                                                    <div class="text-xs text-gray-500">Refund for #{{ $payment?->id ?? '-' }}</div>
                                                </td>
                                                <td class="py-2 pr-3">
                                                    {{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}
                                                    <div class="text-xs text-gray-500">Refund</div>
                                                </td>
                                                <td class="py-2 pr-3 text-right">-${{ number_format((float) $refund->total_amount, 2) }}</td>
                                                <td class="py-2">
                                                    <a href="{{ route('admin.payment.edit', $refund) }}" class="hover:text-primary-color mr-2" title="Open refund record"><i class="fa-solid fa-pen-to-square"></i></a>
                                                    <a href="{{ route('admin.payment.receipt', ['payment' => $refund]) }}" target="_blank" class="hover:text-primary-color mr-2" title="View refund receipt"><i class="fa-regular fa-file-lines"></i></a>
                                                    <a href="{{ route('admin.payment.receipt', ['payment' => $refund, 'download' => 1]) }}" class="hover:text-primary-color" title="Download refund receipt"><i class="fa-solid fa-download"></i></a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="mt-6" id="tax-adjustments">
                    <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="font-semibold">Tax Adjustment Notes</h3>
                        @if($isLocked)
                            <x-ui.button color="danger" href="{{ route('admin.tax_adjustment.create', ['invoice' => $invoice]) }}" class="w-full sm:w-auto">Create Tax Adjustment Note</x-ui.button>
                        @endif
                    </div>
                    @if($invoiceAdjustments->isEmpty())
                        <div class="text-sm text-gray-500">No tax adjustment notes linked to this invoice yet.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[42rem] text-sm">
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
                invoiceStatus: @js((string) old('status', isset($invoice) ? ($invoice->status ?? \App\Models\Invoice::STATUS_DRAFT) : \App\Models\Invoice::STATUS_DRAFT)),
                issueNow: @js((bool) old('issue_now', false)),
                canSaveAndEmail() {
                    return this.invoiceStatus !== @js(\App\Models\Invoice::STATUS_DRAFT) || this.issueNow;
                },
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

                    const [year, month, day] = String(this.issueDate).split('-').map((value) => Number.parseInt(value, 10));
                    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
                        return;
                    }

                    const dueDate = new Date(Date.UTC(year, month - 1, day));
                    dueDate.setUTCDate(dueDate.getUTCDate() + 28);
                    while (dueDate.getUTCDay() === 0 || dueDate.getUTCDay() === 6) {
                        dueDate.setUTCDate(dueDate.getUTCDate() + 1);
                    }
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
                    } else if (field === 'quantity') {
                        this.lineItems[index][field] = value;
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
                        value="{{ old('invoice_number', isset($invoice) ? ($invoice->invoice_number ?? '') : ($nextInvoiceNumber ?? '')) }}"
                        :disabled="$isLocked"
                    />
                </div>
                <div class="flex-1"></div>
            </div>

            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                <div><strong>Status:</strong> {{ \App\Models\Invoice::statusLabel((string) (isset($invoice) ? ($invoice->status ?? \App\Models\Invoice::STATUS_DRAFT) : \App\Models\Invoice::STATUS_DRAFT)) }}</div>
                @if(! $isLocked)
                    <div class="mt-2">
                        <x-ui.checkbox
                            name="issue_now"
                            value="1"
                            label="Finalize invoice (move out of draft)"
                            :checked="old('issue_now', false)"
                            :noWrapper="true"
                            :inline="true"
                            x-model="issueNow"
                        />
                    </div>
                @endif
            </div>

            <x-ui.input label="Purchase Order Number" name="purchase_order_number" value="{{ old('purchase_order_number', isset($invoice) ? ($invoice->purchase_order_number ?? '') : '') }}" />
            @if(isset($invoice) && ($invoice->storeOrders ?? collect())->isNotEmpty())
                <div class="mb-4 rounded-lg border border-gray-300 p-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm pl-1">Linked Orders</label>
                        <span class="text-xs text-gray-500">{{ $invoice->storeOrders->count() }} linked</span>
                    </div>
                    <div class="mt-1 rounded-lg border border-gray-300 bg-white">
                        @foreach($invoice->storeOrders as $linkedOrder)
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
                    <div class="text-xs text-gray-500 ml-2 mt-1">Open the linked store order to review fulfilment and tracking.</div>
                </div>
            @endif
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
                <x-ui.select
                    name="quote_id"
                    label="Linked Quote"
                    noLabel="true"
                    innerClass="mt-1"
                    x-on:change="
                        const button = document.getElementById('open-linked-quote-button');
                        if (!button) { return; }
                        button.disabled = this.value === '';
                    "
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
                </x-ui.select>
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
                                <a href="#" class="text-xs text-primary-color hover:underline" x-on:click.prevent="setDueDateDefault(true)">Use default (+28 days, next business day)</a>
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
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="font-bold text-lg">Line Items</h3>
                    @if(! $isLocked)
                        <button type="button" class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white w-full whitespace-nowrap text-center justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition sm:w-auto" x-on:click.prevent="addLineItem()">Add Item</button>
                    @endif
                </div>

                <template x-if="lineItems.length === 0">
                    <div class="text-sm text-gray-500">No line items yet.</div>
                </template>

                <template x-for="(item, index) in lineItems" :key="index">
                        <div class="grid grid-cols-1 gap-3 mb-4 border-b border-gray-300 pb-6 items-start md:grid-cols-12">
                            <div class="md:col-span-2">
                                <label class="block text-sm pl-1" :for="`line_item_kind_${index}`">Type</label>
                                <x-ui.select
                                    name="line_item_kind"
                                noLabel="true"
                                class="mb-0"
                                innerClass="mt-1"
                                x-bind:id="`line_item_kind_${index}`"
                                x-model="item.kind"
                                x-on:change="serializeLineItems()"
                            >
                                <option value="generic">Generic</option>
                                <option value="product">Product</option>
                                <option value="ticket">Ticket</option>
                                </x-ui.select>
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-sm pl-1">Description</label>
                                <input type="text" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.description" x-on:input="serializeLineItems()" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm pl-1">Qty / Hrs</label>
                                <input type="number" step="any" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.quantity" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'quantity')" />
                            </div>
                        <div class="md:col-span-4">
                            <label class="block text-sm pl-1">Unit Price (Ex GST)</label>
                            <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <input type="number" step="0.01" class="disabled:bg-gray-100 bg-white block px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="item.unit_price" x-on:input="serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price')" />
                                    <div class="mt-1 text-xs text-gray-600">
                                        Total (Ex GST): $<span x-text="lineTotalExFormatted(item)"></span>
                                    </div>
                                </div>
                                <x-ui.checkbox
                                    class="shrink-0"
                                    inputClass="mt-0"
                                    :label="'GST applies'"
                                    :inline="true"
                                    :noWrapper="true"
                                    :disabled="$isLocked"
                                    x-model="item.gst_applicable"
                                    x-bind:name="'line_item_gst_' + index"
                                    x-bind:id="'line_item_gst_' + index"
                                    x-on:change="serializeLineItems()"
                                />
                            </div>
                        </div>
                        <div class="md:col-span-1">
                            @if(! $isLocked)
                                <button type="button" class="text-red-600 hover:text-red-700 h-[42px]" x-on:click.prevent="removeLineItem(index)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            @endif
                        </div>
                        <div class="md:col-span-12">
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
                        value="{{ old('subtotal_amount_display', isset($invoice) ? ($invoice->subtotal_amount ?? '0.00') : '0.00') }}"
                        readonly="true"
                    />
                </div>
                <div class="flex-1">
                    <x-ui.input
                        type="text"
                        label="GST Amount (Auto)"
                        name="gst_amount_display"
                        x-bind:value="gstAmountFormatted()"
                        value="{{ old('gst_amount_display', isset($invoice) ? ($invoice->gst_amount ?? '0.00') : '0.00') }}"
                        readonly="true"
                    />
                </div>
            </div>

            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input
                        type="text"
                        label="Total Amount (Auto, incl GST)"
                        name="total_amount_display"
                        x-bind:value="totalAmountFormatted()"
                        value="{{ old('total_amount_display', isset($invoice) ? ($invoice->total_amount ?? '0.00') : '0.00') }}"
                        readonly="true"
                    />
                </div>
                <div class="flex-1"></div>
            </div>

            </fieldset>

            <x-ui.input type="textarea" label="Private Notes" name="notes" value="{{ old('notes', isset($invoice) ? ($invoice->notes ?? '') : '') }}" />
            <x-admin.finance-file-manager
                label="Private Files"
                info="Admin-only files attached to this invoice."
                field-name="private_file_ids"
                upload-name="private_file_upload"
                upload-id="invoice-private-file-upload"
                context-type="invoice"
                context-id="{{ isset($invoice) ? (string) $invoice->id : '' }}"
                :files="$privateFinanceFiles"
            />

            @if(isset($invoice))
                <div class="flex justify-end mt-8 gap-4">
                    @php
                        $isDraftInvoice = (string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT;
                        $cancelBlockReason = ! $isDraftInvoice ? $invoice->cancellationBlockedReason() : null;
                        $canCancelInvoice = $cancelBlockReason === null;
                        $invoiceConfirmTitle = $isDraftInvoice ? 'Delete draft invoice?' : 'Cancel invoice?';
                        $invoiceConfirmMessage = $isDraftInvoice
                            ? 'This will permanently delete this draft invoice. Continue?'
                            : 'Invoice cancellation is exceptional and should only be used when the invoice was issued in error.<br><br>For workshop no-shows, cancel the ticket instead so the tax adjustment note is created.<br>For store orders, cancel the linked order and handle any refund through the order flow.<br><br>Continue only if this invoice has no payments or downstream records.';
                        $invoiceConfirmButtonText = $isDraftInvoice ? 'Delete' : 'Cancel Invoice';
                        $invoiceCancelButtonText = $isDraftInvoice ? 'Cancel' : 'Keep Invoice';
                    @endphp
                    @if($isDraftInvoice)
                        <button
                            type="button"
                            class="inline-flex items-center justify-center text-gray-500 transition hover:text-red-600 disabled:cursor-not-allowed disabled:text-gray-300 disabled:pointer-events-none"
                            title="Delete Draft"
                            x-data
                            x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', @js($invoiceConfirmTitle), @js($invoiceConfirmMessage), '{{ route('admin.invoice.destroy', $invoice) }}', @js($invoiceConfirmButtonText), @js($invoiceCancelButtonText))"
                        ><i class="fa-solid fa-trash"></i></button>
                    @elseif($canCancelInvoice)
                        <button
                            type="button"
                            class="inline-flex items-center justify-center text-gray-500 transition hover:text-red-600 disabled:cursor-not-allowed disabled:text-gray-300 disabled:pointer-events-none"
                            title="Cancel Invoice"
                            x-data
                            x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', @js($invoiceConfirmTitle), @js($invoiceConfirmMessage), '{{ route('admin.invoice.destroy', $invoice) }}', @js($invoiceConfirmButtonText), @js($invoiceCancelButtonText))"
                        ><i class="fa-solid fa-ban"></i></button>
                    @else
                        <button
                            type="button"
                            class="inline-flex items-center justify-center text-gray-300 transition disabled:cursor-not-allowed disabled:pointer-events-none"
                            title="{{ $cancelBlockReason ?? 'Cannot cancel invoice' }}"
                            disabled
                        ><i class="fa-solid fa-ban"></i></button>
                    @endif
                    <x-ui.button
                        type="submit"
                        color="primary-outline"
                        name="save_and_email"
                        value="1"
                        x-bind:disabled="!canSaveAndEmail()"
                    >
                        Save and Email
                    </x-ui.button>
                    <x-ui.button type="submit">Save</x-ui.button>
                </div>
            @else
                <div class="flex justify-end mt-8">
                    <x-ui.button type="submit">Save</x-ui.button>
                </div>
            @endif

        </form>
    </x-container>
</x-layout>
