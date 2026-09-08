@php
    $savedLineItems = old('line_items_json');
    $isLocked = isset($invoice) && ! $invoice->canEditContents();
    $siteInvoiceEmailTemplate = app(\App\Services\InvoiceEmailTemplateService::class)->organisationDefaults();
    $userLookupOptions = collect($users ?? [])->map(function ($user) use ($siteInvoiceEmailTemplate) {
        $name = trim((string) $user->getName());
        $email = trim((string) ($user->email ?? ''));
        $company = trim((string) ($user->primaryOrganisation?->name ?? ''));

        $label = $name !== '' ? $name : $email;
        if ($company !== '') {
            $label .= ' - '.$company;
        }
        if ($email !== '') {
            $label .= ' ('.$email.')';
        }

        $siteTemplate = $siteInvoiceEmailTemplate;
        $organisation = $user->primaryOrganisation;
        $organisationHasTemplate = $organisation && collect([
            $organisation->invoice_email_to,
            $organisation->invoice_email_cc,
            $organisation->invoice_email_subject,
            $organisation->invoice_email_message,
        ])->contains(fn ($value) => trim((string) $value) !== '');

        return [
            'id' => (string) $user->id,
            'label' => $label,
            'account_terms_days' => $user->accountTermsDays(),
            'email_template' => $organisationHasTemplate ? [
                'recipient_emails' => html_entity_decode((string) $organisation->invoice_email_to, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'cc_emails' => html_entity_decode((string) $organisation->invoice_email_cc, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'subject_line' => html_entity_decode((string) $organisation->invoice_email_subject, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'email_message' => html_entity_decode((string) $organisation->invoice_email_message, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ] : $siteTemplate,
        ];
    })->values();
    $userLookupMap = $userLookupOptions->mapWithKeys(fn ($item) => [$item['label'] => $item['id']])->all();
    $selectedUserId = (string) old('user_id', isset($invoice) ? ($invoice->user_id ?? '') : '');
    $selectedUser = $userLookupOptions->first(fn ($item) => $item['id'] === $selectedUserId);
    $selectedUserLabel = is_array($selectedUser) ? ($selectedUser['label'] ?? '') : '';
    $selectedQuoteId = (string) old('quote_id', isset($invoice) ? ($invoice->quote_id ?? '') : '');
    $quoteLookupOptions = collect($quotes ?? [])->map(fn ($quote) => [
        'id' => (string) $quote->id,
        'label' => trim((string) $quote->quote_number).' - '.trim((string) ($quote->user?->getName() ?? $quote->user?->email ?? 'No user')),
        'edit_url' => route('admin.quote.edit', $quote),
    ])->values();
    $selectedQuoteOption = $quoteLookupOptions->first(fn ($item) => $item['id'] === $selectedQuoteId);
    $selectedQuoteLabel = is_array($selectedQuoteOption) ? (string) ($selectedQuoteOption['label'] ?? '') : '';
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
    $invoiceIsClosed = isset($invoice) && in_array((string) $invoice->status, [\App\Models\Invoice::STATUS_CANCELLED, \App\Models\Invoice::STATUS_WRITTEN_OFF], true);
    $invoiceRemainingAmount = $invoiceIsClosed
        ? 0.0
        : round(max(0, $invoiceDueAmount - $invoiceNetAllocatedAmount), 2);
    $invoiceCanAcceptPayment = isset($invoice)
        && ! in_array((string) $invoice->status, [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_CANCELLED, \App\Models\Invoice::STATUS_WRITTEN_OFF], true)
        && (float) $invoice->total_amount > 0
        && $invoiceRemainingAmount > 0.0001;
    $invoiceProgressPercent = isset($invoice) && $invoiceDueAmount > 0 && ! $invoiceIsClosed
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

    <div
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
        invoiceEmailTemplateOnly: false,
        selectedInvoiceUserId: @js($selectedUserId),
        invoiceEmailTemplatesByUser: @js($userLookupOptions->mapWithKeys(fn ($item) => [$item['id'] => $item['email_template']])->all()),
        newInvoiceEmailPayload() {
            const template = this.invoiceEmailTemplatesByUser[this.selectedInvoiceUserId] || @js(app(\App\Services\InvoiceEmailTemplateService::class)->organisationDefaults());
            return {
                action: '',
                invoice_number: 'New invoice',
                ...template,
            };
        },
        openInvoiceEmailModal(payload, templateOnly = false) {
            this.invoiceEmailAction = payload?.action || this.invoiceEmailAction || '';
            this.invoiceEmailInvoiceNumber = payload?.invoice_number || this.invoiceEmailInvoiceNumber || '';
            this.invoiceEmailRecipientEmails = payload?.recipient_emails || this.invoiceEmailRecipientEmails || '';
            this.invoiceEmailSubjectLine = payload?.subject_line || this.invoiceEmailSubjectLine || '';
            this.invoiceEmailCcEmails = payload?.cc_emails || '';
            this.invoiceEmailMessage = payload?.email_message || this.invoiceEmailMessage || '';
            this.invoiceEmailTemplateOnly = templateOnly;
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
        x-on:admin-linked-user-changed.window="selectedInvoiceUserId = String($event.detail?.userId || '')"
    >
        <x-mast backRoute="admin.invoice.index" backTitle="Invoices" :title="isset($invoice) ? 'Invoice '.$invoice->invoice_number : 'Create Invoice'">
            @if(isset($invoice))
                <x-slot:actions>
<div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                            <x-ui.button color="mast" type="button" x-data x-on:click.prevent="window.open('{{ route('admin.invoice.pdf', $invoice) }}', '_blank', 'noopener,noreferrer')" class="w-full sm:w-auto">Open PDF</x-ui.button>
                            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                            <x-ui.button color="mast" type="button" x-on:click.prevent="openInvoiceEmailModal({{ json_encode($invoiceEmailDefaultPayload) }})" class="w-full sm:w-auto">Email Invoice</x-ui.button>
                            @if($invoiceCanAcceptPayment)
                                <x-ui.button
                                    type="button"
                                    color="mast"
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
                                <x-ui.button color="mast" href="{{ route('admin.payment.create', ['invoice' => $invoice->invoice_number]) }}" class="w-full sm:w-auto">Record Payment</x-ui.button>
                            @endif
                            @endif
                        </div>
                </x-slot:actions>
            @endif
        </x-mast>
        <x-admin.invoice-email-modal :deferred="!isset($invoice)" form-id="invoice-edit-form" />

        <x-container class="py-5 sm:py-8">
        <div class="grid items-start gap-6 {{ isset($invoice) ? 'xl:grid-cols-[minmax(0,1fr)_23rem]' : '' }}">
        @isset($invoice)
            <aside class="min-w-0 space-y-5 xl:order-2">
            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
            <x-finance.panel title="Payments">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-4"><dt class="min-w-0 text-slate-600">Total:</dt> <dd class="shrink-0 whitespace-nowrap font-semibold tabular-nums">${{ number_format((float) $invoice->total_amount, 2) }}</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="min-w-0 text-slate-600">Due (after adjustments):</dt> <dd class="shrink-0 whitespace-nowrap font-semibold tabular-nums">${{ number_format($invoiceDueAmount, 2) }}</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="min-w-0 text-slate-600">Allocated:</dt> <dd class="shrink-0 whitespace-nowrap font-semibold tabular-nums">${{ number_format($invoiceGrossAllocatedAmount, 2) }}</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="min-w-0 text-slate-600">Refunded:</dt> <dd class="shrink-0 whitespace-nowrap font-semibold tabular-nums">${{ number_format($invoiceRefundedAmount, 2) }}</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="min-w-0 text-slate-600">Net Allocated:</dt> <dd class="shrink-0 whitespace-nowrap font-semibold tabular-nums">${{ number_format($invoiceNetAllocatedAmount, 2) }}</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="min-w-0 text-slate-600">Remaining:</dt> <dd class="shrink-0 whitespace-nowrap font-semibold tabular-nums">${{ number_format($invoiceRemainingAmount, 2) }}</dd></div>
                </dl>
                @if((string) $invoice->status === \App\Models\Invoice::STATUS_WRITTEN_OFF)
                    <div class="mt-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-800">
                        <div><strong>Written off:</strong> {{ $invoice->written_off_at?->format('M j, Y g:i a') ?? '-' }}</div>
                        <div class="mt-1 whitespace-pre-line"><strong>Reason:</strong> {{ $invoice->written_off_reason }}</div>
                    </div>
                @endif
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
                        <div class="space-y-3">
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

                    @endif
                </div>
                <div class="mt-6" id="tax-adjustments">
                    <div class="mb-3 flex flex-col items-start gap-3">
                        <h3 class="font-semibold">Tax Adjustment Notes</h3>
                        @if($isLocked)
                            <x-ui.button color="danger" href="{{ route('admin.tax_adjustment.create', ['invoice' => $invoice]) }}" class="w-full min-w-0 whitespace-normal px-3">Create Tax Adjustment Note</x-ui.button>
                        @endif
                    </div>
                    @if($invoiceAdjustments->isEmpty())
                        <div class="text-sm text-gray-500">No tax adjustment notes linked to this invoice yet.</div>
                    @else
                        <div class="overflow-x-auto">
                            <x-ui.table variant="plain" table-class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 pr-3">Document #</th>
                                        <th class="py-2 pr-3 text-center!">Issue Date</th>
                                        <th class="py-2 pr-3 text-center!">Total</th>
                                        <th class="text-center! py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoiceAdjustments as $adjustment)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 pr-3">{{ $adjustment->adjustment_number }}</td>
                                            <td class="py-2 pr-3 text-center!"><x-ui.date-time>{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></td>
                                            <td class="py-2 pr-3 text-center!">${{ number_format((float) $adjustment->total_amount, 2) }}</td>
                                            <td class="text-center! py-2">
                                                <x-ui.row-action label="Open tax adjustment" icon="fa-solid fa-up-right-from-square" tone="neutral" href="{{ route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.table>
                        </div>
                    @endif
                </div>
            </x-finance.panel>
            @endif
            @include('admin.invoice.allocation-panel')
            </aside>
        @endisset


        <form
            id="invoice-edit-form"
            class="min-w-0 xl:order-1"
            method="POST"
            action="{{ route('admin.invoice.' . (isset($invoice) ? 'update' : 'store'), $invoice ?? []) }}"
            x-data="{
                isLocked: @js($isLocked),
                invoiceStatus: @js((string) old('status', isset($invoice) ? ($invoice->status ?? \App\Models\Invoice::STATUS_DRAFT) : \App\Models\Invoice::STATUS_DRAFT)),
                issueNow: @js((bool) old('issue_now', false)),
                scheduledEmail: @js((bool) old('scheduled_email', isset($invoice) ? $invoice->scheduled_email : false)),
                scheduledSendModalOpen: false,
                scheduledSendNow: true,
                scheduledSendSubmitter: null,
                openScheduledSendModal(submitter) {
                    this.scheduledSendNow = true;
                    this.scheduledSendSubmitter = submitter || null;
                    this.scheduledSendModalOpen = true;
                },
                closeScheduledSendModal() {
                    this.scheduledSendModalOpen = false;
                    this.scheduledSendSubmitter = null;
                },
                confirmScheduledSend(form) {
                    this.$refs.sendScheduledNow.value = this.scheduledSendNow ? '1' : '0';
                    form.dataset.scheduledSendConfirmed = '1';
                    this.scheduledSendModalOpen = false;
                    if (this.scheduledSendSubmitter instanceof HTMLButtonElement) {
                        form.requestSubmit(this.scheduledSendSubmitter);
                        return;
                    }
                    form.requestSubmit();
                },
                canSaveAndEmail() {
                    return this.invoiceStatus !== @js(\App\Models\Invoice::STATUS_DRAFT) || this.issueNow;
                },
                issueDate: @js(old('issue_date', isset($invoice) && $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : now()->format('Y-m-d'))),
                dueDate: @js(old('due_date', isset($invoice) && $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '')),
                selectedUserId: @js($selectedUserId),
                selectedUserTermsDays: @js(is_array($selectedUser) ? (int) ($selectedUser['account_terms_days'] ?? 0) : 28),
                lineItems: (() => {
                    try {
                        const parsed = JSON.parse(@js($savedLineItems));
                        if (!Array.isArray(parsed)) {
                            return [];
                        }

                        return parsed.map((item) => SM.hydrateTravelLine({
                            id: item.id ?? null, source_type: item.source_type ?? null, source_id: item.source_id ?? null,
                            workshop_date: item.workshop_date ?? item.details_json?.workshop?.date ?? '',
                            travel_hours: item.travel_hours ?? '',
                            travel_units: item.travel_units ?? item.details_json?.travel?.billable_units ?? '',
                            legacy_workshop: item.kind === 'workshop' &amp;&amp; !item.workshop_hours &amp;&amp; !item.details_json?.workshop?.hours,
                            details_json: item.details_json || {},
                            workshop_hours: item.workshop_hours ?? item.details_json?.workshop?.hours ?? '',
                            workshop_seats: item.workshop_seats ?? item.details_json?.workshop?.seats ?? '',
                            supplied_categories: item.supplied_categories ?? item.details_json?.workshop?.supplied_categories ?? item.details_json?.travel?.supplied_categories ?? {},
                            venue_supplied: item.venue_supplied ?? item.details_json?.workshop?.venue_supplied ?? true,
                            kind: !item.kind || item.kind === 'generic' ? 'custom' : item.kind,
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
                            workshop_date: item.workshop_date ?? item.details_json?.workshop?.date ?? '',
                            travel_hours: item.travel_hours ?? '',
                            travel_units: item.travel_units ?? item.details_json?.travel?.billable_units ?? '',
                            details_json: item.details_json || {},
                            workshop_hours: item.workshop_hours ?? item.details_json?.workshop?.hours ?? '',
                            workshop_seats: item.workshop_seats ?? item.details_json?.workshop?.seats ?? '',
                            supplied_categories: item.supplied_categories ?? item.details_json?.workshop?.supplied_categories ?? item.details_json?.travel?.supplied_categories ?? {},
                            venue_supplied: item.venue_supplied ?? item.details_json?.workshop?.venue_supplied ?? true,
                            id: item.id ?? null, source_type: item.source_type ?? null, source_id: item.source_id ?? null,
                            kind: (item.kind || 'custom').trim() || 'custom',
                            description: (item.description || '').trim(),
                            notes: (item.notes || '').trim(),
                            quantity: parseFloat(item.quantity || 0),
                            unit_price: parseFloat(item.unit_price || 0),
                            gst_applicable: item.gst_applicable !== false,
                        }))
                        .filter((item) => item.description !== '' || item.notes !== '' || item.quantity > 0 || item.unit_price > 0);

                    this.$refs.lineItemsJson.value = JSON.stringify(cleaned);
                    this.$dispatch('invoice-lines-updated', { items: cleaned, total: Math.round(Number(this.subtotalAmountFormatted()) * 100) });
                },
                addLineItem() {
                    if (this.isLocked) {
                        return;
                    }
                    this.lineItems.push({ auto_pricing: true, kind: 'custom', workshop_date: '', legacy_workshop: false, travel_hours: '', travel_units: '', workshop_hours: '', workshop_seats: '', venue_supplied: true, supplied_categories: {}, details_json: {}, description: '', notes: '', quantity: 1, unit_price: 0, gst_applicable: true });
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
                    dueDate.setUTCDate(dueDate.getUTCDate() + this.selectedUserTermsDays);
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
                    return this.lineItems.reduce((total, item) => total + SM.lineAmounts(item).net, 0);
                },
                calculateGst() {
                    return this.lineItems.reduce((total, item) => total + SM.lineAmounts(item).tax, 0);
                },
                lineTotalExFormatted(item) {
                    return this.normalizeMoney(SM.lineAmounts(item).net);
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
                    organisation_name: '',
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
                    if (this.$refs.linkedUserId) this.$refs.linkedUserId.value = userId;
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
                        organisation_name: '',
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
            x-on:admin-linked-user-changed.window="
                selectedUserId = String($event.detail?.userId || '');
                selectedUserTermsDays = selectedUserId === '' ? 28 : Number($event.detail?.accountTermsDays || 0);
                setDueDateDefault(true);
            "
            x-on:submit="
                serializeLineItems();
                if (!$el.dataset.scheduledSendConfirmed) {
                    $refs.sendScheduledNow.value = '0';
                }
                if (scheduledEmail
                    && (issueDate < @js(today()->toDateString()) || (issueDate === @js(today()->toDateString()) && @js(now()->format('H:i') >= '08:00')))
                    && !$el.dataset.scheduledSendConfirmed
                ) {
                    $event.preventDefault();
                    openScheduledSendModal($event.submitter);
                }
            ">
            @isset($invoice)
                @method('PUT')
            @endisset
            @csrf

            <input type="hidden" name="send_scheduled_now" value="0" x-ref="sendScheduledNow" />
            <input type="hidden" name="line_items_json" x-ref="lineItemsJson" value="{{ $savedLineItems }}" />

            <div
                x-show="scheduledSendModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:keydown.escape.window="closeScheduledSendModal()"
            >
                <div class="w-full max-w-lg rounded-lg bg-white p-5 shadow-lg" x-on:click.outside="closeScheduledSendModal()">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Scheduled invoice</h3>
                            <p class="mt-2 text-sm text-gray-600">This invoice is scheduled to finalize today. As it is past the dispatch time, the invoice will be immediately finalized and sent to the person.</p>
                        </div>
                    </div>
                    <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <x-ui.checkbox
 label="Send invoice to person now"
 :noWrapper="true"
 :inline="true"
 x-model="scheduledSendNow"
 />
                    </div>
                    <x-ui.editor-actions>
                        <x-ui.button type="button" color="primary-outline" x-on:click.prevent="closeScheduledSendModal()">Cancel</x-ui.button>
                        <x-ui.button type="button" x-on:click.prevent="confirmScheduledSend($el.closest('form'))">Save</x-ui.button>
                    </x-ui.editor-actions>
                </div>
            </div>

            @if($isLocked)
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                    This document is issued and locked. To change amounts/items, create a tax adjustment note.
                </div>
            @endif
            <div class="grid gap-x-6 sm:grid-cols-2">
                <div class="flex-1">
                    <x-ui.input
                        label="Invoice Number"
                        name="invoice_number"
                        value="{{ old('invoice_number', isset($invoice) ? ($invoice->invoice_number ?? '') : ($nextInvoiceNumber ?? '')) }}"
                        :disabled="$isLocked"
                    />
                </div>
                <div class="mb-4 self-end rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm {{ $isLocked ? '' : 'sm:col-span-2' }}">
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
 x-bind:disabled="scheduledEmail"
 x-on:change="if (issueNow) scheduledEmail = false"
 />
                        <div class="mt-2">
                            <x-ui.checkbox
 name="scheduled_email"
 value="1"
 label="Schedule this draft to issue and email automatically at 8:00 am on its issue date"
 :checked="old('scheduled_email', isset($invoice) ? $invoice->scheduled_email : false)"
 :noWrapper="true"
 :inline="true"
 x-model="scheduledEmail"
 x-bind:disabled="issueNow"
 x-on:change="if (scheduledEmail) issueNow = false"
 />
                            <x-ui.button
                                type="button"
                                color="primary-outline-sm"
                                class="ml-6 mt-2"
                                x-show="scheduledEmail"
                                x-on:click.prevent="openInvoiceEmailModal({{ isset($invoice) ? json_encode($invoiceEmailDefaultPayload) : 'newInvoiceEmailPayload()' }}, true)"
                            >Edit scheduled email details</x-ui.button>
                        </div>
                    </div>
                @endif
            </div>

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
            <div
                class="mb-4"
                x-data="{
                    quoteLabel: @js($selectedQuoteLabel),
                    quoteId: @js($selectedQuoteId),
                    quoteOptions: @js($quoteLookupOptions->all()),
                    filteredQuotes: [],
                    quoteOpen: false,
                    selectedQuoteIndex: -1,
                    refreshQuotes() {
                        const needle = String(this.quoteLabel || '').toLowerCase().trim();
                        if (needle === '') {
                            this.filteredQuotes = [];
                            this.quoteOpen = false;
                            this.quoteId = '';
                            return;
                        }
                        const exact = this.quoteOptions.find((option) => option.label === this.quoteLabel);
                        this.quoteId = exact?.id || '';
                        this.filteredQuotes = this.quoteOptions.filter((option) => option.label.toLowerCase().includes(needle)).slice(0, 8);
                        this.selectedQuoteIndex = this.filteredQuotes.length ? 0 : -1;
                        this.quoteOpen = this.filteredQuotes.length > 0;
                    },
                    chooseQuote(option) {
                        this.quoteLabel = option.label;
                        this.quoteId = option.id;
                        this.quoteOpen = false;
                    },
                    moveQuote(step) {
                        if (!this.quoteOpen) { this.refreshQuotes(); return; }
                        const length = this.filteredQuotes.length;
                        if (length) this.selectedQuoteIndex = (this.selectedQuoteIndex + step + length) % length;
                    },
                    confirmQuote() {
                        if (this.quoteOpen && this.selectedQuoteIndex >= 0) this.chooseQuote(this.filteredQuotes[this.selectedQuoteIndex]);
                    },
                    openQuote() {
                        const option = this.quoteOptions.find((item) => item.id === this.quoteId);
                        if (option?.edit_url) window.open(option.edit_url, '_blank', 'noopener,noreferrer');
                    },
                }"
            >
                <div class="flex items-center justify-between">
                    <label for="invoice_linked_quote_lookup" class="block text-sm pl-1">Linked Quote</label>
                    <x-ui.button variant="plain"
                        type="button"
                        class="text-xs text-primary-color hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed"
                        x-bind:disabled="!quoteId"
                        x-on:click.prevent="openQuote()"
                        >
                        Open linked quote
                    </x-ui.button>
                </div>
                <div class="relative mt-1" x-on:click.away="quoteOpen = false">
                    <x-ui.input-control id="invoice_linked_quote_lookup" type="text" x-model="quoteLabel" x-on:focus="refreshQuotes()" x-on:input="refreshQuotes()" x-on:keydown.arrow-down.prevent="moveQuote(1)" x-on:keydown.arrow-up.prevent="moveQuote(-1)" x-on:keydown.enter.prevent="confirmQuote()" x-on:keydown.escape.prevent="quoteOpen = false" autocomplete="off" placeholder="Search quote number or owner" class="disabled:bg-gray-100 bg-white block px-2.5 py-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 border-gray-300 focus:border-indigo-300 focus:ring-indigo-300" />
                    <input type="hidden" name="quote_id" x-bind:value="quoteId">
                    <div x-show="quoteOpen" x-cloak class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-gray-300 bg-white shadow-lg">
                        <ul class="max-h-60 overflow-auto py-1">
                            <template x-for="(item, index) in filteredQuotes" :key="item.id">
                                <li class="cursor-pointer px-3 py-2 text-sm" x-bind:class="index === selectedQuoteIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-800 hover:bg-gray-100'" x-on:mouseenter="selectedQuoteIndex = index" x-on:mousedown.prevent="chooseQuote(item)" x-text="item.label"></li>
                            </template>
                        </ul>
                    </div>
                </div>
                <div class="text-xs text-gray-500 ml-2 mt-1">Can only link quotes for the same user.</div>
                @if($errors->has('quote_id'))
                    <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('quote_id') }}</div>
                @endif
            </div>

            <fieldset class="rounded-xl border border-slate-200 bg-white p-5" @if($isLocked) disabled @endif>

            <x-admin.user-selector-inline
                :users="$users ?? collect()"
                :selected-user-id="$selectedUserId"
                field-name="user_id"
                lookup-name="invoice_linked_user_lookup"
                label="Linked User"
                info="Search by name/organisation/email. Select a suggestion to link the invoice."
                :disabled="$isLocked"
            />

            <div class="grid gap-x-6 sm:grid-cols-2">
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
                                <a href="#" class="text-xs text-primary-color hover:underline" x-on:click.prevent="setDueDateDefault(true)">
                                    Use customer terms (<span x-text="selectedUserTermsDays === 0 ? 'Current' : '+' + selectedUserTermsDays + ' days'"></span>, next business day)
                                </a>
                            @endif
                        </div>
                        <x-ui.input-control
                            id="due_date"
                            type="date"
                            name="due_date"
                            x-model="dueDate"
                            value="{{ old('due_date', isset($invoice) && $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '') }}"
                            class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 {{ $errors->has('due_date') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}" />
                        @if($errors->has('due_date'))
                            <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('due_date') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 mb-4 border-y border-slate-200 py-5" x-init="serializeLineItems()">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="font-bold text-lg">Line Items</h3>
                </div>

                <template x-if="lineItems.length === 0">
                    <div class="text-sm text-gray-500">No line items yet.</div>
                </template>

                <x-ui.table variant="listing" table-class="min-w-[44rem] w-full">
                    <thead><tr><th>Description</th><th class="w-28 text-center whitespace-nowrap">HRS / QTY</th><th class="w-36 text-center whitespace-nowrap">Unit price (ex GST)</th><th class="w-16 text-center">GST</th><th class="w-28 text-center whitespace-nowrap">Total (inc GST)</th><th class="w-16 text-center">Actions</th></tr></thead>
                    <template x-for="(item, index) in lineItems" :key="index">
                        <tbody x-data="{ expanded: false }" class="[&>tr>td]:bg-white!">
                            <tr>
                                <td class="min-w-64">
                                    <div class="flex items-center gap-2">
                                        <x-ui.button href="#" role="button" variant="plain" class="flex h-11 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-sky-50 hover:text-primary-color" x-on:click.prevent="expanded = !expanded" x-on:keydown.space.prevent="expanded = !expanded" x-bind:aria-expanded="expanded" x-bind:aria-label="expanded ? 'Collapse details and notes' : 'Expand details and notes'">
                                            <i class="fa-solid text-sm" x-bind:class="expanded ? 'fa-chevron-down' : 'fa-chevron-right'" aria-hidden="true"></i>
                                        </x-ui.button>
                                    <x-ui.select label="Type" :noLabel="true" class="mb-0 min-w-0 flex-1" x-model="item.kind" x-on:change="if (item.kind === 'multi_workshop') { item.auto_pricing = true; expanded = true; if (!item.workshops?.length) SM.addWorkshopRow(item); } if (item.kind === 'travel') SM.hydrateTravelLine(item); SM.updateWorkshopLine(item); serializeLineItems()">
                                        <option value="workshop">Workshop Delivery</option><option value="multi_workshop">Multi Workshop Delivery</option><option value="travel">Travel Fee</option><option value="product">Store Product</option><option value="shipping">Shipping</option><option value="custom">Custom</option><option value="ticket">Ticket</option>
                                    </x-ui.select>
                                    </div>
                                </td>
                                <td><div class="relative"><x-ui.input-control aria-label="Hours or quantity" type="number" step="any" class="h-11 pr-11!" x-model="item.quantity" x-bind:readonly="item.kind === 'multi_workshop' || item.kind === 'workshop' &amp;&amp; !!item.workshop_hours &amp;&amp; !!item.workshop_seats" x-on:input="if (item.kind === 'travel') { item.travel_hours = item.quantity; SM.updateWorkshopLine(item); } serializeLineItems()" /><x-finance.line-refresh /></div></td>
                                <td><div class="relative"><span class="pointer-events-none absolute left-2 top-3">$</span><x-ui.input-control aria-label="Unit price excluding GST" type="number" step="0.01" class="h-11 pl-6! pr-11!" x-model="item.unit_price" x-on:input="item.auto_pricing = false; delete item.details_json.inclusive_unit_price; serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price')" /><x-finance.line-refresh :price="true" /></div></td>
                                <td class="text-center"><x-ui.checkbox :bare="true" :small="true" aria-label="GST applies" x-model="item.gst_applicable" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                                <td class="text-center whitespace-nowrap font-semibold">$<span x-text="normalizeMoney(SM.lineAmounts(item).net + SM.lineAmounts(item).tax)"></span></td>
                                <td class="text-center">@if(! $isLocked)<x-ui.row-action label="Remove line item" icon="fa-trash" tone="danger" x-on:click.prevent="removeLineItem(index)" />@endif</td>
                            </tr>
                            <tr x-show="expanded" x-cloak><td colspan="6" class="border-t-0! pt-0!">
                                <div class="ml-10">
                                <div class="mb-3"><x-ui.input label="Description" type="text" x-model="item.description" x-on:input="serializeLineItems()" /></div>
                                <x-finance.workshop-line-fields />
                                <div x-show="item.kind === 'workshop'" class="mt-3 max-w-xs"><x-ui.input label="Workshop date" type="date" x-model="item.workshop_date" x-on:change="serializeLineItems()" /></div>
                                <label class="mt-4 block text-sm">Line item notes</label>
                                <x-ui.textarea-control aria-label="Line item notes" x-bind:readonly="item.kind === 'multi_workshop'" rows="4" class="mt-2 w-full resize-y" x-model="item.notes" x-on:input="serializeLineItems()" />
                                </div>
                            </td></tr>
                        </tbody>
                    </template>
                </x-ui.table>
                @if(! $isLocked)
                    <div class="mt-4 flex justify-end">
                        <x-ui.button type="button" x-on:click.prevent="addLineItem()">Add Item</x-ui.button>
                    </div>
                @endif
            </div>

            <div class="grid gap-x-6 sm:grid-cols-2">
                <div class="min-w-0 sm:col-start-2">
                    <x-ui.input
                        type="text"
                        label="Subtotal (Ex GST, Auto)"
                        name="subtotal_amount_display"
                        x-bind:value="subtotalAmountFormatted()"
                        value="{{ old('subtotal_amount_display', isset($invoice) ? ($invoice->subtotal_amount ?? '0.00') : '0.00') }}"
                        readonly="true"
                    />
                </div>
                <div class="min-w-0 sm:col-start-2">
                    <x-ui.input
                        type="text"
                        label="GST Amount (Auto)"
                        name="gst_amount_display"
                        x-bind:value="gstAmountFormatted()"
                        value="{{ old('gst_amount_display', isset($invoice) ? ($invoice->gst_amount ?? '0.00') : '0.00') }}"
                        readonly="true"
                    />
                </div>

                <div class="min-w-0 sm:col-start-2">
                    <x-ui.input
                        type="text"
                        label="Total Amount (Auto, incl GST)"
                        name="total_amount_display"
                        x-bind:value="totalAmountFormatted()"
                        value="{{ old('total_amount_display', isset($invoice) ? ($invoice->total_amount ?? '0.00') : '0.00') }}"
                        readonly="true"
                    />
                </div>
            </div>

            </fieldset>
            <section class="mt-5">
                @empty($invoice)
                    <x-finance.invoice-allocation-preview />
                @endempty
            </section>

            <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
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

            </section>
            @if(isset($invoice))
                <x-ui.editor-actions>
                    @php
                        $isDraftInvoice = (string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT;
                        $cancelBlockReason = ! $isDraftInvoice ? $invoice->cancellationBlockedReason() : null;
                        $canCancelInvoice = $cancelBlockReason === null;
                        $writeOffBlockReason = ! $isDraftInvoice ? $invoice->writeOffBlockedReason() : null;
                        $canWriteOffInvoice = $writeOffBlockReason === null;
                        $invoiceConfirmTitle = $isDraftInvoice ? 'Delete draft invoice?' : 'Cancel invoice?';
                        $invoiceConfirmMessage = $isDraftInvoice
                            ? 'This will permanently delete this draft invoice. Continue?'
                            : 'Invoice cancellation is exceptional and should only be used when the invoice was issued in error.<br><br>For workshop no-shows, cancel the ticket instead so the tax adjustment note is created.<br>For store orders, cancel the linked order and handle any refund through the order flow.<br><br>Continue only if this invoice has no payments or downstream records.';
                        $invoiceConfirmButtonText = $isDraftInvoice ? 'Delete' : 'Cancel Invoice';
                        $invoiceCancelButtonText = $isDraftInvoice ? 'Cancel' : 'Keep Invoice';
                    @endphp
                    <div data-editor-delete class="flex flex-wrap items-center gap-3">
                        @if($isDraftInvoice)
                            <x-ui.button color="danger-outline"
                                type="button"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', {{ \Illuminate\Support\Js::from($invoiceConfirmTitle) }}, {{ \Illuminate\Support\Js::from($invoiceConfirmMessage) }}, '{{ route('admin.invoice.destroy', $invoice) }}', {{ \Illuminate\Support\Js::from($invoiceConfirmButtonText) }}, {{ \Illuminate\Support\Js::from($invoiceCancelButtonText) }})"
                             ><i class="fa-solid fa-trash mr-2" aria-hidden="true"></i>Delete Draft</x-ui.button>
                        @elseif($canCancelInvoice)
                            <x-ui.button color="yellow-outline"
                                type="button"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', {{ \Illuminate\Support\Js::from($invoiceConfirmTitle) }}, {{ \Illuminate\Support\Js::from($invoiceConfirmMessage) }}, '{{ route('admin.invoice.destroy', $invoice) }}', {{ \Illuminate\Support\Js::from($invoiceConfirmButtonText) }}, {{ \Illuminate\Support\Js::from($invoiceCancelButtonText) }})"
                             ><i class="fa-solid fa-ban mr-2" aria-hidden="true"></i>Cancel Invoice</x-ui.button>
                        @else
                            <x-ui.button color="yellow-outline"
                                type="button"
                                disabled
                             title="{{ $cancelBlockReason }}" ><i class="fa-solid fa-ban mr-2" aria-hidden="true"></i>Cancel Invoice</x-ui.button>
                        @endif
                        @if(! $isDraftInvoice)
                            @if($canWriteOffInvoice)
                                <x-ui.button color="secondary"
                                    type="button"
                                    x-on:click.prevent="SM.submitInvoiceWriteOff('{{ route('admin.invoice.write-off', $invoice) }}', '{{ csrf_token() }}')"
                                 ><i class="fa-solid fa-file-circle-minus mr-2" aria-hidden="true"></i>Write Off Invoice</x-ui.button>
                            @else
                                <x-ui.button color="secondary"
                                    type="button"
                                    disabled
                                 title="{{ $writeOffBlockReason }}" ><i class="fa-solid fa-file-circle-minus mr-2" aria-hidden="true"></i>Write Off Invoice</x-ui.button>
                            @endif
                        @endif
                    </div>
                    <div class="ml-auto flex flex-wrap justify-end gap-3">
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
                </x-ui.editor-actions>
            @else
                <x-ui.editor-actions>
                    <x-ui.button type="submit">Save</x-ui.button>
                </x-ui.editor-actions>
            @endif

        </form>
        </div>
        </x-container>
    </div>
    <x-ui.record-dialog />
    <script>
        window.SM = window.SM || {};
        window.SM.submitInvoiceWriteOff = function (action, csrfToken) {
            if (typeof Swal === 'undefined' || !Swal || typeof Swal.fire !== 'function') {
                return;
            }

            Swal.fire({
                position: 'top',
                icon: 'warning',
                iconColor: '#b91c1c',
                title: 'Write off invoice?',
                html: 'This clears the outstanding balance without cancelling linked tickets, orders, or attendance records. The invoice will no longer accept payments.',
                input: 'textarea',
                inputLabel: 'Write-off reason',
                inputPlaceholder: 'Reason this invoice is being written off',
                inputAttributes: {
                    maxlength: 1000
                },
                showCancelButton: true,
                confirmButtonText: 'Write Off Invoice',
                confirmButtonColor: '#b91c1c',
                cancelButtonText: 'Keep Invoice',
                reverseButtons: true,
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Enter a write-off reason.';
                    }

                    return undefined;
                }
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const form = document.createElement('form');
                const tokenInput = document.createElement('input');
                const reasonInput = document.createElement('input');

                form.method = 'POST';
                form.action = action;

                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = csrfToken;

                reasonInput.type = 'hidden';
                reasonInput.name = 'reason';
                reasonInput.value = result.value || '';

                form.appendChild(tokenInput);
                form.appendChild(reasonInput);
                document.body.appendChild(form);
                form.submit();
            });
        };
    </script>
</x-layout>
