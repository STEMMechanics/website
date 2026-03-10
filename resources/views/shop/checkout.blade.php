@php
    $heroLine = $lines->shuffle()->first();
    $summaryRows = [
        ['label' => 'Items', 'value' => $lines->sum('quantity').' item'.($lines->sum('quantity') === 1 ? '' : 's')],
        ['label' => 'Subtotal', 'value' => '$'.number_format((float) $summary['subtotal'], 2)],
        ['label' => 'Shipping', 'value' => $summary['can_checkout'] ? '$'.number_format((float) $summary['shipping'], 2) : 'Manual quote'],
    ];

    if ((float) $summary['discount'] > 0) {
        $summaryRows[] = [
            'label' => 'Discount',
            'value' => '- $'.number_format((float) $summary['discount'], 2).($summary['coupon_code'] ? ' ('.$summary['coupon_code'].')' : ''),
            'value_class' => 'text-emerald-700',
        ];
    }

    $summaryRows[] = [
        'label' => 'Total',
        'value' => $summary['total'] !== null ? '$'.number_format((float) $summary['total'], 2) : 'Unavailable',
    ];
@endphp

<x-layout title="Checkout" :canonical="route('shop.checkout')">
    <x-mast backRoute="shop.cart.show" backTitle="Cart">Checkout</x-mast>

    <x-container class="max-w-4xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
            <div class="flex-1">
                <div class="text-sm uppercase tracking-[0.18em] text-gray-500 mb-1">Step 1 of 2</div>
                <h2 class="text-2xl font-bold mb-3">Checkout Details</h2>
                <p class="text-sm text-gray-600 mb-4">Complete this checkout to place your order. Nothing is created or charged until the next step.</p>

                @include('shop.partials.flow-summary', [
                    'heading' => 'Order Summary',
                    'rows' => $summaryRows,
                ])

                @if(!$summary['can_checkout'])
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <div class="font-semibold mb-1">Checkout blocked</div>
                        <div>{{ $summary['shipping_quote']['reason'] }}</div>
                    </div>
                @endif

                @error('coupon_code')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                @enderror
                @error('shipping_country')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                @enderror

                <div class="mb-6">
                    <div class="text-sm font-semibold text-gray-900 mb-3">Items</div>
                    <div class="space-y-3">
                        @foreach($lines as $line)
                            <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3">
                                <div>
                                    <div class="font-semibold text-gray-900">{{ $line->display_title }}</div>
                                    <div class="text-sm text-gray-500">Qty {{ $line->quantity }}{{ $line->product->isDigital() ? ' · Digital' : ' · Physical' }}</div>
                                </div>
                                <div class="text-sm font-semibold text-gray-900">${{ number_format((float) $line->line_price, 2) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('shop.checkout.place-order') }}">
                    @csrf

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.input name="billing_name" label="Full Name" :value="$prefill['billing_name']" />
                            <x-ui.input name="billing_company" label="Company" :value="$prefill['billing_company']" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.input name="billing_email" label="Email" type="email" :value="$prefill['billing_email']" />
                            <x-ui.input name="billing_phone" label="Phone" :value="$prefill['billing_phone']" />
                        </div>
                    </div>

                    @if($summary['contains_physical'])
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Shipping Address</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <x-ui.input name="shipping_name" label="Recipient Name" :value="$prefill['shipping_name']" />
                                <x-ui.input name="shipping_phone" label="Recipient Phone" :value="$prefill['shipping_phone']" />
                            </div>
                            <x-ui.input name="shipping_address" label="Address Line 1" :value="$prefill['shipping_address']" />
                            <x-ui.input name="shipping_address2" label="Address Line 2" :value="$prefill['shipping_address2']" />
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <x-ui.input name="shipping_city" label="City" :value="$prefill['shipping_city']" />
                                <x-ui.input name="shipping_state" label="State" :value="$prefill['shipping_state']" />
                                <x-ui.input name="shipping_postcode" label="Postcode" :value="$prefill['shipping_postcode']" />
                                <x-ui.input name="shipping_country" label="Country" :value="$prefill['shipping_country']" />
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="shipping_country" value="{{ $prefill['shipping_country'] }}">
                        <div class="border-t border-gray-200 pt-6">
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                This order only contains digital items, so no shipping address is required.
                            </div>
                        </div>
                    @endif

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Notes</h3>
                        <x-ui.input type="textarea" name="notes" label="Anything we should know?" :value="$prefill['notes']" />
                    </div>

                    <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:justify-between">
                        <x-ui.button type="link" color="outline" href="{{ route('shop.cart.show') }}">Back to Cart</x-ui.button>
                        @if($summary['can_checkout'])
                            <x-ui.button type="submit">{{ (float) ($summary['total'] ?? 0) > 0 ? 'Continue to Payment' : 'Complete Order' }}</x-ui.button>
                        @else
                            <button type="button" disabled class="inline-flex cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-8 py-1.5 text-sm font-semibold leading-6 text-gray-600 shadow-sm">Continue to Payment</button>
                        @endif
                    </div>
                </form>
            </div>

            @if($heroLine)
                <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $heroLine->product->primaryImageUrl() }}')"></div>
            @endif
        </div>
    </x-container>
</x-layout>
