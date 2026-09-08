@php
    $config = $configs->get(app(\App\Services\Finance\ProductAllocation::class)->scope($product->id, $variantId));
    $mode = old('mode', $config ? ($config->profile_id ? 'profile' : 'custom') : 'inherit');
@endphp
<x-layout>
    <x-mast :title="$product->title.' — Allocations'" :backUrl="route('admin.shop.product.edit', $product)" backTitle="Product">
        <x-slot:actions><x-ui.button color="mast" :href="route('admin.product-allocation.profiles')">Manage profiles</x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8 space-y-5">
        <x-finance.panel title="Product allocation">
            <form method="GET" class="mb-5">
                <x-ui.select name="variant" id="variant" label="Product option" onchange="this.form.submit()">
                    <option value="">Base product</option>
                    @foreach($product->variants as $variant)<option value="{{ $variant->id }}" @selected($variantId === $variant->id)>{{ $variant->name }}</option>@endforeach
                </x-ui.select>
            </form>
            <form method="POST" action="{{ route('admin.product-allocation.save', $product) }}" x-data="{ mode: @js($mode) }">
                @csrf
                <input type="hidden" name="variant_id" value="{{ $variantId }}">
                <x-ui.select name="mode" id="mode" label="Allocation rules" x-model="mode">
                    <option value="inherit">{{ $variantId ? 'Use base product allocation' : 'No automatic allocation' }}</option>
                    <option value="profile">Use a profile</option>
                    <option value="custom">Set amounts for this {{ $variantId ? 'variant' : 'product' }}</option>
                </x-ui.select>
                <fieldset x-show="mode === 'profile'" x-bind:disabled="mode !== 'profile'" x-cloak>
                    <x-ui.select name="profile_id" id="profile_id" label="Allocation profile">
                        <option value="">Choose a profile</option>
                        @foreach($profiles as $profile)<option value="{{ $profile->id }}" @selected((string) old('profile_id', $config?->profile_id) === (string) $profile->id)>{{ $profile->name }}</option>@endforeach
                    </x-ui.select>
                </fieldset>
                <fieldset x-show="mode === 'custom'" x-bind:disabled="mode !== 'custom'" x-cloak>
                    <x-finance.product-allocation-fields :categories="$categories" :rules="$config?->rules ? json_decode($config->rules, true) : []" />
                </fieldset>
                <p class="mt-4 text-sm text-slate-600">New invoice lines retain a copy of these rules. Existing invoices keep their saved allocations.</p>
                <x-finance.save>Save allocation</x-finance.save>
            </form>
        </x-finance.panel>
    </x-container>
</x-layout>
