<x-layout>
    <x-mast>Invoices</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.invoice.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($invoices->isEmpty())
        <x-none-found item="invoices" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table>
            <x-slot:header>
                <th>Invoice #</th>
                <th>Details</th>
                <th class="hidden md:table-cell">Status</th>
                <th class="hidden md:table-cell">Issue Date</th>
                <th>Amount <span class="font-normal text-xs">(incl GST)</span></th>
                <th class="text-center">Actions</th>
            </x-slot:header>
            <x-slot:body>
                @foreach ($invoices as $invoice)
                @php
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
                        <div class="md:hidden text-xs text-gray-600 mt-1">{{ \App\Models\Invoice::statusLabel((string) $invoice->status) }}</div>
                        <div class="md:hidden text-xs text-gray-600">{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</div>
                    </td>
                    <td class="hidden md:table-cell">{{ \App\Models\Invoice::statusLabel((string) $invoice->status) }}</td>
                    <td class="hidden md:table-cell">{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</td>
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
                    <tr class="bg-gray-50">
                        <td>↳ {{ $adjustment->adjustment_number }}</td>
                        <td>
                            <div>Tax Adjustment</div>
                            <div class="text-xs text-gray-600">{{ $invoice->user?->getName() ?? '-' }}</div>
                            <div class="md:hidden text-xs text-gray-600">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                        <td class="hidden md:table-cell">Tax Adjustment</td>
                        <td class="hidden md:table-cell">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</td>
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
