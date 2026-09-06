<x-layout>
    <x-mast>Store Orders</x-mast>

    <x-container>
        <x-ui.dynamic-list name="admin-shop-order">
        <x-ui.collection-controls class="my-5" />

        @if($orders->isEmpty())
            <x-none-found item="orders" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Order" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Customer" />
                    <x-ui.list-heading class="text-center!" label="Status" />
                    <x-ui.list-heading class="text-center!" label="Total" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.shop.order.edit', $order) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $order->order_number }}</a>
                                <div class="text-xs text-gray-500"><x-ui.date-time>{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div>{{ $order->billing_name ?: '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $order->billing_email ?: '-' }}</div>
                            </td>
                            <td class="text-center!">{{ $order->statusLabel() }}</td>
                            <td class="text-center!">${{ number_format((float) $order->total_amount, 2) }}</td>
                            <td class="">
                                <x-ui.row-actions class="whitespace-nowrap">
                                    <x-ui.row-action label="Edit order" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.shop.order.edit', $order) }}" aria-label="Edit order {{ $order->order_number }}" />
                                    @if($order->invoice)
                                        <x-ui.row-action label="View invoice PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.invoice.pdf', $order->invoice) }}" target="_blank" aria-label="View invoice PDF for order {{ $order->order_number }}" />
                                    @endif
                                    <x-ui.row-action label="View pick list PDF" icon="fa-solid fa-list-check" tone="neutral" href="{{ route('admin.shop.order.pick-list.pdf', $order) }}" target="_blank" aria-label="View pick list PDF for order {{ $order->order_number }}" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div class="mt-6">
                <x-ui.list-pagination :paginator="$orders" />
            </div>
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
