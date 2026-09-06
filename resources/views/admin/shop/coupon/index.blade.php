<x-layout>
    <x-mast>Store Vouchers
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.shop.coupon.create') }}">Create Voucher</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-shop-coupon">
        <x-ui.collection-controls class="my-5" />

        @if($coupons->isEmpty())
            <x-none-found item="vouchers" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Code" />
                    <x-ui.list-heading class="hidden lg:table-cell text-center!" label="Status" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Type" />
                    <x-ui.list-heading class="hidden xl:table-cell" label="Products" />
                    <x-ui.list-heading class="hidden xl:table-cell" label="Workshops" />
                    <x-ui.list-heading class="text-center!" label="Amount" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Used" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($coupons as $coupon)
                        <tr>
                            <td>
                                <a href="{{ route('admin.shop.coupon.edit', $coupon) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $coupon->code }}</a>
                                <div class="mt-1 lg:hidden"><x-ui.badge :color="$coupon->status === \App\Models\Coupon::STATUS_ACTIVE ? 'success' : 'gray'">{{ \App\Models\Coupon::statusLabel((string) $coupon->status) }}</x-ui.badge></div>
                                @if($coupon->description)
                                    <div class="text-xs text-gray-500">{{ $coupon->description }}</div>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell text-center!"><x-ui.badge :color="$coupon->status === \App\Models\Coupon::STATUS_ACTIVE ? 'success' : 'gray'">{{ \App\Models\Coupon::statusLabel((string) $coupon->status) }}</x-ui.badge></td>
                            <td class="hidden md:table-cell text-center!">{{ \App\Models\Coupon::discountTypeLabel((string) $coupon->discount_type) }}</td>
                            <td class="hidden xl:table-cell">
                                @if(! $coupon->applies_to_products)
                                    Off
                                @elseif((int) ($coupon->restricted_products_count ?? 0) > 0)
                                    Selected ({{ (int) $coupon->restricted_products_count }})
                                @else
                                    All
                                @endif
                            </td>
                            <td class="hidden xl:table-cell">
                                @if(! $coupon->applies_to_workshops)
                                    Off
                                @elseif((int) ($coupon->restricted_workshops_count ?? 0) > 0)
                                    Selected ({{ (int) $coupon->restricted_workshops_count }})
                                @else
                                    All
                                @endif
                            </td>
                            <td class="text-center!">
                                @if((string) $coupon->discount_type === \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE)
                                    {{ number_format((float) $coupon->amount, 2) }}%
                                @elseif((string) $coupon->discount_type === \App\Models\Coupon::DISCOUNT_TYPE_FREE_SHIPPING)
                                    Free shipping
                                @else
                                    ${{ number_format((float) $coupon->amount, 2) }}
                                @endif
                            </td>
                            <td class="hidden md:table-cell">{{ $coupon->orders_count }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.shop.coupon.edit', $coupon) }}" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div class="mt-6">
                <x-ui.list-pagination :paginator="$coupons" />
            </div>
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
