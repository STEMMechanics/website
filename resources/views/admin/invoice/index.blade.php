<x-layout>
    <x-mast>Invoices</x-mast>

    <x-container>
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
                $statusBadgeClass = $invoice->displayStatusBadgeClass();
                $contentsSummary = $invoice->contentsSummary();
                $issuedDate = $invoice->issue_date?->format('M j, Y') ?? '-';
                $dueDate = $invoice->due_date?->format('M j, Y') ?? '-';
                $isOverdue = $invoice->isOverdue();
                $dueDateClass = $isOverdue ? 'text-rose-700 font-semibold' : 'text-gray-600';
                $settlementKind = $invoice->expectedSettlementKind();
                $allocated = (float) $invoice->allocations
                ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0)
                ->filter(fn ($allocation) => (string) ($allocation->customerPayment->kind ?? \App\Models\Payment::KIND_PAYMENT) === $settlementKind)
                ->sum('allocated_amount');
                $balance = (float) $invoice->outstandingAmount();
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
                        <span class="inline-flex items-center justify-center rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
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
                            <form method="POST" action="{{ route('admin.invoice.email', $invoice) }}">
                                @csrf
                                <button type="submit" class="hover:text-primary-color" title="Email Invoice PDF"><i class="fa-regular fa-envelope"></i></button>
                            </form>
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
                            @if((string) $invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                            <a href="#"
                                class="hover:text-red-600"
                                title="Delete Draft"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete draft invoice?', 'This will permanently delete this draft invoice. Continue?', '{{ route('admin.invoice.destroy', $invoice) }}')"><i class="fa-solid fa-trash"></i></a>
                            @else
                            <a href="#"
                                class="hover:text-red-600"
                                title="Cancel Invoice"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Cancel invoice?', 'This will cancel the invoice and keep it for audit records. Continue?', '{{ route('admin.invoice.destroy', $invoice) }}')"><i class="fa-solid fa-ban"></i></a>
                            @endif
                        </div>
                    </td>
                    </tr>
                @foreach(($invoice->taxAdjustments ?? collect())->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment)
                    @php
                        $adjustmentBadgeClass = 'border-slate-200 bg-slate-50 text-slate-700';
                    @endphp
                    <tr class="bg-gray-50">
                        <td class="text-center!">↳ {{ $adjustment->adjustment_number }}</td>
                        <td>
                            <div class="whitespace-nowrap">Tax Adjustment</div>
                            <div class="text-xs text-gray-600">{{ $invoice->user?->getName() ?? '-' }}</div>
                            <div class="md:hidden text-xs text-gray-600">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                        <td class="hidden md:table-cell text-center">
                            <span class="inline-flex items-center justify-center rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ $adjustmentBadgeClass }}">Tax Adjustment</span>
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

        {{ $invoices->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
