<x-layout>
    <x-mast backRoute="admin.shop.order.index" backTitle="Store Orders">Order {{ $order->order_number }}</x-mast>

    <x-container class="py-8">
        <div class="grid gap-6 xl:grid-cols-[0.95fr,1.05fr]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Overview</h2>
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex items-center justify-between gap-4"><span>Order</span><span class="font-semibold">{{ $order->order_number }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Invoice</span><span class="font-semibold">{{ $order->invoice?->invoice_number ?? '-' }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Placed</span><span class="font-semibold">{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Paid</span><span class="font-semibold">{{ $order->paid_at?->format('M j, Y g:i a') ?? 'No' }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Fulfilled</span><span class="font-semibold">{{ $order->fulfilled_at?->format('M j, Y g:i a') ?? 'No' }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Shipping</span><span class="font-semibold">{{ $order->shipping_method ?: 'Shipping' }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Total</span><span class="font-semibold">${{ number_format((float) $order->total_amount, 2) }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Outstanding</span><span class="font-semibold">${{ number_format((float) ($order->invoice?->outstandingAmount() ?? 0), 2) }}</span></div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        @if($order->invoice)
                            <x-ui.button type="link" href="{{ route('admin.invoice.edit', $order->invoice) }}" color="outline">Open Invoice</x-ui.button>
                        @endif
                        @if($order->items->first()?->product)
                            <x-ui.button type="link" href="{{ route('shop.product.show', $order->items->first()->product) }}" color="outline">View Product</x-ui.button>
                        @endif
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Customer</h2>
                    <div class="space-y-1 text-sm text-gray-700">
                        <div>{{ $order->billing_name ?: '-' }}</div>
                        <div>{{ $order->billing_email ?: '-' }}</div>
                        <div>{{ $order->billing_phone ?: '-' }}</div>
                        @if($order->billing_company)
                            <div>{{ $order->billing_company }}</div>
                        @endif
                    </div>
                </section>

                @if($order->contains_physical)
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping</h2>
                        <div class="space-y-1 text-sm text-gray-700">
                            @foreach($order->shippingAddressLines() as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                        <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            <div><strong>{{ $order->shipping_method ?: 'Shipping' }}</strong></div>
                            @if($order->shipping_package_summary)
                                <div class="mt-1">{{ $order->shipping_package_summary }}</div>
                            @endif
                            @if(($order->shipping_chargeable_weight_grams ?? 0) > 0)
                                <div class="mt-1 text-xs text-gray-500">Known packed weight {{ number_format((float) (($order->shipping_chargeable_weight_grams ?? 0) / 1000), 2) }} kg</div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Status</h2>
                    <form method="POST" action="{{ route('admin.shop.order.update', $order) }}">
                        @csrf
                        @method('PUT')
                        <x-ui.select name="status" label="Order Status">
                            @foreach(\App\Models\StoreOrder::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ (new \App\Models\StoreOrder(['status' => $status]))->statusLabel() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input type="textarea" name="notes" label="Internal Notes" :value="$order->notes ?? ''" />
                        <x-ui.button type="submit">Save Order</x-ui.button>
                    </form>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Totals</h2>
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex items-center justify-between gap-4"><span>Items</span><span>${{ number_format((float) $order->subtotal_amount, 2) }}</span></div>
                        <div class="flex items-center justify-between gap-4"><span>Shipping</span><span>${{ number_format((float) $order->shipping_amount, 2) }}</span></div>
                        @if((float) $order->discount_amount > 0)
                            <div class="flex items-center justify-between gap-4 text-emerald-700"><span>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</span><span>- ${{ number_format((float) $order->discount_amount, 2) }}</span></div>
                        @endif
                        <div class="flex items-center justify-between gap-4 text-gray-500"><span>GST included</span><span>${{ number_format((float) $order->gst_amount, 2) }}</span></div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Items</h2>
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            <div class="rounded-2xl border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $item->displayTitle() }}</div>
                                        <div class="text-sm text-gray-500">Qty {{ $item->quantity }} · {{ \App\Models\Product::productTypeLabel((string) $item->product_type) }}</div>
                                        @if($item->variant_sku || $item->product_sku)
                                            <div class="text-sm text-gray-500">SKU {{ $item->variant_sku ?: $item->product_sku }}</div>
                                        @endif
                                        @if((int) $item->inventory_reserved_quantity > 0)
                                            <div class="text-xs text-gray-500">Reserved stock: {{ (int) $item->inventory_reserved_quantity }}</div>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900">${{ number_format((float) $item->line_total_amount, 2) }}</div>
                                        <div class="text-xs text-gray-500">GST ${{ number_format((float) $item->line_gst_amount, 2) }}</div>
                                    </div>
                                </div>
                                @if($item->downloads->isNotEmpty())
                                    <div class="mt-4 border-t border-gray-200 pt-4 space-y-2">
                                        @foreach($item->downloads as $download)
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <span>{{ $download->title ?: ($download->media?->title ?: $download->media_name) }}</span>
                                                <span class="text-gray-500">{{ $download->media?->file_type ?? 'Download file' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
