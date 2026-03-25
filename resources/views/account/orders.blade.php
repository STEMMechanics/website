<x-layout title="My Orders">
    <x-mast>My Orders</x-mast>

    <x-container x-data="{ showCancelled: false }">
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.checkbox
                    label="Show cancelled"
                    :noWrapper="true"
                    :inline="true"
                    labelClass="text-sm pt-0"
                    x-model="showCancelled" />
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($orders->isEmpty())
            <x-none-found item="orders" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;">Order #</th>
                    <th>Order Details</th>
                    <th class="hidden md:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Placed</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($orders as $order)
                        @php
                            $invoice = $order->invoice;
                            $isCancelled = (string) $order->status === \App\Models\StoreOrder::STATUS_CANCELLED;
                            $itemCount = max(0, (int) ($order->items_sum_quantity ?? $order->items_count ?? 0));
                            $itemCountLabel = $itemCount.' item'.($itemCount === 1 ? '' : 's');
                            $invoiceNumber = trim((string) ($invoice?->invoice_number ?? ''));
                            $orderTypeLabel = match (true) {
                                (bool) $order->contains_physical && (bool) $order->contains_digital => $order->usesPickup() ? 'Pickup order with downloads' : 'Delivery order with downloads',
                                (bool) $order->contains_physical => $order->usesPickup() ? 'Pickup order' : 'Delivery order',
                                (bool) $order->contains_digital => 'Digital order',
                                default => 'Store order',
                            };
                            $statusLabel = $order->statusLabel();
                            $canPay = $invoice !== null && (string) $order->status === \App\Models\StoreOrder::STATUS_PENDING_PAYMENT;
                        @endphp
                        <tr
                            x-show="showCancelled || !{{ $isCancelled ? 'true' : 'false' }}"
                            style="{{ $isCancelled ? 'background-color: rgb(254 242 242);' : '' }}"
                        >
                            <td>
                                <div class="whitespace-nowrap">{{ $order->order_number }}</div>
                                <div class="md:hidden text-xs text-gray-600 mt-1">{{ $statusLabel }}</div>
                            </td>
                            <td>
                                <div>{{ $orderTypeLabel }}</div>
                                <div class="text-xs text-gray-600 mt-1">
                                    {{ $itemCountLabel }}
                                    @if($invoiceNumber !== '')
                                        - Invoice {{ $invoiceNumber }}
                                    @endif
                                </div>
                                <div class="lg:hidden text-xs text-gray-600 mt-1">Placed: {{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div class="whitespace-nowrap">{{ $statusLabel }}</div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                            <td>
                                <div>Total: ${{ number_format((float) $order->total_amount, 2) }}</div>
                                @if($invoice)
                                    <div class="text-xs text-gray-600">
                                        Invoice: {{ \App\Models\Invoice::statusLabel((string) $invoice->status) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    @if($canPay)
                                        <a href="{{ route('account.invoice.show', $invoice) }}" class="hover:text-primary-color" title="Pay order"><i class="fa-solid fa-credit-card"></i></a>
                                    @elseif($invoice)
                                        <span class="text-gray-300" title="Order is already paid or closed"><i class="fa-solid fa-credit-card"></i></span>
                                    @endif

                                    <a href="{{ route('account.order.show', $order) }}" class="hover:text-primary-color" title="View order"><i class="fa-regular fa-eye"></i></a>

                                    @if($invoice)
                                        <a href="{{ route('account.invoice.receipts', $invoice) }}" class="hover:text-primary-color" title="View invoice payments"><i class="fa-solid fa-receipt"></i></a>
                                        <a href="{{ route('account.invoice.pdf', $invoice) }}" class="hover:text-primary-color" title="Open invoice PDF" target="_blank"><i class="fa-regular fa-file-pdf"></i></a>
                                    @else
                                        <span class="text-gray-300" title="No linked invoice"><i class="fa-solid fa-receipt"></i></span>
                                        <span class="text-gray-300" title="No linked invoice PDF"><i class="fa-regular fa-file-pdf"></i></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $orders->links() }}
        @endif
    </x-container>
</x-layout>
