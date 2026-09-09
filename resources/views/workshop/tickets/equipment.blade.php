<x-layout :title="$workshop->title.' — Optional equipment'">
    <x-mast :title="$workshop->title" />
    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
        <div class="flex-1 min-w-0">
        <h2 class="mb-3 text-2xl font-bold">Optional equipment</h2>
        <p class="mb-4 text-sm text-gray-600">Choose any equipment you need, or continue without it.</p>
        @include('workshop.tickets.partials.summary', ['workshop' => $workshop])
        @foreach($errors->all() as $error)<p class="mb-2 text-sm text-red-600">{{ $error }}</p>@endforeach
        @php
            $savedLines = collect($cart->contents()['lines'] ?? [])->keyBy('product_id');
            $equipmentOptions = $products->mapWithKeys(function ($product) {
                $options = ['base' => ['price' => $product->priceForVariant(), 'available' => $product->isSelectionPurchasable()]];
                foreach ($product->purchasableVariants() as $variant) $options[(string) $variant->id] = ['price' => $product->priceForVariant($variant), 'available' => $product->isSelectionPurchasable($variant)];
                return [$product->id => $options];
            });
            $config = ['options' => $equipmentOptions, 'selected' => (object) old('quantities', $products->mapWithKeys(fn ($product) => [$product->id => $savedLines->get($product->id)['quantity'] ?? 0])->all()), 'variants' => (object) old('variants', $products->mapWithKeys(fn ($product) => [$product->id => $savedLines->get($product->id)['variant_id'] ?? ''])->all()), 'quantity' => 0, 'ticketPrice' => 0, 'regularPrice' => 0, 'earlyBirdRemaining' => null];
        @endphp
        <form x-data="SM.workshopEquipmentCheckout(@js($config))" method="POST" action="{{ route('workshop.ticket.flow.equipment.save', $workshop) }}">
            @csrf
            <p class="mb-5 text-sm" aria-live="polite"><strong>Equipment total:</strong> <span x-text="money(total)"></span> <span class="text-gray-500">before delivery</span></p>
            @php
                $selected = collect($cart->contents()['lines'] ?? [])->keyBy('product_id');
                $customer = $session['equipment_customer'] ?? [];
            @endphp
            <div class="mb-5 space-y-4">
                @foreach($products as $product)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="mb-3 font-semibold"><a href="{{ route('shop.product.show', $product) }}" target="_blank" rel="noopener noreferrer" class="text-black hover:underline">{{ $product->title }}</a> <span class="font-normal text-gray-500" x-text="'(+' + money(option({{ $product->id }}).price) + ')'">(+{{ money($product->priceForVariant()) }})</span></h3>
                        <div class="grid gap-x-6 sm:grid-cols-2">
                            <x-ui.input type="number" min="0" max="99" label="Quantity" x-model="selected[{{ $product->id }}]" name="quantities[{{ $product->id }}]" :value="old('quantities.'.$product->id, $selected->get($product->id)['quantity'] ?? 0)" />
                            <x-ui.select label="Option" x-model="variants[{{ $product->id }}]" name="variants[{{ $product->id }}]">
                                <option value="">{{ $product->baseOptionName() }}</option>
                                @foreach($product->purchasableVariants() as $variant)
                                    <option value="{{ $variant->id }}" @selected((string) old('variants.'.$product->id, $selected->get($product->id)['variant_id'] ?? '') === (string) $variant->id)>{{ $variant->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($lines->isNotEmpty())
                <x-finance.panel title="Delivery">
                    <x-ui.select name="shipping_method_code" label="Delivery option">
                        @foreach($summary['shipping_methods'] ?? [] as $method)
                            <option value="{{ $method['code'] }}" @selected(($summary['shipping_method_code'] ?? '') === $method['code'])>{{ $method['label'] ?? $method['name'] ?? $method['code'] }} — {{ ($method['requires_manual_quote'] ?? false) ? 'Quote required' : money($method['estimated_amount'] ?? 0) }}</option>
                        @endforeach
                    </x-ui.select>
                    <p class="mb-4 text-sm text-gray-600">Billing address, also used for delivery when shipping is selected.</p>
                    <x-ui.input name="billing_address" label="Address" :value="old('billing_address', $customer['billing_address'] ?? '')" />
                    <x-ui.input name="billing_address2" label="Address line 2" :value="old('billing_address2', $customer['billing_address2'] ?? '')" />
                    <div class="grid gap-x-6 sm:grid-cols-2">
                        <x-ui.input name="billing_city" label="Suburb / city" :value="old('billing_city', $customer['billing_city'] ?? '')" />
                        <x-ui.select name="billing_state" label="State">
                            <option value="">Choose state</option>
                            @foreach(['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'] as $state)<option @selected(old('billing_state', $customer['billing_state'] ?? '') === $state)>{{ $state }}</option>@endforeach
                        </x-ui.select>
                        <x-ui.input name="billing_postcode" label="Postcode" maxlength="4" :value="old('billing_postcode', $customer['billing_postcode'] ?? '')" />
                        <x-ui.input label="Country" value="Australia" disabled />
                    </div>
                    @if($summary['has_delayed_items'] ?? false)
                        <x-ui.checkbox name="consolidate_shipments" value="1" label="Send items together when all are available" :checked="$customer['consolidate_shipments'] ?? false" />
                        @foreach($lines as $line)
                            @if($line->delayed_quantity > 0)<p class="text-sm text-gray-600">{{ $line->title ?? $line->product->title }}: {{ $line->delayed_shipping_estimate ?? 'Delivery timing to be confirmed' }}</p>@endif
                        @endforeach
                    @endif
                    @if($summary['contains_preorder'] ?? false)
                        <x-ui.checkbox name="preorder_acknowledged" value="1" label="I understand that preordered equipment will be sent when available" :checked="$customer['preorder_acknowledged'] ?? false" />
                    @endif
                </x-finance.panel>
                <dl class="my-5 space-y-2 text-sm">
                    @foreach($lines as $line)<div class="flex justify-between gap-4"><dt>{{ $line->product->title }} × {{ $line->quantity }}</dt><dd>{{ money($line->line_price) }}</dd></div>@endforeach
                    <div class="flex justify-between gap-4"><dt>Delivery</dt><dd>{{ money($summary['shipping']) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t pt-2 font-semibold"><dt>Equipment total (inc GST)</dt><dd>{{ ($summary['shipping_quote']['requires_manual_quote'] ?? false) ? 'Quote required' : money($summary['total']) }}</dd></div>
                </dl>
                @if(!($summary['can_checkout'] ?? true) || ($summary['shipping_quote']['requires_manual_quote'] ?? false))
                    <p class="mb-4 text-sm text-amber-700">This delivery requires a quote. Your tickets will be paid now; equipment and delivery will be quoted separately before you pay for them.</p>
                @endif
            @endif
            <input type="hidden" name="confirmed_total" value="{{ $summary['total'] ?? 0 }}">
            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <x-ui.button type="submit" name="action" value="skip" color="outline" formnovalidate>Continue without equipment</x-ui.button>
                <x-ui.button type="submit" name="action" value="review" color="secondary" formnovalidate>Update total</x-ui.button>
                @if($lines->isNotEmpty())<x-ui.button type="submit" name="action" value="continue">Continue to payment</x-ui.button>@endif
            </div>
        </form>
        </div>
        <div class="hidden md:block w-64 shrink-0 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
