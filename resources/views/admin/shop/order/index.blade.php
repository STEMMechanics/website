<x-layout>
    <x-mast>Store Orders</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($orders->isEmpty())
            <x-none-found item="orders" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Order</th>
                    <th class="hidden md:table-cell">Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.shop.order.edit', $order) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $order->order_number }}</a>
                                <div class="text-xs text-gray-500">{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div>{{ $order->billing_name ?: '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $order->billing_email ?: '-' }}</div>
                            </td>
                            <td>{{ $order->statusLabel() }}</td>
                            <td>${{ number_format((float) $order->total_amount, 2) }}</td>
                            <td><a href="{{ route('admin.shop.order.edit', $order) }}" class="hover:text-primary-color" title="Open order"><i class="fa-solid fa-pen-to-square"></i></a></td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div class="mt-6">
                {{ $orders->appends(request()->query())->links() }}
            </div>
        @endif
    </x-container>
</x-layout>
