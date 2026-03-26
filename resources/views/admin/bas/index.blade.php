<x-layout>
    <x-mast>BAS Report</x-mast>

    <x-container>
        <form method="GET" action="{{ route('admin.bas.index') }}" class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-auto">
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Reporting Month</label>
                <input
                    id="month"
                    name="month"
                    type="month"
                    value="{{ $selectedMonth }}"
                    class="rounded-md border-gray-300 shadow-sm focus:border-primary-color focus:ring-primary-color"
                    required
                />
                <div class="mt-1 text-xs text-gray-500">Format: YYYY-MM (example: 2026-02)</div>
            </div>
            <div>
                <x-ui.button type="submit">Run Report</x-ui.button>
            </div>
            <div class="text-sm text-gray-600">
                Period: {{ $periodStart->format('M j, Y') }} - {{ $periodEnd->format('M j, Y') }}
            </div>
            <div class="w-full sm:w-auto sm:ml-auto flex flex-wrap gap-2">
                <x-ui.button href="{{ route('admin.bas.export.csv', ['month' => $selectedMonth]) }}" color="outline">Export CSV</x-ui.button>
                <x-ui.button href="{{ route('admin.bas.export.pdf', ['month' => $selectedMonth]) }}" color="outline" target="_blank">Export PDF</x-ui.button>
                <x-ui.button href="{{ route('admin.bas.export.download-all', ['month' => $selectedMonth]) }}" color="outline">Download All</x-ui.button>
            </div>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Sales (Processed Payments)</div>
                <div class="text-xl font-semibold mt-2">{{ money((float) $summary['payments_inc']) }}</div>
                <div class="text-sm text-gray-600 mt-1">Invoice Amount Ex GST: {{ money((float) $summary['payments_ex']) }}</div>
                <div class="text-sm text-gray-600">GST on Sales: {{ money((float) $summary['payments_gst']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Expenses</div>
                <div class="text-xl font-semibold mt-2">{{ money((float) $summary['expenses_inc']) }}</div>
                <div class="text-sm text-gray-600 mt-1">Expense Amount Ex GST: {{ money((float) $summary['expenses_ex']) }}</div>
                <div class="text-sm text-gray-600">GST on Expenses: {{ money((float) $summary['expenses_gst']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Net GST</div>
                <div class="text-xl font-semibold mt-2">{{ money((float) $summary['net_gst']) }}</div>
                <div class="text-sm text-gray-600 mt-1">GST Collected - GST Paid</div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Processed Payments</h3>
                @if($customerPayments->isEmpty())
                    <p class="text-sm text-gray-600">No processed payments in this month.</p>
                @else
                    <x-ui.table>
                        <x-slot:header>
                            <th>Date</th>
                            <th class="hidden md:table-cell">Customer</th>
                            <th>Summary</th>
                            <th>Total <span class="font-normal text-xs">(incl GST)</span></th>
                        </x-slot:header>
                        <x-slot:body>
                            @foreach($customerPayments as $payment)
                                <tr>
                                    <td>
                                        <div>{{ $payment->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                                        <div class="md:hidden text-xs text-gray-600 mt-1">{{ $payment->user?->getName() ?? '-' }}</div>
                                    </td>
                                    <td class="hidden md:table-cell">{{ $payment->user?->getName() ?? '-' }}</td>
                                    <td>{{ $payment->bas_summary ?: '-' }}</td>
                                    <td class="text-right">
                                        <div>{{ money((float) ($payment->bas_total_amount ?? $payment->total_amount)) }}</div>
                                        <div class="text-xs">GST: {{ money((float) ($payment->bas_gst_amount ?? $payment->gst_amount)) }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:body>
                    </x-ui.table>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Expenses</h3>
                @if($expenses->isEmpty())
                    <p class="text-sm text-gray-600">No expenses in this month.</p>
                @else
                    <x-ui.table>
                        <x-slot:header>
                            <th>Date</th>
                            <th class="hidden md:table-cell">Supplier</th>
                            <th class="hidden md:table-cell">Invoice ID</th>
                            <th>Description</th>
                            <th>Total <span class="font-normal text-xs">(incl GST)</span></th>
                        </x-slot:header>
                        <x-slot:body>
                            @foreach($expenses as $expense)
                                @php
                                    $expenseTotal = round((float) $expense->total_amount, 2);
                                    $expenseGst = round((float) $expense->gst_amount, 2);
                                    $expenseEx = round($expenseTotal - $expenseGst, 2);
                                @endphp
                                <tr>
                                    <td>
                                        <div>{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</div>
                                        <div class="md:hidden text-xs text-gray-600 mt-1">{{ $expense->supplier ?: '-' }}</div>
                                        <div class="md:hidden text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                                    </td>
                                    <td class="hidden md:table-cell">{{ $expense->supplier ?: '-' }}</td>
                                    <td class="hidden md:table-cell">{{ $expense->invoice_id ?: '-' }}</td>
                                    <td>{{ $expense->description ?: '-' }}</td>
                                    <td class="text-right">
                                        <div>{{ money($expenseTotal) }}</div>
                                        <div class="text-xs">GST: {{ money($expenseGst) }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:body>
                    </x-ui.table>
                @endif
            </div>
        </div>
    </x-container>
</x-layout>
