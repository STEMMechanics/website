<x-layout>
    <x-mast title="Store checkout activity">
        <x-slot:actions><x-ui.button type="link" :href="route('admin.analytics.index')" color="secondary">Analytics</x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-5" inner-class="space-y-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-ui.input class="mb-0" type="date" name="from" id="checkout-analytics-from" label="From" :value="$from->toDateString()" />
            <x-ui.input class="mb-0" type="date" name="to" id="checkout-analytics-to" label="To" :value="$to->toDateString()" />
            <x-ui.button type="submit" class="h-[42px]">Apply</x-ui.button>
        </form>
        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            @include('admin.analytics.checkout-summary')
            <p class="mt-2 text-sm text-gray-600">These figures show interest and where checkout stopped. They do not establish why someone left, or represent lost revenue. Completed orders include orders placed on account terms.</p>
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-3 text-lg font-semibold">Items in carts</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b text-gray-500"><th class="p-2">Item</th><th class="p-2 text-center">Carts</th><th class="p-2 text-center">Completed orders</th><th class="p-2 text-center">Inactive carts</th><th class="p-2">Common stopping point</th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-100">
                            <td class="p-2"><a class="text-primary-color hover:underline" href="{{ route('admin.analytics.checkout', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'product' => $item->product_id]) }}#cart-details">{{ $item->title }}</a></td>
                            <td class="p-2 text-center">{{ $item->carts }}</td><td class="p-2 text-center">{{ $item->completed }}</td><td class="p-2 text-center">{{ $item->inactive }}</td><td class="p-2">{{ $stages[$item->product_id] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-gray-500">No carts recorded in this period yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </section>
        <section id="cart-details" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">Anonymous cart summaries</h2>
                @if(request()->filled('product'))
                    <x-ui.button type="link" :href="route('admin.analytics.checkout', ['from' => $from->toDateString(), 'to' => $to->toDateString()])" color="secondary">Show all items</x-ui.button>
                @endif
            </div>
            @forelse($carts as $cart)
                <article class="rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="flex flex-wrap justify-between gap-2 text-sm">
                        <strong>{{ ucfirst(str_replace('_', ' ', $cart->outcome ?? (\Illuminate\Support\Carbon::parse($cart->last_activity_at)->lte(now()->subDay()) ? 'inactive' : 'active'))) }}</strong>
                        <span class="text-gray-500">Last active {{ \Illuminate\Support\Carbon::parse($cart->last_activity_at)->diffForHumans() }}</span>
                    </div>
                    <ul class="my-3 space-y-1 text-sm">
                        @foreach($cartItems[$cart->id] ?? [] as $line)
                            <li>{{ $line->quantity }} × {{ $line->title }} <span class="text-gray-500">(${{ number_format($line->unit_price, 2) }} each)</span></li>
                        @endforeach
                    </ul>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600">
                        <span>Items: ${{ number_format($cart->subtotal, 2) }}</span>
                        <span>Delivery estimate: {{ $cart->manual_quote ? 'Manual quote required' : ($cart->shipping === null ? 'Not available' : '$'.number_format($cart->shipping, 2)) }}</span>
                        <span>Total: {{ $cart->total === null ? 'Awaiting quote' : '$'.number_format($cart->total, 2) }}</span>
                        <span>Furthest step: {{ ucfirst($cart->stage) }}</span>
                        @if($cart->payment_failed)<span class="text-rose-700">Payment failed</span>@endif
                        @if($cart->payment_cancelled)<span>Payment cancelled</span>@endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-gray-500">No matching carts.</p>
            @endforelse
            {{ $carts->links() }}
        </section>
    </x-container>
</x-layout>
