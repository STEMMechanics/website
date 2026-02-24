<x-layout>
    <x-mast>My Invoices</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($invoices->isEmpty())
        <x-none-found item="invoices" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table>
            <x-slot:header>
                <th class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;">Invoice #</th>
                <th>Details</th>
                <th class="hidden md:table-cell">Status</th>
                <th class="hidden md:table-cell">Issue Date</th>
                <th>Amount</th>
                <th>Actions</th>
            </x-slot:header>
            <x-slot:body>
                @foreach ($invoices as $invoice)
                @php
                $outstanding = (float) $invoice->outstandingAmount();
                $isCreditDocument = ((float) $invoice->total_amount) < 0;
                    @endphp
                    <tr>
                    <td>
                        <div class="whitespace-nowrap">{{ $invoice->invoice_number }}</div>
                        <div class="md:hidden text-xs text-gray-600 mt-1 capitalize">{{ $invoice->status }}</div>
                    </td>
                    <td>
                        <div>{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</div>
                        @if(($invoice->taxAdjustments?->count() ?? 0) > 0)
                        <div class="text-xs text-gray-600 mt-1">{{ $invoice->taxAdjustments->count() }} adjustment{{ $invoice->taxAdjustments->count() === 1 ? '' : 's' }}</div>
                        @endif
                    </td>
                    <td class="hidden md:table-cell capitalize">{{ $invoice->status }}</td>
                    <td class="hidden md:table-cell">{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</td>
                    <td>
                        <div>Total: ${{ number_format((float) $invoice->total_amount, 2) }}</div>
                        <div class="text-xs text-gray-600">
                            @if($isCreditDocument)
                            Outstanding: <span class="text-indigo-700 font-medium">Credit ${{ number_format((float) $outstanding, 2) }}</span>
                            @else
                            Outstanding: ${{ number_format((float) $outstanding, 2) }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex justify-center gap-3 whitespace-nowrap">
                            @if(!$isCreditDocument && $outstanding > 0.0001)
                            <a href="{{ route('account.invoice.show', $invoice) }}" class="hover:text-primary-color" title="View / Pay Invoice"><i class="fa-solid fa-credit-card"></i></a>
                            @else
                            <a href="{{ route('account.invoice.show', $invoice) }}" class="hover:text-primary-color" title="View Invoice"><i class="fa-regular fa-eye"></i></a>
                            @endif
                            <a href="{{ route('account.invoice.receipts', $invoice) }}" class="hover:text-primary-color" title="View Invoice Payments"><i class="fa-solid fa-receipt"></i></a>
                            <a href="{{ route('account.invoice.pdf', $invoice) }}" class="hover:text-primary-color" title="Open PDF" target="_blank"><i class="fa-regular fa-file-pdf"></i></a>
                        </div>
                    </td>
                    </tr>
                    @foreach(($invoice->taxAdjustments ?? collect())->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment)
                    <tr class="bg-gray-50">
                        <td>
                            <div class="whitespace-nowrap">↳ {{ $adjustment->adjustment_number }}</div>
                        </td>
                        <td>
                            <div>Tax Adjustment</div>
                            <div class="text-xs text-gray-600">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                        <td class="hidden md:table-cell">Tax Adjustment</td>
                        <td class="hidden md:table-cell">{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</td>
                        <td>${{ number_format((float) $adjustment->total_amount, 2) }}</td>
                        <td>
                            <div class="flex justify-center gap-3 whitespace-nowrap">
                                <a href="{{ route('account.invoice.pdf', $invoice) }}" class="hover:text-primary-color" title="Open Invoice PDF" target="_blank"><i class="fa-regular fa-file-pdf"></i></a>
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
