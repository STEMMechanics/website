<x-layout title="My Orders">
    <x-mast backRoute="account.show" backTitle="Account">My Orders</x-mast>

    <x-container class="py-8">
        @if($orders->isEmpty())
            <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-600">
                You do not have any store orders yet.
            </div>
        @else
            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                <x-ui.table>
                    <x-slot:header>
                        <th>Order</th>
                        <th class="hidden md:table-cell">Placed</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <div class="font-semibold">{{ $order->order_number }}</div>
                                    <div class="text-xs text-gray-500">Invoice {{ $order->invoice?->invoice_number ?? '-' }}</div>
                                </td>
                                <td class="hidden md:table-cell">{{ $order->created_at?->format('M j, Y') ?? '-' }}</td>
                                <td>{{ $order->statusLabel() }}</td>
                                <td>${{ number_format((float) $order->total_amount, 2) }}</td>
                                <td><a href="{{ route('account.order.show', $order) }}" class="hover:text-primary-color" title="Open order"><i class="fa-solid fa-pen-to-square"></i></a></td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>

                <div class="mt-6">
                    {{ $orders->appends(request()->query())->links() }}
                </div>
            </div>
        @endif
    </x-container>
</x-layout>
