@php
    $shippingMethodRows = collect(old('shipping_methods', $shippingMethods ?? []))
        ->map(function ($method): array {
            $method = is_array($method) ? $method : [];
            $isPickup = filter_var($method['is_pickup'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

            return [
                'id' => ($method['id'] ?? '') !== '' ? (int) $method['id'] : null,
                'code' => (string) ($method['code'] ?? ''),
                'name' => (string) ($method['name'] ?? ''),
                'description' => (string) ($method['description'] ?? ''),
                'shipment_label' => (string) ($method['shipment_label'] ?? ($isPickup ? 'Collection' : 'Shipment')),
                'immediate_status_label' => (string) ($method['immediate_status_label'] ?? ($isPickup ? 'Available now' : 'Ships now')),
                'delayed_status_label' => (string) ($method['delayed_status_label'] ?? ($isPickup ? 'Available later' : 'Ships later')),
                'delivery_estimate_min_days' => (string) ($method['delivery_estimate_min_days'] ?? ''),
                'delivery_estimate_max_days' => (string) ($method['delivery_estimate_max_days'] ?? ''),
                'is_active' => filter_var($method['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                'sort_order' => (int) ($method['sort_order'] ?? 0),
                'packages' => collect($method['packages'] ?? [])
                    ->map(function ($package): array {
                        $package = is_array($package) ? $package : [];

                        return [
                            'id' => ($package['id'] ?? '') !== '' ? (int) $package['id'] : null,
                            'code' => (string) ($package['code'] ?? ''),
                            'label' => (string) ($package['label'] ?? ''),
                            'sort_order' => (int) ($package['sort_order'] ?? 1),
                            'capacity' => (string) ($package['capacity'] ?? '1.00'),
                            'price' => (string) ($package['price'] ?? '0.00'),
                            'is_active' => filter_var($package['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        })
        ->values()
        ->all();
    $shippingMethodErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'shipping_methods'))
        ->flatten()
        ->unique()
        ->values()
        ->all();
    $settingsCardClasses = 'rounded-3xl border border-gray-200 bg-white p-6 shadow-sm';
    $inlineInputClasses = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0';
    $inlineTextareaClasses = 'block min-h-[5.5rem] w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0';
    $toggleCardClasses = 'flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700';
@endphp

<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products">Store Settings</x-mast>

    <x-container class="mt-4">
        <form
            method="POST"
            action="{{ route('admin.shop.settings.update') }}"
            class="space-y-6"
            x-data="{
                shippingMethods: @js($shippingMethodRows),
                newPackage(sortOrder = 1) {
                    return {
                        id: null,
                        code: '',
                        label: '',
                        sort_order: sortOrder,
                        capacity: '1.00',
                        price: '0.00',
                        is_active: true,
                    };
                },
                nextShippingMethodSortOrder() {
                    return this.shippingMethods.length > 0
                        ? Math.max(...this.shippingMethods.map((method) => Number(method.sort_order || 0))) + 1
                        : 0;
                },
                nextPackageSortOrder(methodIndex) {
                    const method = this.shippingMethods[methodIndex];
                    const packages = Array.isArray(method && method.packages)
                        ? method.packages
                        : [];

                    return packages.length > 0
                        ? Math.max(...packages.map((item) => Number(item.sort_order || 0))) + 1
                        : 1;
                },
                channelUsesFreeCollection(method) {
                    return !method || !Array.isArray(method.packages) || method.packages.length === 0;
                },
                addShippingMethod() {
                    this.shippingMethods.push({
                        id: null,
                        code: '',
                        name: '',
                        description: '',
                        shipment_label: 'Shipment',
                        immediate_status_label: 'Ships now',
                        delayed_status_label: 'Ships later',
                        delivery_estimate_min_days: '',
                        delivery_estimate_max_days: '',
                        is_active: true,
                        sort_order: this.nextShippingMethodSortOrder(),
                        packages: [this.newPackage(1)],
                    });
                },
                removeShippingMethod(index) {
                    this.shippingMethods.splice(index, 1);
                },
                addPackage(methodIndex) {
                    const method = this.shippingMethods[methodIndex];
                    if (!method) {
                        return;
                    }

                    if (!Array.isArray(method.packages)) {
                        method.packages = [];
                    }

                    method.packages.push(this.newPackage(this.nextPackageSortOrder(methodIndex)));
                },
                removePackage(methodIndex, packageIndex) {
                    const method = this.shippingMethods[methodIndex];
                    if (!method || !Array.isArray(method.packages)) {
                        return;
                    }

                    method.packages.splice(packageIndex, 1);
                },
            }"
        >
            @csrf
            @method('PUT')

            <div class="grid gap-6 xl:grid-cols-[minmax(0,0.82fr),minmax(0,1.18fr)]">
                <section class="{{ $settingsCardClasses }} space-y-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Storefront</h2>
                        <p class="mt-1 text-sm text-gray-600">Control whether the public store, cart, and checkout are available. Existing order links remain accessible.</p>
                    </div>

                    <input type="hidden" name="public_enabled" value="0" />
                    <label class="{{ $toggleCardClasses }}">
                        <input
                            type="checkbox"
                            name="public_enabled"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                            @checked((bool) old('public_enabled', $publicEnabled))
                        >
                        <span class="block">
                            <span class="block font-medium text-gray-900">Enable public storefront</span>
                            <span class="mt-1 block text-gray-500">Customers can browse products, manage their cart, and complete checkout.</span>
                        </span>
                    </label>
                    @error('public_enabled')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </section>

                <section class="{{ $settingsCardClasses }} space-y-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Packing Rules</h2>
                        <p class="mt-1 text-sm text-gray-600">These settings drive parcel packing in the cart, checkout, and order summaries.</p>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        Known packed weight only matters when products have a weight entered. If a packed parcel goes over the limit below, checkout splits into another parcel.
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <x-ui.input
                            name="max_satchel_weight_grams"
                            label="Max Known Package Weight (grams)"
                            type="number"
                            min="0"
                            :value="$maxSatchelWeightGrams"
                            class="!mb-0"
                        />
                        <x-ui.input
                            name="boxed_shipping_label"
                            label="Manual Quote Label"
                            :value="$boxedShipping['label']"
                            class="!mb-0"
                        />
                        <x-ui.input
                            name="boxed_shipping_amount"
                            label="Manual Quote Amount"
                            moneyFormat="true"
                            :value="$boxedShipping['amount'] !== null ? number_format((float) $boxedShipping['amount'], 2, '.', '') : ''"
                            info="Leave blank to require a manual quote instead of allowing checkout."
                            class="!mb-0"
                        />
                    </div>

                    <x-ui.input
                        type="textarea"
                        name="boxed_shipping_message"
                        label="Manual Quote Message"
                        :value="$boxedShipping['message']"
                        class="!mb-0"
                    />
                </section>
            </div>

            <section class="{{ $settingsCardClasses }} space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Delivery Channels</h2>
                        <p class="mt-1 text-sm text-gray-600">Create delivery options with their own package sizes, pricing, ETA, and customer-facing notes.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addShippingMethod()">Add Delivery Channel</x-ui.button>
                </div>

                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    Channels with no package options are treated as free collection or pickup. Checkout uses the first active channel in sort order, unless a manual quote is required.
                </div>

                @if($shippingMethodErrors !== [])
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        @foreach($shippingMethodErrors as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <div x-show="shippingMethods.length === 0" x-cloak class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center text-sm text-gray-600">
                    No delivery channels yet. Add one to configure shipping or pickup options for checkout.
                </div>

                <div class="space-y-4">
                    <template x-for="(method, index) in shippingMethods" :key="method.id ?? `new-${index}`">
                        <section class="rounded-3xl border border-gray-200 bg-gray-50/80 p-5">
                            <input type="hidden" :name="`shipping_methods[${index}][id]`" :value="method.id ?? ''">
                            <input type="hidden" :name="`shipping_methods[${index}][is_active]`" :value="method.is_active ? 1 : 0">

                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900" x-text="method.name || method.code || `Delivery Channel ${index + 1}`"></h3>
                                        <span class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-600" x-text="channelUsesFreeCollection(method) ? 'Collection' : 'Shipping'"></span>
                                        <span class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-500" x-show="!method.is_active" x-cloak>Inactive</span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500" x-text="channelUsesFreeCollection(method) ? 'No package pricing set. This will behave as a free collection or pickup option.' : 'Customers can choose this channel when its package options fit their order.'"></p>
                                </div>
                                <x-ui.button type="button" color="danger-outline" class="!px-4" x-on:click="removeShippingMethod(index)">Remove</x-ui.button>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Code</label>
                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][code]`" x-model="method.code">
                                </div>
                                <div class="xl:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][name]`" x-model="method.name">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                                    <input type="number" min="0" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][sort_order]`" x-model="method.sort_order">
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Customer Note</label>
                                <textarea rows="2" class="{{ $inlineTextareaClasses }}" :name="`shipping_methods[${index}][description]`" x-model="method.description"></textarea>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Shipment Term</label>
                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][shipment_label]`" x-model="method.shipment_label">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Now Term</label>
                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][immediate_status_label]`" x-model="method.immediate_status_label">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Later Term</label>
                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][delayed_status_label]`" x-model="method.delayed_status_label">
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">ETA Min Days</label>
                                    <input type="number" min="0" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][delivery_estimate_min_days]`" x-model="method.delivery_estimate_min_days">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">ETA Max Days</label>
                                    <input type="number" min="0" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][delivery_estimate_max_days]`" x-model="method.delivery_estimate_max_days">
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr),minmax(0,1fr),minmax(0,1.2fr)]">
                                <label class="{{ $toggleCardClasses }}">
                                    <input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" x-model="method.is_active">
                                    <span class="block">
                                        <span class="block font-medium text-gray-900">Active at checkout</span>
                                        <span class="mt-1 block text-gray-500">Customers can select this channel when it applies.</span>
                                    </span>
                                </label>
                                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                                    Checkout will default to the first active channel by sort order.
                                </div>
                            </div>

                            <div class="mt-5 border-t border-gray-200 pt-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-gray-900">Package Options</div>
                                        <div class="mt-1 text-sm text-gray-600">Capacity is the packing size used by products. Price is the shipping charge for one parcel in this channel.</div>
                                    </div>
                                    <x-ui.button type="button" color="outline" x-on:click="addPackage(index)">Add Package</x-ui.button>
                                </div>

                                <div x-show="channelUsesFreeCollection(method)" x-cloak class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                    No package options are configured. This channel will save as a free collection or pickup option.
                                </div>

                                <div class="mt-4 space-y-3" x-show="!channelUsesFreeCollection(method) || method.packages.length > 0" x-cloak>
                                    <template x-for="(packageOption, packageIndex) in method.packages" :key="packageOption.id ?? `package-${packageIndex}`">
                                        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                                            <input type="hidden" :name="`shipping_methods[${index}][packages][${packageIndex}][id]`" :value="packageOption.id ?? ''">
                                            <input type="hidden" :name="`shipping_methods[${index}][packages][${packageIndex}][is_active]`" :value="packageOption.is_active ? 1 : 0">

                                            <div class="flex flex-wrap items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h4 class="font-semibold text-gray-900" x-text="packageOption.label || `Package ${packageIndex + 1}`"></h4>
                                                        <span class="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500" x-show="!packageOption.is_active" x-cloak>Inactive</span>
                                                    </div>
                                                    <p class="mt-1 text-sm text-gray-500">Use package rows to define the parcel sizes and prices available in this channel.</p>
                                                </div>
                                                <x-ui.button type="button" color="danger-outline" class="!px-4" x-on:click="removePackage(index, packageIndex)">Remove</x-ui.button>
                                            </div>

                                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Code</label>
                                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][packages][${packageIndex}][code]`" x-model="packageOption.code">
                                                </div>
                                                <div class="xl:col-span-2">
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Label</label>
                                                    <input type="text" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][packages][${packageIndex}][label]`" x-model="packageOption.label">
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                                                    <input type="number" min="1" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][packages][${packageIndex}][sort_order]`" x-model="packageOption.sort_order">
                                                </div>
                                                <label class="{{ $toggleCardClasses }} !px-3 !py-2.5">
                                                    <input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" x-model="packageOption.is_active">
                                                    <span class="block">
                                                        <span class="block font-medium text-gray-900">Active</span>
                                                        <span class="mt-1 block text-gray-500">Available for automatic packing.</span>
                                                    </span>
                                                </label>
                                            </div>

                                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Capacity</label>
                                                    <input type="number" step="0.01" min="0.01" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][packages][${packageIndex}][capacity]`" x-model="packageOption.capacity">
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Price</label>
                                                    <input type="number" step="0.01" min="0" class="{{ $inlineInputClasses }}" :name="`shipping_methods[${index}][packages][${packageIndex}][price]`" x-model="packageOption.price">
                                                </div>
                                            </div>
                                        </section>
                                    </template>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>
            </section>

            <div class="flex justify-end">
                <x-ui.button type="submit">Save Store Settings</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
