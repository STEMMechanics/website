<x-layout :title="$workshop->title.' — Optional equipment'">
    <x-mast :title="$workshop->title" />
    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="relative bg-white border border-gray-200 rounded-lg shadow-sm p-5 pt-20 md:pt-5 flex gap-6">
        @include('workshop.tickets.partials.hold-countdown', ['holdExpiresAt' => $session['expires_at'] ?? null])
        <div class="flex-1 min-w-0">
        <div class="mb-3 flex items-center gap-3"><x-ui.row-action label="Back" icon="fa-arrow-left" :href="route('workshop.ticket.flow.start', $workshop)" /><h2 class="text-2xl font-bold">Optional equipment</h2></div>
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
            <dl class="mb-5 space-y-2 text-sm" aria-live="polite">
                <div class="flex justify-between"><dt>Tickets</dt><dd>{{ money($ticketAmount) }}</dd></div>
                <div class="flex justify-between"><dt>Equipment</dt><dd x-text="money(total)"></dd></div>
                <div class="flex justify-between border-t pt-2 font-semibold"><dt>Sub Total</dt><dd x-text="money(total + {{ $ticketAmount }})"></dd></div>
            </dl>
            @php
                $selected = collect($cart->contents()['lines'] ?? [])->keyBy('product_id');
                $customer = $session['equipment_customer'] ?? [];
            @endphp
            <div class="mb-5 space-y-4">
                @foreach($products as $product)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="mb-4 flex items-start gap-3">
                            <a href="{{ route('shop.product.show', $product) }}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                                <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->title }}" loading="lazy" width="64" height="64" class="h-16 w-16 rounded-lg bg-white object-contain" />
                            </a>
                            <div class="min-w-0">
                                <h3 class="font-semibold"><a href="{{ route('shop.product.show', $product) }}" target="_blank" rel="noopener noreferrer" class="text-black hover:underline">{{ $product->title }}</a> <span class="font-normal text-gray-500" x-text="'(+' + money(option({{ $product->id }}).price) + ')'">(+{{ money($product->priceForVariant()) }})</span></h3>
                                @if(trim((string) $product->short_description) !== '')
                                    <p class="mt-1 text-sm text-gray-600">{{ $product->short_description }}</p>
                                @endif
                                <div class="mt-2">
                                    <x-stock-indicator :tone="$product->availabilityTone()" :label="$product->availabilityLabel()" :stack-details="true" x-show="!variants[{{ $product->id }}]" />
                                    @foreach($product->purchasableVariants() as $variant)
                                        <x-stock-indicator :tone="$product->availabilityTone($variant)" :label="$product->availabilityLabel('F jS', $variant)" :stack-details="true" x-show="String(variants[{{ $product->id }}]) === '{{ $variant->id }}'" x-cloak />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-x-6 sm:grid-cols-2">
                            <x-ui.input type="number" min="0" max="99" label="Quantity" x-model="selected[{{ $product->id }}]" name="quantities[{{ $product->id }}]" :value="old('quantities.'.$product->id, $selected->get($product->id)['quantity'] ?? 0)" />
                            @if($product->purchasableVariants()->isNotEmpty())
                            <x-ui.select label="Option" x-model="variants[{{ $product->id }}]" name="variants[{{ $product->id }}]">
                                <option value="">{{ $product->baseOptionName() }}</option>
                                @foreach($product->purchasableVariants() as $variant)
                                    <option value="{{ $variant->id }}" @selected((string) old('variants.'.$product->id, $selected->get($product->id)['variant_id'] ?? '') === (string) $variant->id)>{{ $variant->name }}</option>
                                @endforeach
                            </x-ui.select>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 flex justify-end"><x-ui.button type="submit" name="action" value="select">Continue</x-ui.button></div>
        </form>
        </div>
        <div class="hidden md:block w-64 shrink-0 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
