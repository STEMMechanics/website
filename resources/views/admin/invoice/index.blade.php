<x-layout>
    <x-mast>Invoices
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.invoice.create') }}">Create</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="mt-4">
        <x-ui.dynamic-list name="admin-invoice-index">
        <x-finance.attention-notice kind="invoices" />

        <div
            x-data="{
            invoiceEmailModalOpen: {{ session('invoice-email-open', false) ? 'true' : 'false' }},
            invoiceEmailAction: {{ json_encode((string) session('invoice-email-action', '')) }},
            invoiceEmailInvoiceNumber: {{ json_encode((string) session('invoice-email-invoice-number', '')) }},
            invoiceEmailRecipientEmails: {{ json_encode((string) old('recipient_emails', session('invoice-email-recipient-emails', ''))) }},
            invoiceEmailSubjectLine: {{ json_encode((string) old('subject_line', session('invoice-email-subject-line', ''))) }},
            invoiceEmailCcEmails: {{ json_encode((string) old('cc_emails', session('invoice-email-cc-emails', ''))) }},
            invoiceEmailMessage: {{ json_encode((string) old('email_message', session('invoice-email-message', ''))) }},
            invoiceEmailTemplateOnly: false,
            invoiceEmailSubjectOpen: false,
            invoiceEmailCcOpen: false,
            invoiceEmailHelpOpen: false,
            openInvoiceEmailModal(payload) {
                this.invoiceEmailAction = payload?.action || '';
                this.invoiceEmailInvoiceNumber = payload?.invoice_number || '';
                this.invoiceEmailRecipientEmails = payload?.recipient_emails || '';
                this.invoiceEmailSubjectLine = payload?.subject_line || '';
                this.invoiceEmailCcEmails = payload?.cc_emails || '';
                this.invoiceEmailMessage = payload?.email_message || '';
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
        <x-ui.collection-controls class="my-5" />

        <x-ui.grid class="mb-4 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Outstanding</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ money((float) ($summaryOutstandingAmount ?? 0)) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Overdue</div>
                <div class="mt-1 text-2xl font-bold text-rose-700">{{ money((float) ($summaryOverdueAmount ?? 0)) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Draft / scheduled</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ money((float) ($summaryDraftAmount ?? 0)) }}</div>
            </div>
        </x-ui.grid>

        @if($invoices->isEmpty())
        <x-none-found item="invoices" search="{{ request()->get('search') }}" />
        @else
            <div class="mb-3 md:hidden"><x-ui.checkbox bare small data-invoice-select-all aria-label="Select invoices" /> <span class="text-sm">Select invoices</span></div>
            <div data-list-results class="space-y-4 md:hidden">
                @foreach ($invoices as $invoice)
                    @php
                        $statusLabel = $invoice->displayStatusLabel();
                        $statusTone = $invoice->displayStatusTone();
                        $contentsSummary = $invoice->contentsSummary();
                        $issuedDate = $invoice->issue_date?->format('M j, Y') ?? '-';
                        $dueDate = $invoice->due_date?->format('M j, Y') ?? '-';
                        $isOverdue = $invoice->isOverdue();
                        $dueDateClass = $isOverdue ? 'text-rose-700 font-semibold' : 'text-gray-600';
                        $settlementKind = $invoice->expectedSettlementKind();
                        $cancelBlockReason = $invoice->cancellationBlockedReason();
                        $canCancelInvoice = $cancelBlockReason === null;
                        $writeOffBlockReason = $invoice->writeOffBlockedReason();
                        $canWriteOffInvoice = $writeOffBlockReason === null;
                        $allocated = (float) $invoice->allocations
                            ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0)
                            ->filter(fn ($allocation) => (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $settlementKind)
                            ->sum('allocated_amount');
                        $balance = (float) $invoice->displayOutstandingAmount();
                        $canAcceptPayment = ! in_array((string) $invoice->status, [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_CANCELLED, \App\Models\Invoice::STATUS_WRITTEN_OFF], true)
                            && $balance > 0.0001
                            && (float) $invoice->total_amount > 0;
                        $isCreditDocument = ((float) $invoice->total_amount) < 0;
                        $invoiceEmailPayload = $invoiceEmailDefaults[(string) $invoice->id] ?? [];
                    @endphp
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <x-ui.checkbox bare small data-invoice-select :value="(string) $invoice->id" :aria-label="'Select invoice '.$invoice->invoice_number" />
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('admin.invoice.edit', $invoice) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $invoice->invoice_number }}</a>
                                @if(trim((string) ($invoice->title ?? '')) !== '')
                                    <div class="mt-1 text-xs text-gray-600">{{ $invoice->title }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-600">{{ $invoice->user?->getName() ?? '-' }}</div>
                            </div>
                            <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                        </div>

                        <div class="mt-3 space-y-2 text-xs">
                            <div class="text-gray-600">{{ $contentsSummary }}</div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-gray-600">Issued {{ $issuedDate }}</span>
                                <span class="{{ $dueDateClass }}">Due {{ $dueDate }}</span>
                            </div>
                        </div>

                        <x-ui.grid class="mt-3 gap-2 text-sm">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Amount</div>
                                <div class="mt-1 font-semibold text-gray-950">Total: ${{ number_format((float) $invoice->total_amount, 2) }}</div>
                                <div class="text-xs text-gray-600">GST: ${{ number_format($invoice->gst_amount, 2) }}</div>
                                <div class="text-xs text-gray-600">
                                    @if($isCreditDocument)
                                        Balance: <span class="font-medium text-indigo-700">Credit ${{ number_format($balance, 2) }}</span>
                                    @else
                                        Balance: ${{ number_format($balance, 2) }}
                                    @endif
                                </div>
                            </div>
                        </x-ui.grid>

                        <x-ui.row-actions class="mt-4">
                            <x-ui.row-action label="Edit invoice" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.invoice.edit', $invoice) }}" />
                            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                <x-ui.row-action label="Download PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.invoice.pdf', $invoice) }}" />
                                <x-ui.row-action label="Email Invoice PDF" icon="fa-regular fa-envelope" tone="neutral"
                                    type="button"
                                    x-data
                                    x-on:click.prevent="openInvoiceEmailModal({{ json_encode($invoiceEmailPayload) }})"
                                 />
                                @if($canAcceptPayment)
                                    <x-ui.row-action label="Copy Payment Link" icon="fa-solid fa-link" tone="neutral"
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
                                     />
                                @endif
                            @endif
                            @if((string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                                <x-ui.row-action label="Delete Draft" icon="fa-solid fa-trash" tone="danger"
                                    type="button"
                                    x-data
                                    x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete draft invoice?', 'This will permanently delete this draft invoice. Continue?', '{{ route('admin.invoice.destroy', $invoice) }}')"
                                 />
                            @else
                                @if($canCancelInvoice)
                                    @php
                                        $invoiceCancelWarning = 'Invoice cancellation is exceptional and should only be used when the invoice was issued in error.<br><br>For workshop no-shows, cancel the ticket instead so the tax adjustment note is created.<br>For store orders, cancel the linked order and handle any refund through the order flow.<br><br>Continue only if this invoice has no payments or downstream records.';
                                    @endphp
                                    <x-ui.row-action label="Cancel Invoice" icon="fa-solid fa-ban" tone="warning"
                                        type="button"
                                        x-data
                                        x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Cancel invoice?', {{ json_encode($invoiceCancelWarning) }}, '{{ route('admin.invoice.destroy', $invoice) }}', 'Cancel Invoice', 'Keep Invoice')"
                                     />
                                @else
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-gray-300" title="{{ $cancelBlockReason ?? 'Cannot cancel invoice' }}">
                                        <i class="fa-solid fa-ban"></i>
                                    </span>
                                @endif
                            @endif
                            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                @if($canWriteOffInvoice)
                                    <x-ui.row-action label="Write Off Invoice" icon="fa-solid fa-file-circle-minus" tone="neutral"
                                        type="button"
                                        x-on:click.prevent="SM.submitInvoiceWriteOff('{{ route('admin.invoice.write-off', $invoice) }}', '{{ csrf_token() }}')"
                                     />
                                @else
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-gray-300" title="{{ $writeOffBlockReason ?? 'Cannot write off invoice' }}">
                                        <i class="fa-solid fa-file-circle-minus"></i>
                                    </span>
                                @endif
                            @endif
                        </x-ui.row-actions>
                    </article>

                    @foreach(($invoice->taxAdjustments ?? collect())->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment)
                        <article class="ml-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900">↳ {{ $adjustment->adjustment_number }}</div>
                                    <div class="mt-1 text-xs text-gray-600">Tax Adjustment</div>
                                    <div class="mt-1 text-xs text-gray-600">{{ $invoice->user?->getName() ?? '-' }}</div>
                                    <div class="text-xs text-gray-600">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</div>
                                </div>
                                <x-ui.badge color="slate" size="xs">Tax Adjustment</x-ui.badge>
                            </div>
                            <div class="mt-3">
                                <div class="text-sm font-semibold text-gray-950">${{ number_format((float) $adjustment->total_amount, 2) }}</div>
                            </div>
                            <x-ui.row-actions class="mt-4">
                                <x-ui.row-action label="Open Tax Adjustment" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" />
                                <x-ui.row-action label="Download PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.tax_adjustment.pdf', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" />
                                <form method="POST" action="{{ route('admin.tax_adjustment.email', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}">
                                    @csrf
                                    <x-ui.row-action label="Email Tax Adjustment PDF" icon="fa-regular fa-envelope" tone="neutral" type="submit" />
                                </form>
                            </x-ui.row-actions>
                        </article>
                    @endforeach
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <th class="w-10"><x-ui.checkbox bare small data-invoice-select-all aria-label="Select invoices" /></th>
                        <x-ui.list-heading label="Invoice" />
                        <x-ui.list-heading field="invoice_number" label="Details" />
                        <x-ui.list-heading class="hidden md:table-cell text-center!" label="Status" />
                        <x-ui.list-heading field="issue_date" class="hidden md:table-cell text-center!" label="Issued / Due" />
                        <th class="text-center!">Amount <span class="font-normal text-xs whitespace-nowrap">(incl GST)</span></th>
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($invoices as $invoice)
                            @php
                                $statusLabel = $invoice->displayStatusLabel();
                                $statusTone = $invoice->displayStatusTone();
                                $contentsSummary = $invoice->contentsSummary();
                                $issuedDate = $invoice->issue_date?->format('M j, Y') ?? '-';
                                $dueDate = $invoice->due_date?->format('M j, Y') ?? '-';
                                $isOverdue = $invoice->isOverdue();
                                $dueDateClass = $isOverdue ? 'text-rose-700 font-semibold' : 'text-gray-600';
                                $settlementKind = $invoice->expectedSettlementKind();
                                $cancelBlockReason = $invoice->cancellationBlockedReason();
                                $canCancelInvoice = $cancelBlockReason === null;
                                $writeOffBlockReason = $invoice->writeOffBlockedReason();
                                $canWriteOffInvoice = $writeOffBlockReason === null;
                                $allocated = (float) $invoice->allocations
                                    ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0)
                                    ->filter(fn ($allocation) => (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $settlementKind)
                                    ->sum('allocated_amount');
                                $balance = (float) $invoice->displayOutstandingAmount();
                                $canAcceptPayment = ! in_array((string) $invoice->status, [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_CANCELLED, \App\Models\Invoice::STATUS_WRITTEN_OFF], true)
                                    && $balance > 0.0001
                                    && (float) $invoice->total_amount > 0;
                                $isCreditDocument = ((float) $invoice->total_amount) < 0;
                            @endphp
                            <tr>
                                <td><x-ui.checkbox bare small data-invoice-select :value="(string) $invoice->id" :aria-label="'Select invoice '.$invoice->invoice_number" /></td>
                                <td>
                                    <a href="{{ route('admin.invoice.edit', $invoice) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $invoice->invoice_number }}</a>
                                </td>
                                <td>
                                    <div>{{ $invoice->user?->getName() ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-600">{{ $contentsSummary }}</div>
                                </td>
                                <td class="hidden md:table-cell text-center!">
                                    <x-ui.badge :color="$statusTone">{{ $statusLabel }}</x-ui.badge>
                                </td>
                                <td class="hidden md:table-cell text-center!">
                                    <div class="flex flex-col items-center justify-center gap-1 whitespace-nowrap text-xs">
                                        <div class="text-gray-600">Issued <x-ui.date-time>{{ $issuedDate }}</x-ui.date-time></div>
                                        <div class="w-full border-t border-gray-200"></div>
                                        <div class="{{ $dueDateClass }}">Due <x-ui.date-time>{{ $dueDate }}</x-ui.date-time></div>
                                    </div>
                                </td>
                                <td class="text-center!">
                                    <div>Total: ${{ number_format((float) $invoice->total_amount, 2) }}</div>
                                    <div class="text-xs text-gray-600">GST: ${{ number_format($invoice->gst_amount, 2) }}</div>
                                    <div class="text-xs text-gray-600">
                                        @if($isCreditDocument)
                                            Balance: <span class="text-indigo-700 font-medium">Credit ${{ number_format($balance, 2) }}</span>
                                        @else
                                            Balance: ${{ number_format($balance, 2) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center!">
                                    <x-ui.row-actions class="whitespace-nowrap text-sm">
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.invoice.edit', $invoice) }}" />
                                        @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                            <x-ui.row-action label="Download PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.invoice.pdf', $invoice) }}" />
                                            @php
                                                $invoiceEmailPayload = $invoiceEmailDefaults[(string) $invoice->id] ?? [];
                                            @endphp
                                            <x-ui.row-action label="Email Invoice PDF" icon="fa-regular fa-envelope" tone="neutral"
                                                type="button"
                                                x-data
                                                x-on:click.prevent="openInvoiceEmailModal({{ json_encode($invoiceEmailPayload) }})"
                                             />
                                            @if($canAcceptPayment)
                                                <x-ui.row-action label="Copy Payment Link" icon="fa-solid fa-link" tone="neutral"
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
                                            " />
                                            @endif
                                        @endif
                                        @if((string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                                            <x-ui.row-action label="Delete Draft" icon="fa-solid fa-trash" tone="danger"
                                                type="button"
                                                x-data
                                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete draft invoice?', 'This will permanently delete this draft invoice. Continue?', '{{ route('admin.invoice.destroy', $invoice) }}')"
                                             />
                                        @else
                                            @if($canCancelInvoice)
                                                @php
                                                    $invoiceCancelWarning = 'Invoice cancellation is exceptional and should only be used when the invoice was issued in error.<br><br>For workshop no-shows, cancel the ticket instead so the tax adjustment note is created.<br>For store orders, cancel the linked order and handle any refund through the order flow.<br><br>Continue only if this invoice has no payments or downstream records.';
                                                @endphp
                                                <x-ui.row-action label="Cancel Invoice" icon="fa-solid fa-ban" tone="warning"
                                                    type="button"
                                                    x-data
                                                    x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Cancel invoice?', {{ json_encode($invoiceCancelWarning) }}, '{{ route('admin.invoice.destroy', $invoice) }}', 'Cancel Invoice', 'Keep Invoice')"
                                                 />
                                            @else
                                                <x-ui.row-action label="{{ $cancelBlockReason ?? 'Cannot cancel invoice' }}" icon="fa-solid fa-ban" tone="warning"
                                                    type="button"
                                                    disabled
                                                 />
                                            @endif
                                        @endif
                                        @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                            @if($canWriteOffInvoice)
                                                <x-ui.row-action label="Write Off Invoice" icon="fa-solid fa-file-circle-minus" tone="neutral"
                                                    type="button"
                                                    x-on:click.prevent="SM.submitInvoiceWriteOff('{{ route('admin.invoice.write-off', $invoice) }}', '{{ csrf_token() }}')"
                                                 />
                                            @else
                                                <x-ui.row-action label="{{ $writeOffBlockReason ?? 'Cannot write off invoice' }}" icon="fa-solid fa-file-circle-minus" tone="neutral"
                                                    type="button"
                                                    disabled
                                                 />
                                            @endif
                                        @endif
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                            @foreach(($invoice->taxAdjustments ?? collect())->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment)
                                <tr class="bg-gray-50"><td></td>
                                    <td class="text-center!">↳ {{ $adjustment->adjustment_number }}</td>
                                    <td>
                                        <div class="whitespace-nowrap">Tax Adjustment</div>
                                        <div class="text-xs text-gray-600">{{ $invoice->user?->getName() ?? '-' }}</div>
                                        <div class="md:hidden text-xs text-gray-600"><x-ui.date-time>{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></div>
                                    </td>
                                    <td class="hidden md:table-cell text-center!">
                                        <x-ui.badge color="slate">Tax Adjustment</x-ui.badge>
                                    </td>
                                    <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></td>
                                    <td class="text-center!">${{ number_format((float) $adjustment->total_amount, 2) }}</td>
                                    <td class="text-center!">
                                        <x-ui.row-actions class="whitespace-nowrap text-sm">
                                            <x-ui.row-action label="Open Tax Adjustment" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" />
                                            <x-ui.row-action label="Download PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.tax_adjustment.pdf', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" />
                                            <form method="POST" action="{{ route('admin.tax_adjustment.email', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}">
                                                @csrf
                                                <x-ui.row-action label="Email Tax Adjustment PDF" icon="fa-regular fa-envelope" tone="neutral" type="submit" />
                                            </form>
                                        </x-ui.row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

        <x-ui.list-pagination :paginator="$invoices" />
        <div class="mt-3 flex flex-wrap justify-end gap-2">
            <form id="admin-invoice-bulk-form" method="POST" action="{{ route('admin.allocation-overrides.edit', ['kind' => 'invoices']) }}" data-bulk-open="finance-bulk-editor">
                @csrf
                <div data-bulk-inputs></div>
                <x-ui.bulk-edit-button type="submit" :count="0" disabled />
            </form>
            <x-ui.button color="secondary" data-invoice-allocate data-record-editor href="{{ route('admin.invoice.bulk-allocation.preview') }}">Allocate 0 invoices</x-ui.button></div>
        @endif

        <x-admin.invoice-email-modal />
        </div>

        </x-ui.dynamic-list>
    </x-container>

    <x-ui.record-dialog />
<x-ui.bulk-editor id="finance-bulk-editor" title="Bulk edit invoices" loader-id="finance-bulk-loader" list="admin-invoice-index" selection-key="admin-invoice-allocation-selection" selection-field="ids[]" />
</x-layout>

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
