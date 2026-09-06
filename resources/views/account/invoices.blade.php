<x-layout>
    <x-mast>My Invoices</x-mast>

    <x-container>
        <x-ui.dynamic-list name="account-invoices">

        <x-ui.collection-controls class="my-5" />

        @if($invoices->isEmpty())
        <x-none-found item="invoices" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table variant="listing">
            <x-slot:header>
                <x-ui.list-heading field="invoice_number" class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;" label="Invoice #" />
                <x-ui.list-heading field="issue_date" label="Details" />
                <x-ui.list-heading class="hidden md:table-cell text-center!" label="Status" />
                <x-ui.list-heading field="issue_date" class="hidden md:table-cell text-center!" label="Issue Date" />
                <x-ui.list-heading class="text-center!" label="Amount" />
                <x-ui.list-heading class="text-center!" label="Actions" />
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
                        <div><x-ui.date-time>{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></div>
                        @if(($invoice->taxAdjustments?->count() ?? 0) > 0)
                        <div class="text-xs text-gray-600 mt-1">{{ $invoice->taxAdjustments->count() }} adjustment{{ $invoice->taxAdjustments->count() === 1 ? '' : 's' }}</div>
                        @endif
                    </td>
                    <td class="hidden md:table-cell capitalize text-center!">{{ $invoice->status }}</td>
                    <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></td>
                    <td class="text-center!">
                        <div>Total: ${{ number_format((float) $invoice->total_amount, 2) }}</div>
                        <div class="text-xs text-gray-600">
                            @if($isCreditDocument)
                            Outstanding: <span class="text-indigo-700 font-medium">Credit ${{ number_format((float) $outstanding, 2) }}</span>
                            @else
                            Outstanding: ${{ number_format((float) $outstanding, 2) }}
                            @endif
                        </div>
                    </td>
                    <td class="text-center!">
                        <x-ui.row-actions class="whitespace-nowrap">
                            @if(!$isCreditDocument && $outstanding > 0.0001)
                            <x-ui.row-action label="View / Pay Invoice" icon="fa-solid fa-credit-card" tone="neutral" href="{{ route('account.invoice.show', $invoice) }}" />
                            @else
                            <x-ui.row-action label="View Invoice" icon="fa-regular fa-eye" tone="neutral" href="{{ route('account.invoice.show', $invoice) }}" />
                            @endif
                            <x-ui.row-action label="View Invoice Payments" icon="fa-solid fa-receipt" tone="neutral" href="{{ route('account.invoice.receipts', $invoice) }}" />
                            <x-ui.row-action label="Open PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('account.invoice.pdf', $invoice) }}" target="_blank" />
                        </x-ui.row-actions>
                    </td>
                    </tr>
                    @foreach(($invoice->taxAdjustments ?? collect())->sortByDesc(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment)
                    <tr class="bg-gray-50">
                        <td>
                            <div class="whitespace-nowrap">↳ {{ $adjustment->adjustment_number }}</div>
                        </td>
                        <td>
                            <div>Tax Adjustment</div>
                            <div class="text-xs text-gray-600"><x-ui.date-time>{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></div>
                        </td>
                        <td class="hidden md:table-cell text-center!">Tax Adjustment</td>
                        <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ $adjustment->issue_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></td>
                        <td class="text-center!">${{ number_format((float) $adjustment->total_amount, 2) }}</td>
                        <td class="text-center!">
                            <x-ui.row-actions class="whitespace-nowrap">
                                <x-ui.row-action label="Open Invoice PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('account.invoice.pdf', $invoice) }}" target="_blank" />
                            </x-ui.row-actions>
                        </td>
                    </tr>
                    @endforeach
                    @endforeach
            </x-slot:body>
        </x-ui.table>

        <x-ui.list-pagination :paginator="$invoices" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
