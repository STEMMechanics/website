@php
    $satchelRows = collect(old('satchels', $satchels))
        ->map(function ($satchel): array {
            $satchel = is_array($satchel) ? $satchel : [];

            return [
                'code' => (string) ($satchel['code'] ?? ''),
                'label' => (string) ($satchel['label'] ?? ''),
                'rank' => (int) ($satchel['rank'] ?? 1),
                'capacity' => (string) ($satchel['capacity'] ?? '1.00'),
                'price' => (string) ($satchel['price'] ?? '0.00'),
                'active' => filter_var($satchel['active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            ];
        })
        ->values()
        ->all();
@endphp

<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products">Store Settings</x-mast>

    <x-container class="mt-4">
        <form
            method="POST"
            action="{{ route('admin.shop.settings.update') }}"
            x-data="{
                satchels: @js($satchelRows),
                addSatchel() {
                    const nextRank = this.satchels.length > 0
                        ? Math.max(...this.satchels.map((satchel) => Number(satchel.rank || 0))) + 1
                        : 1;

                    this.satchels.push({
                        code: '',
                        label: '',
                        rank: nextRank,
                        capacity: '1.00',
                        price: '0.00',
                        active: true,
                    });
                },
                removeSatchel(index) {
                    this.satchels.splice(index, 1);
                },
            }"
        >
            @csrf
            @method('PUT')

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Storefront</h2>
                        <p class="text-sm text-gray-600">Control whether the public store, cart, and checkout are available. Existing public order links still stay accessible.</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        The public store also switches off automatically when there are no active products on sale.
                    </div>
                </div>

                <input type="hidden" name="public_enabled" value="0" />
                <x-ui.checkbox
                    name="public_enabled"
                    value="1"
                    label="Enable the public store storefront"
                    :checked="(bool) old('public_enabled', $publicEnabled)"
                    class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3"
                />
                @error('public_enabled')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Packing Rules</h2>
                        <p class="text-sm text-gray-600">These settings drive the satchel packing logic used in the cart, checkout, and order summaries.</p>
                    </div>
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        Packed weight only matters when products have a weight entered. If the known parcel weight goes over the limit below, checkout splits into another satchel.
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.input
                        name="max_satchel_weight_grams"
                        label="Max Known Satchel Weight (grams)"
                        type="number"
                        min="0"
                        :value="$maxSatchelWeightGrams"
                    />
                    <x-ui.input
                        name="boxed_shipping_label"
                        label="Boxed Shipping Label"
                        :value="$boxedShipping['label']"
                    />
                    <x-ui.input
                        name="boxed_shipping_amount"
                        label="Boxed Shipping Amount"
                        moneyFormat="true"
                        :value="$boxedShipping['amount'] !== null ? number_format((float) $boxedShipping['amount'], 2, '.', '') : ''"
                        info="Leave blank to require a manual quote instead of allowing checkout."
                    />
                </div>

                <x-ui.input
                    type="textarea"
                    name="boxed_shipping_message"
                    label="Boxed Shipping Message"
                    :value="$boxedShipping['message']"
                />
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Satchel Types</h2>
                        <p class="text-sm text-gray-600">Rank determines the packing order. Capacity is the internal packing size used by products. Price is the shipping charge for one satchel.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addSatchel()">Add Satchel</x-ui.button>
                </div>

                @error('satchels')
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                @enderror

                <div class="space-y-4">
                    <template x-for="(satchel, index) in satchels" :key="index">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div class="mb-4 flex items-center justify-between gap-4">
                                <div class="font-semibold text-gray-900" x-text="satchel.label || `Satchel ${index + 1}`"></div>
                                <button type="button" class="text-sm text-red-600 hover:underline" x-on:click="removeSatchel(index)">Remove</button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Code</label>
                                    <input type="text" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 pb-2.5 pt-4 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" :name="`satchels[${index}][code]`" x-model="satchel.code">
                                </div>
                                <div class="xl:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Label</label>
                                    <input type="text" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 pb-2.5 pt-4 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" :name="`satchels[${index}][label]`" x-model="satchel.label">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Rank</label>
                                    <input type="number" min="1" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 pb-2.5 pt-4 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" :name="`satchels[${index}][rank]`" x-model="satchel.rank">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Capacity</label>
                                    <input type="number" step="0.01" min="0.01" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 pb-2.5 pt-4 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" :name="`satchels[${index}][capacity]`" x-model="satchel.capacity">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Price</label>
                                    <input type="number" step="0.01" min="0" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 pb-2.5 pt-4 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" :name="`satchels[${index}][price]`" x-model="satchel.price">
                                </div>
                            </div>

                            <div class="mt-4">
                                <input type="hidden" :name="`satchels[${index}][active]`" :value="satchel.active ? 1 : 0">
                                <label class="inline-flex items-center gap-3 text-sm text-gray-700">
                                    <input type="checkbox" class="h-5 w-5 rounded border-gray-300 text-sky-600 focus:ring-sky-500" x-model="satchel.active">
                                    Active and available for packing
                                </label>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button type="submit">Save Shipping Settings</x-ui.button>
                <x-ui.button type="link" href="{{ route('admin.site_option.index', ['search' => 'store.shipping']) }}" color="outline">Open Raw Site Options</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
