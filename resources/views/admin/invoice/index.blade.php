<x-layout>
    <x-mast>Invoices</x-mast>

    <x-container class="mt-4">
        <div
            x-data="{
            invoiceEmailModalOpen: {{ session('invoice-email-open', false) ? 'true' : 'false' }},
            invoiceEmailAction: {{ json_encode((string) session('invoice-email-action', '')) }},
            invoiceEmailInvoiceNumber: {{ json_encode((string) session('invoice-email-invoice-number', '')) }},
            invoiceEmailRecipientEmails: {{ json_encode((string) old('recipient_emails', session('invoice-email-recipient-emails', ''))) }},
            invoiceEmailSubjectLine: {{ json_encode((string) old('subject_line', session('invoice-email-subject-line', ''))) }},
            invoiceEmailCcEmails: {{ json_encode((string) old('cc_emails', session('invoice-email-cc-emails', ''))) }},
            invoiceEmailMessage: {{ json_encode((string) old('email_message', session('invoice-email-message', ''))) }},
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
        <x-ui.toolbar break="md">
            <x-slot:left class="flex-0">
                <x-ui.button href="{{ route('admin.invoice.create') }}" class="w-full md:w-auto">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <div class="flex gap-3 flex-col md:flex-row">
                    <form method="GET" action="{{ route('admin.invoice.index') }}">
                        <input type="hidden" name="search" value="{{ request()->query('search', '') }}">
                        <x-ui.select
                            name="status"
                            label="Status"
                            inline-label
                            class="mb-0"
                            select-class="mt-0 md:min-w-38"
                            onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            @foreach(\App\Models\Invoice::STATUSES as $invoiceStatus)
                                <option value="{{ $invoiceStatus }}" @selected(request()->query('status', '') === $invoiceStatus)>{{ \App\Models\Invoice::statusLabel($invoiceStatus) }}</option>
                            @endforeach
                        </x-ui.select>
                    </form>
                    <x-ui.search name="search" label="Search" class="w-full sm:flex-1" />
                </div>
            </x-slot:right>
        </x-ui.toolbar>

        <div class="mb-4 flex flex-rpw gap-3">
            <div class="flex-1 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Still outstanding</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ money((float) ($summaryOutstandingAmount ?? 0)) }}</div>
            </div>
            <div class="flex-1 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Overdue</div>
                <div class="mt-1 text-2xl font-bold text-rose-700">{{ money((float) ($summaryOverdueAmount ?? 0)) }}</div>
            </div>
        </div>

        @if($invoices->isEmpty())
        <x-none-found item="invoices" search="{{ request()->get('search') }}" />
        @else
            <div class="space-y-4 md:hidden">
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

                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
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
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.invoice.edit', $invoice) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Edit invoice">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span class="sr-only">Edit invoice</span>
                            </a>
                            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                <a href="{{ route('admin.invoice.pdf', $invoice) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Download PDF">
                                    <i class="fa-regular fa-file-pdf"></i>
                                    <span class="sr-only">Download PDF</span>
                                </a>
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                    title="Email Invoice PDF"
                                    x-data
                                    x-on:click.prevent="openInvoiceEmailModal({{ json_encode($invoiceEmailPayload) }})"
                                >
                                    <i class="fa-regular fa-envelope"></i>
                                    <span class="sr-only">Email Invoice PDF</span>
                                </button>
                                @if($canAcceptPayment)
                                    <a href="#"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                        title="Copy Payment Link"
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
                                    >
                                        <i class="fa-solid fa-link"></i>
                                        <span class="sr-only">Copy Payment Link</span>
                                    </a>
                                @endif
                            @endif
                            @if((string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600"
                                    title="Delete Draft"
                                    x-data
                                    x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete draft invoice?', 'This will permanently delete this draft invoice. Continue?', '{{ route('admin.invoice.destroy', $invoice) }}')"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                    <span class="sr-only">Delete Draft</span>
                                </button>
                            @else
                                @if($canCancelInvoice)
                                    @php
                                        $invoiceCancelWarning = 'Invoice cancellation is exceptional and should only be used when the invoice was issued in error.<br><br>For workshop no-shows, cancel the ticket instead so the tax adjustment note is created.<br>For store orders, cancel the linked order and handle any refund through the order flow.<br><br>Continue only if this invoice has no payments or downstream records.';
                                    @endphp
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600"
                                        title="Cancel Invoice"
                                        x-data
                                        x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Cancel invoice?', {{ json_encode($invoiceCancelWarning) }}, '{{ route('admin.invoice.destroy', $invoice) }}', 'Cancel Invoice', 'Keep Invoice')"
                                    >
                                        <i class="fa-solid fa-ban"></i>
                                        <span class="sr-only">Cancel Invoice</span>
                                    </button>
                                @else
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-gray-300" title="{{ $cancelBlockReason ?? 'Cannot cancel invoice' }}">
                                        <i class="fa-solid fa-ban"></i>
                                    </span>
                                @endif
                            @endif
                            @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                @if($canWriteOffInvoice)
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-amber-50 hover:text-amber-700"
                                        title="Write Off Invoice"
                                        x-on:click.prevent="SM.submitInvoiceWriteOff('{{ route('admin.invoice.write-off', $invoice) }}', '{{ csrf_token() }}')"
                                    >
                                        <i class="fa-solid fa-file-circle-minus"></i>
                                        <span class="sr-only">Write Off Invoice</span>
                                    </button>
                                @else
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-gray-300" title="{{ $writeOffBlockReason ?? 'Cannot write off invoice' }}">
                                        <i class="fa-solid fa-file-circle-minus"></i>
                                    </span>
                                @endif
                            @endif
                        </div>
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
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open Tax Adjustment">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span class="sr-only">Open Tax Adjustment</span>
                                </a>
                                <a href="{{ route('admin.tax_adjustment.pdf', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Download PDF">
                                    <i class="fa-regular fa-file-pdf"></i>
                                    <span class="sr-only">Download PDF</span>
                                </a>
                                <form method="POST" action="{{ route('admin.tax_adjustment.email', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Email Tax Adjustment PDF">
                                        <i class="fa-regular fa-envelope"></i>
                                        <span class="sr-only">Email Tax Adjustment PDF</span>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table>
                    <x-slot:header>
                        <th>Invoice</th>
                        <th>Details</th>
                        <th class="hidden md:table-cell text-center">Status</th>
                        <th class="hidden md:table-cell text-center">Issued / Due</th>
                        <th>Amount <span class="font-normal text-xs whitespace-nowrap">(incl GST)</span></th>
                        <th class="text-center">Actions</th>
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
                                <td>
                                    <a href="{{ route('admin.invoice.edit', $invoice) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $invoice->invoice_number }}</a>
                                </td>
                                <td>
                                    <div>{{ $invoice->user?->getName() ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-600">{{ $contentsSummary }}</div>
                                </td>
                                <td class="hidden md:table-cell text-center">
                                    <x-ui.badge :color="$statusTone">{{ $statusLabel }}</x-ui.badge>
                                </td>
                                <td class="hidden md:table-cell text-center">
                                    <div class="flex flex-col items-center justify-center gap-1 whitespace-nowrap text-xs">
                                        <div class="text-gray-600">Issued {{ $issuedDate }}</div>
                                        <div class="w-full border-t border-gray-200"></div>
                                        <div class="{{ $dueDateClass }}">Due {{ $dueDate }}</div>
                                    </div>
                                </td>
                                <td>
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
                                <td>
                                    <div class="flex justify-center sm:justify-center gap-2 sm:gap-3 whitespace-nowrap text-sm">
                                        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                        @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                            <a href="{{ route('admin.invoice.pdf', $invoice) }}" class="hover:text-primary-color" title="Download PDF"><i class="fa-regular fa-file-pdf"></i></a>
                                            @php
                                                $invoiceEmailPayload = $invoiceEmailDefaults[(string) $invoice->id] ?? [];
                                            @endphp
                                            <button
                                                type="button"
                                                class="hover:text-primary-color"
                                                title="Email Invoice PDF"
                                                x-data
                                                x-on:click.prevent="openInvoiceEmailModal({{ json_encode($invoiceEmailPayload) }})"
                                            ><i class="fa-regular fa-envelope"></i></button>
                                            @if($canAcceptPayment)
                                                <a href="#"
                                                    class="hover:text-primary-color"
                                                    title="Copy Payment Link"
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
                                            "><i class="fa-solid fa-link"></i></a>
                                            @endif
                                        @endif
                                        @if((string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center text-gray-500 transition hover:text-red-600 disabled:cursor-not-allowed disabled:text-gray-300 disabled:pointer-events-none"
                                                title="Delete Draft"
                                                x-data
                                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete draft invoice?', 'This will permanently delete this draft invoice. Continue?', '{{ route('admin.invoice.destroy', $invoice) }}')"
                                            ><i class="fa-solid fa-trash"></i></button>
                                        @else
                                            @if($canCancelInvoice)
                                                @php
                                                    $invoiceCancelWarning = 'Invoice cancellation is exceptional and should only be used when the invoice was issued in error.<br><br>For workshop no-shows, cancel the ticket instead so the tax adjustment note is created.<br>For store orders, cancel the linked order and handle any refund through the order flow.<br><br>Continue only if this invoice has no payments or downstream records.';
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center justify-center text-gray-500 transition hover:text-red-600 disabled:cursor-not-allowed disabled:text-gray-300 disabled:pointer-events-none"
                                                    title="Cancel Invoice"
                                                    x-data
                                                    x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Cancel invoice?', {{ json_encode($invoiceCancelWarning) }}, '{{ route('admin.invoice.destroy', $invoice) }}', 'Cancel Invoice', 'Keep Invoice')"
                                                ><i class="fa-solid fa-ban"></i></button>
                                            @else
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center justify-center text-gray-300 transition disabled:cursor-not-allowed disabled:pointer-events-none"
                                                    title="{{ $cancelBlockReason ?? 'Cannot cancel invoice' }}"
                                                    disabled
                                                ><i class="fa-solid fa-ban"></i></button>
                                            @endif
                                        @endif
                                        @if((string) $invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                            @if($canWriteOffInvoice)
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center justify-center text-gray-500 transition hover:text-amber-700 disabled:cursor-not-allowed disabled:text-gray-300 disabled:pointer-events-none"
                                                    title="Write Off Invoice"
                                                    x-on:click.prevent="SM.submitInvoiceWriteOff('{{ route('admin.invoice.write-off', $invoice) }}', '{{ csrf_token() }}')"
                                                ><i class="fa-solid fa-file-circle-minus"></i></button>
                                            @else
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center justify-center text-gray-300 transition disabled:cursor-not-allowed disabled:pointer-events-none"
                                                    title="{{ $writeOffBlockReason ?? 'Cannot write off invoice' }}"
                                                    disabled
                                                ><i class="fa-solid fa-file-circle-minus"></i></button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @foreach(($invoice->taxAdjustments ?? collect())->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment)
                                <tr class="bg-gray-50">
                                    <td class="text-center!">↳ {{ $adjustment->adjustment_number }}</td>
                                    <td>
                                        <div class="whitespace-nowrap">Tax Adjustment</div>
                                        <div class="text-xs text-gray-600">{{ $invoice->user?->getName() ?? '-' }}</div>
                                        <div class="md:hidden text-xs text-gray-600">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</div>
                                    </td>
                                    <td class="hidden md:table-cell text-center">
                                        <x-ui.badge color="slate">Tax Adjustment</x-ui.badge>
                                    </td>
                                    <td class="hidden md:table-cell text-center">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</td>
                                    <td>${{ number_format((float) $adjustment->total_amount, 2) }}</td>
                                    <td>
                                        <div class="flex justify-center sm:justify-center gap-2 sm:gap-3 whitespace-nowrap text-sm">
                                            <a href="{{ route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" class="hover:text-primary-color" title="Open Tax Adjustment">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="{{ route('admin.tax_adjustment.pdf', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}" class="hover:text-primary-color" title="Download PDF">
                                                <i class="fa-regular fa-file-pdf"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.tax_adjustment.email', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]) }}">
                                                @csrf
                                                <button type="submit" class="hover:text-primary-color" title="Email Tax Adjustment PDF">
                                                    <i class="fa-regular fa-envelope"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

        {{ $invoices->appends(request()->query())->links() }}
        @endif

        <x-admin.invoice-email-modal />
        </div>
    </x-container>
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
