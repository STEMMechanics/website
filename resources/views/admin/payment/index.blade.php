<x-layout>
    <x-mast>Payments</x-mast>

    <x-container>
        <div class="flex flex-col sm:flex-row my-4 items-center gap-3 sm:gap-4">
            <div class="w-full sm:flex-1">
                <x-ui.button href="{{ route('admin.payment.create') }}">Record</x-ui.button>
            </div>
            <div class="w-full sm:flex-1 flex flex-col sm:flex-row gap-3 sm:gap-4">
                <form method="GET" action="{{ route('admin.payment.index') }}" class="flex flex-wrap items-center gap-2">
                    @if(request()->filled('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <x-ui.checkbox
                        id="unallocated_only"
                        name="unallocated_only"
                        value="1"
                        label="Show unallocated only"
                        :checked="request()->boolean('unallocated_only')"
                        :noWrapper="true"
                        :inline="true"
                        label-class="whitespace-nowrap"
                        onchange="this.form.submit()" />
                </form>
                <x-ui.search name="search" label="Search" class="w-full sm:flex-1" />
            </div>
        </div>

        @if($customerPayments->isEmpty())
        <x-none-found item="payments" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>ID</th>
                    <th>Details</th>
                    <th>Amount <span class="font-normal text-xs">(incl GST)</span></th>
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden lg:table-cell">Allocated</th>
                    <th class="hidden lg:table-cell">Unallocated</th>
                    <th class="text-center">Actions</th>
                </x-slot:header>
                <x-slot:body>
                @foreach ($customerPayments as $customerPayment)
                @php
                $allocated = (float) ($customerPayment->allocated_amount_sum ?? 0);
                $unallocatedBeforeRefund = max(0, round(((float) $customerPayment->total_amount) - $allocated, 2));
                $unallocated = max(0, round($unallocatedBeforeRefund - (float) $customerPayment->refunds->sum('total_amount'), 2));
                $typeLabel = (string) ($customerPayment->payment_method ?? '') === \App\Models\Payment::PAYMENT_METHOD_CREDIT
                ? 'Credit'
                : 'Payment';
                $allocatedInvoiceNumbers = $customerPayment->allocations
                ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0 && $allocation->invoice)
                ->map(fn ($allocation) => (string) $allocation->invoice->invoice_number)
                ->unique()
                ->values();
                $receiptViewUrl = route('admin.payment.receipt', $customerPayment);
                $receiptDownloadUrl = route('admin.payment.receipt', ['payment' => $customerPayment, 'download' => 1]);
                @endphp
                <tr>
                    <td class="!text-center">
                        <a href="{{ route('admin.payment.edit', $customerPayment) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $customerPayment->id }}</a>
                    </td>
                    <td class="">
                        <div>{{ $customerPayment->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                        <div class="text-xs text-gray-600">{{ $customerPayment->user?->getName() ?? '-' }}</div>
                        <div class="text-xs text-gray-600 md:hidden">{{ $typeLabel }}</div>
                        <div class="text-xs text-gray-600 lg:hidden mt-1">
                            Alloc: {{ money((float) $allocated) }} · Unalloc: {{ money($unallocated) }}
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            {{ $allocatedInvoiceNumbers->isNotEmpty() ? 'Invoice #'.$allocatedInvoiceNumbers->implode(', Invoice #') : '-' }}
                        </div>
                    </td>
                    <td class="text-center">{{ money((float) $customerPayment->total_amount) }}</td>
                    <td class="text-center hidden md:table-cell">{{ $typeLabel }}</td>
                    <td class="hidden lg:table-cell">
                        {{ money((float) $allocated) }}
                        <div class="text-xs text-gray-600">
                            {{ $allocatedInvoiceNumbers->isNotEmpty() ? 'Invoice #'.$allocatedInvoiceNumbers->implode(', Invoice #') : '-' }}
                        </div>
                    </td>
                    <td class="hidden lg:table-cell">{{ money($unallocated) }}</td>
                    <td>
                        <div class="flex justify-center gap-2 sm:gap-3 whitespace-nowrap text-sm">
                            <a href="{{ route('admin.payment.edit', $customerPayment) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="{{ $receiptViewUrl }}" target="_blank" class="hover:text-primary-color" title="View receipt"><i class="fa-regular fa-file-lines"></i></a>
                            <a href="{{ $receiptDownloadUrl }}" class="hover:text-primary-color" title="Download receipt"><i class="fa-solid fa-download"></i></a>
                        </div>
                    </td>
                </tr>
                @foreach($customerPayment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0) as $refund)
                @php
                $refundViewUrl = route('admin.payment.receipt', $refund);
                $refundDownloadUrl = route('admin.payment.receipt', ['payment' => $refund, 'download' => 1]);
                @endphp
                <tr class="bg-gray-50">
                    <td class="!text-center">
                        <a href="{{ route('admin.payment.edit', $refund) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $refund->id }}</a>
                    </td>
                    <td>
                        <div>↳ {{ $refund->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                        <div class="text-xs text-gray-600">{{ $refund->user?->getName() ?? '-' }}</div>
                        <div class="text-xs text-gray-600 md:hidden">Refund</div>
                    </td>
                    <td class="text-center">{{ money(-((float) $refund->total_amount)) }}</td>
                    <td class="text-center hidden md:table-cell">Refund</td>
                    <td class="hidden lg:table-cell">-</td>
                    <td class="hidden lg:table-cell">-</td>
                    <td class="w-28">
                        <div class="flex justify-center gap-2 sm:gap-3 whitespace-nowrap text-sm">
                            <a href="{{ route('admin.payment.edit', $refund) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="{{ $refundViewUrl }}" target="_blank" class="hover:text-primary-color" title="View receipt"><i class="fa-regular fa-file-lines"></i></a>
                            <a href="{{ $refundDownloadUrl }}" class="hover:text-primary-color" title="Download receipt"><i class="fa-solid fa-download"></i></a>
                        </div>
                    </td>
                </tr>
                @endforeach
                @endforeach
            </x-slot:body>
        </x-ui.table>

        {{ $customerPayments->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
