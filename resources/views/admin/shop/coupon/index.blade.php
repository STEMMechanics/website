<x-layout>
    <x-mast>Store Coupons</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.shop.coupon.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($coupons->isEmpty())
            <x-none-found item="coupons" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Code</th>
                    <th class="hidden lg:table-cell">Status</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th>Amount</th>
                    <th class="hidden md:table-cell">Used</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($coupons as $coupon)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $coupon->code }}</div>
                                @if($coupon->description)
                                    <div class="text-xs text-gray-500">{{ $coupon->description }}</div>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">{{ \App\Models\Coupon::statusLabel((string) $coupon->status) }}</td>
                            <td class="hidden md:table-cell">{{ \App\Models\Coupon::discountTypeLabel((string) $coupon->discount_type) }}</td>
                            <td>
                                @if((string) $coupon->discount_type === \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE)
                                    {{ number_format((float) $coupon->amount, 2) }}%
                                @elseif((string) $coupon->discount_type === \App\Models\Coupon::DISCOUNT_TYPE_FREE_SHIPPING)
                                    Free shipping
                                @else
                                    ${{ number_format((float) $coupon->amount, 2) }}
                                @endif
                            </td>
                            <td class="hidden md:table-cell">{{ $coupon->orders_count }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.shop.coupon.edit', $coupon) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div class="mt-6">
                {{ $coupons->appends(request()->query())->links() }}
            </div>
        @endif
    </x-container>
</x-layout>
