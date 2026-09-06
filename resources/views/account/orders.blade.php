<x-layout title="My Orders">
    <x-mast>My Orders</x-mast>

    <x-container x-data="{ showCancelled: true }">
        <x-ui.dynamic-list name="account-orders">

        <x-ui.collection-controls class="my-5" />

        @if($orders->isEmpty())
            <x-none-found item="orders" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="order_number" class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;" label="Order #" />
                    <x-ui.list-heading field="order_number" label="Order Details" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Status" />
                    <x-ui.list-heading field="created_at" class="hidden lg:table-cell" label="Placed" />
                    <x-ui.list-heading class="text-center!" label="Amount" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                <div class="lg:hidden text-xs text-gray-600 mt-1">Placed: <x-ui.date-time>{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                            </td>
                            <td class="hidden md:table-cell text-center!">
                                <div class="whitespace-nowrap">{{ $statusLabel }}</div>
                            </td>
                            <td class="hidden lg:table-cell"><x-ui.date-time>{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></td>
                            <td class="text-center!">
                                <div>Total: ${{ number_format((float) $order->total_amount, 2) }}</div>
                                @if($invoice)
                                    <div class="text-xs text-gray-600">
                                        Invoice: {{ \App\Models\Invoice::statusLabel((string) $invoice->status) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center!">
                                <x-ui.row-actions class="whitespace-nowrap">
                                    @if($canPay)
                                        <x-ui.row-action label="Pay order" icon="fa-solid fa-credit-card" tone="neutral" href="{{ route('account.invoice.show', $invoice) }}" />
                                    @elseif($invoice)
                                        <span class="text-gray-300" title="Order is already paid or closed"><i class="fa-solid fa-credit-card"></i></span>
                                    @endif

                                    <x-ui.row-action label="View order" icon="fa-regular fa-eye" tone="neutral" href="{{ route('account.order.show', $order) }}" />

                                    @if($invoice)
                                        <x-ui.row-action label="View invoice payments" icon="fa-solid fa-receipt" tone="neutral" href="{{ route('account.invoice.receipts', $invoice) }}" />
                                        <x-ui.row-action label="Open invoice PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('account.invoice.pdf', $invoice) }}" target="_blank" />
                                    @else
                                        <span class="text-gray-300" title="No linked invoice"><i class="fa-solid fa-receipt"></i></span>
                                        <span class="text-gray-300" title="No linked invoice PDF"><i class="fa-regular fa-file-pdf"></i></span>
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$orders" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
