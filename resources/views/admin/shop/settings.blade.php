@php
    $shippingMethodRows = collect(old('shipping_methods', $shippingMethods ?? []))
        ->map(function ($method): array {
            $method = is_array($method) ? $method : [];
            $isPickup = filter_var($method['is_pickup'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

            return [
                'is_pickup' => $isPickup,
                'calculated_packaging_cost' => (string) ($method['calculated_packaging_cost'] ?? '0.00'),
                'cubic_divisor' => (string) ($method['cubic_divisor'] ?? ''),
                'weight_tiers' => array_values($method['weight_tiers'] ?? []),
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
                'suppresses_request_quote' => filter_var($method['suppresses_request_quote'] ?? ! $isPickup, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
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
                            'internal_length_mm' => (string) ($package['internal_length_mm'] ?? ''),
                            'internal_width_mm' => (string) ($package['internal_width_mm'] ?? ''),
                            'internal_height_mm' => (string) ($package['internal_height_mm'] ?? ''),
                            'max_weight_grams' => (string) ($package['max_weight_grams'] ?? '5000'),
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
    $validationErrors = $errors->getMessages();
    $settingsCardClasses = 'rounded-3xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm';
    $inlineInputClasses = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0';
    $inlineTextareaClasses = 'block min-h-22 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0';
    $toggleCardClasses = 'flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700';
@endphp

<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products">Store Settings</x-mast>

    <x-container class="mt-4" inner-class="max-w-screen-2xl">
        @if($errors->any())
            <div role="alert" class="mb-6 rounded-2xl border border-red-300 bg-red-50 px-5 py-4 text-red-800">
                <div class="font-semibold">Store settings were not saved</div>
                <div class="mt-1 text-sm">Please correct the highlighted fields below and save again.</div>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('admin.shop.settings.update') }}"
            class="space-y-6"
            x-data="{
                shippingMethods: @js($shippingMethodRows),
                processingPauseUntil: @js(old('processing_pause_until', $processingPauseUntil ?? '')),
                trackingLinkTemplatesSource: @js(old('tracking_link_templates', $trackingLinkTemplates ?? [])),
                validationErrors: @js($validationErrors),
                trackingLinkTemplates: [],
                init() {
                    this.trackingLinkTemplates = this.parseTrackingLinkTemplates(this.trackingLinkTemplatesSource);
                },
                newPackage(sortOrder = 1) {
                    return {
                        id: null,
                        code: '',
                        label: '',
                        sort_order: sortOrder,
                        capacity: '1.00',
                        internal_length_mm: '',
                        internal_width_mm: '',
                        internal_height_mm: '',
                        max_weight_grams: '5000',
                        price: '0.00',
                        is_active: true,
                        suppresses_request_quote: true,
                    };
                },
                loadStandardBoxes(methodIndex) {
                    this.shippingMethods[methodIndex].packages = [
                        { id: null, code: 'box_220_160_70', label: '220 × 160 × 70 mm Box', sort_order: 1, capacity: '1.00', internal_length_mm: '220', internal_width_mm: '160', internal_height_mm: '70', max_weight_grams: '5000', price: '12.38', is_active: true },
                        { id: null, code: 'box_240_190_120', label: '240 × 190 × 120 mm Box', sort_order: 2, capacity: '1.00', internal_length_mm: '240', internal_width_mm: '190', internal_height_mm: '120', max_weight_grams: '5000', price: '16.56', is_active: true },
                        { id: null, code: 'box_390_280_140', label: '390 × 280 × 140 mm Box', sort_order: 3, capacity: '1.00', internal_length_mm: '390', internal_width_mm: '280', internal_height_mm: '140', max_weight_grams: '5000', price: '20.93', is_active: true },
                        { id: null, code: 'box_440_277_168', label: '440 × 277 × 168 mm Box', sort_order: 4, capacity: '1.00', internal_length_mm: '440', internal_width_mm: '277', internal_height_mm: '168', max_weight_grams: '5000', price: '25.09', is_active: true },
                    ];
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
                    return !!method?.is_pickup;
                },
                addShippingMethod() {
                    this.shippingMethods.push({
                        id: null,
                        is_pickup: false,
                        cubic_divisor: '',
                        calculated_packaging_cost: '0.00',
                        weight_tiers: [],
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
                newTrackingLinkTemplate() {
                    return {
                        carrier: '',
                        template: '',
                    };
                },
                parseTrackingLinkTemplates(rawValue) {
                    if (Array.isArray(rawValue)) {
                        return rawValue
                            .map((row) => ({
                                carrier: String(row && typeof row === 'object' ? row.carrier : '').trim(),
                                template: String(row && typeof row === 'object' ? row.template : '').trim(),
                            }))
                            .filter((row) => row.carrier !== '' || row.template !== '');
                    }

                    if (typeof rawValue !== 'string' || rawValue.trim() === '') {
                        return [];
                    }

                    try {
                        const parsed = JSON.parse(rawValue);

                        if (Array.isArray(parsed)) {
                            return parsed
                                .map((row) => ({
                                    carrier: String((row && typeof row === 'object' ? row.carrier : '') ?? '').trim(),
                                    template: String((row && typeof row === 'object' ? row.template : '') ?? '').trim(),
                                }))
                                .filter((row) => row.carrier !== '' || row.template !== '');
                        }

                        if (parsed && typeof parsed === 'object') {
                            return Object.entries(parsed)
                                .map(([carrier, template]) => ({
                                    carrier: String(carrier ?? '').trim(),
                                    template: String(template ?? '').trim(),
                                }))
                                .filter((row) => row.carrier !== '' || row.template !== '');
                        }
                    } catch (error) {
                        return [];
                    }

                    return [];
                },
                addTrackingLinkTemplate() {
                    this.trackingLinkTemplates.push(this.newTrackingLinkTemplate());
                },
                removeTrackingLinkTemplate(index) {
                    this.trackingLinkTemplates.splice(index, 1);
                },
                normalizedTrackingLinkTemplates() {
                    return this.trackingLinkTemplates
                        .map((row) => ({
                            carrier: String(row.carrier ?? '').trim(),
                            template: String(row.template ?? '').trim(),
                        }))
                        .filter((row) => row.carrier !== '' && row.template !== '');
                },
                fieldErrors(name) {
                    return this.validationErrors[name] ?? [];
                },
                hasFieldError(name) {
                    return this.fieldErrors(name).length > 0;
                },
            }"
        >
            @csrf
            @method('PUT')

            <div class="grid gap-6 xl:grid-cols-[minmax(0,0.82fr),minmax(0,1.18fr)]">
                <section class="{{ $settingsCardClasses }} space-y-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Storefront</h2>
                    </div>

                    <input type="hidden" name="public_enabled" value="0" />
                    <label class="{{ $toggleCardClasses }}">
                        <x-ui.checkbox bare small

 name="public_enabled"
 value="1"
 class="mt-0.5"
 :checked="(bool) old('public_enabled', $publicEnabled)" />
                        <span class="block">
                            <span class="block font-medium text-gray-900">Enabled</span>
                        </span>
                    </label>
                    @error('public_enabled')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror

                    <div class="pt-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Away Until</label>
                        <div class="flex items-center gap-2">
                            <x-ui.input-control
                                type="date"
                                name="processing_pause_until"
                                class="{{ $inlineInputClasses }} mb-0!"
                                x-model="processingPauseUntil" />
                            <x-ui.button
                                type="button"
                                color="outline"
                                class="shrink-0 px-3!"
                                x-on:click="processingPauseUntil = ''"
                                x-bind:disabled="!processingPauseUntil"
                            >
                                Clear
                            </x-ui.button>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">Orders placed while you are away will be held until this date.</div>
                        @error('processing_pause_until')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
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
                            class="mb-0!"
                        />
                        <x-ui.input
                            name="boxed_shipping_label"
                            label="Manual Quote Label"
                            :value="$boxedShipping['label']"
                            class="mb-0!"
                        />
                        <x-ui.input
                            name="boxed_shipping_amount"
                            label="Manual Quote Amount"
                            moneyFormat="true"
                            :value="$boxedShipping['amount'] !== null ? number_format((float) $boxedShipping['amount'], 2, '.', '') : ''"
                            info="Leave blank to require a manual quote instead of allowing checkout."
                            class="mb-0!"
                        />
                    </div>

                    <x-ui.input
                        type="textarea"
                        name="boxed_shipping_message"
                        label="Manual Quote Message"
                        :value="$boxedShipping['message']"
                        class="mb-0!"
                    />
                </section>
            </div>

            <section class="{{ $settingsCardClasses }} space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Courier Tracking Link Templates</h2>
                        <p class="mt-1 text-sm text-gray-600">Add one row per courier. Use {tracking} in the URL template and the tracking link will be filled automatically when a tracking number is entered.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addTrackingLinkTemplate()">Add Template</x-ui.button>
                </div>

                <div x-show="trackingLinkTemplates.length === 0" x-cloak class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    No courier templates yet. Add one to auto-fill tracking links for specific couriers.
                </div>

                        <div class="mt-4 space-y-3" x-show="trackingLinkTemplates.length > 0" x-cloak>
                            <template x-for="(trackingLinkTemplate, templateIndex) in trackingLinkTemplates" :key="templateIndex">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div class="grid flex-1 gap-4 md:grid-cols-[minmax(0,0.75fr),minmax(0,1.25fr)]">
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-gray-700">Courier</label>
                                                <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`tracking_link_templates[${templateIndex}][carrier]`" placeholder="Australia Post" x-model="trackingLinkTemplate.carrier" />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-gray-700">Tracking URL Template</label>
                                                <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`tracking_link_templates[${templateIndex}][template]`" placeholder="https://example.com/track?id={tracking}" x-model="trackingLinkTemplate.template" />
                                            </div>
                                        </div>
                                        <x-ui.button type="button" color="danger-outline" class="px-4!" x-on:click="removeTrackingLinkTemplate(templateIndex)">Remove</x-ui.button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

            <section class="{{ $settingsCardClasses }} space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Delivery Channels</h2>
                        <p class="mt-1 text-sm text-gray-600">Create delivery options with their own package sizes, pricing, ETA, and customer-facing notes.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addShippingMethod()">Add Delivery Channel</x-ui.button>
                </div>

                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    Each shipping channel can have fixed-price boxes, calculated weight tiers, or both. Select free collection explicitly for pickup channels. Checkout uses the first active channel in sort order, unless a manual quote is required.
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
                        <section class="rounded-3xl border border-gray-200 bg-gray-50/80 p-3 sm:p-5">
                            <input type="hidden" :name="`shipping_methods[${index}][id]`" :value="method.id ?? ''">
                            <input type="hidden" :name="`shipping_methods[${index}][is_active]`" :value="method.is_active ? 1 : 0">

                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900" x-text="method.name || method.code || `Delivery Channel ${index + 1}`"></h3>
                                        <x-ui.badge color="gray" variant="outline" class="font-medium" x-text="channelUsesFreeCollection(method) ? 'Collection' : 'Shipping'"></x-ui.badge>
                                        <x-ui.badge color="gray" variant="outline" class="font-medium" x-show="!method.is_active" x-cloak>Inactive</x-ui.badge>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500" x-text="channelUsesFreeCollection(method) ? 'Customers can collect their order for free.' : 'Checkout compares fixed-price and calculated boxes, including combinations, to find the cheapest shipment for this channel.'"></p>
                                </div>
                                <x-ui.button type="button" color="danger-outline" class="px-4!" x-on:click="removeShippingMethod(index)">Remove</x-ui.button>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Code</label>
                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][code]`" x-model="method.code" />
                                </div>
                                <div class="xl:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][name]`" x-model="method.name" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                                    <x-ui.input-control type="number" min="0" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][sort_order]`" x-model="method.sort_order" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Customer Note</label>
                                <x-ui.textarea-control rows="2" class="{{ $inlineTextareaClasses }}" x-bind:name="`shipping_methods[${index}][description]`" x-model="method.description"></x-ui.textarea-control>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Shipment Term</label>
                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][shipment_label]`" x-model="method.shipment_label" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Now Term</label>
                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][immediate_status_label]`" x-model="method.immediate_status_label" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Later Term</label>
                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][delayed_status_label]`" x-model="method.delayed_status_label" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">ETA Min Days</label>
                                    <x-ui.input-control type="number" min="0" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][delivery_estimate_min_days]`" x-model="method.delivery_estimate_min_days" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">ETA Max Days</label>
                                    <x-ui.input-control type="number" min="0" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][delivery_estimate_max_days]`" x-model="method.delivery_estimate_max_days" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                                <label class="{{ $toggleCardClasses }}">
                                    <x-ui.checkbox bare small class="mt-0.5" x-model="method.is_active" />
                                    <span class="block">
                                        <span class="block font-medium text-gray-900">Active at checkout</span>
                                        <span class="mt-1 block text-gray-500">Customers can select this channel when it applies.</span>
                                    </span>
                                </label>
                                <label class="{{ $toggleCardClasses }}">
                                    <input type="hidden" :name="`shipping_methods[${index}][suppresses_request_quote]`" value="0">
                                    <x-ui.checkbox bare small value="1" class="mt-0.5" x-bind:name="`shipping_methods[${index}][suppresses_request_quote]`" x-model="method.suppresses_request_quote" />
                                    <span class="block">
                                        <span class="block font-medium text-gray-900">Can replace a manual quote</span>
                                        <span class="mt-1 block text-gray-500">When this channel can fulfil the cart, Request Quote is hidden. Leave this off for pickup or collection.</span>
                                    </span>
                                </label>
                                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                                    Checkout will default to the first active channel by sort order.
                                </div>
                            </div>

                            <div class="mt-5">
                                <input type="hidden" :name="`shipping_methods[${index}][is_pickup]`" :value="method.is_pickup ? 1 : 0">
                                <label class="{{ $toggleCardClasses }}">
                                    <x-ui.checkbox bare small class="mt-0.5" x-model="method.is_pickup" />
                                    <span class="block">
                                        <span class="block font-medium text-gray-900">Free collection / pickup</span>
                                        <span class="mt-1 block text-gray-500">Use free collection instead of shipping. Turn off to use fixed-price boxes and calculated pricing.</span>
                                    </span>
                                </label>
                            </div>

                            <fieldset class="min-w-0" x-show="!method.is_pickup" x-bind:disabled="method.is_pickup" x-cloak>
                            <x-ui.collapsible-section title="Calculated Box Pricing" variant="product" class="mt-5 min-w-0 [&_.ui-collapsible-section__summary-text--title]:whitespace-normal" x-on:invalid.capture="$el.open = true" x-bind:open="Object.keys(validationErrors).some(key => key.startsWith(`shipping_methods.${index}.weight_tiers`) || key === `shipping_methods.${index}.cubic_divisor` || key === `shipping_methods.${index}.calculated_packaging_cost`)">
                                <x-slot:summary><span x-text="method.weight_tiers.length ? `${method.weight_tiers.length} weight tier${method.weight_tiers.length === 1 ? '' : 's'} · $${Number(method.calculated_packaging_cost || 0).toFixed(2)} packaging per box` : 'Not configured'"></span></x-slot:summary>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="mt-1 text-sm text-gray-600">Optional. Chargeable weight is the higher of actual weight and cubic weight for each packed box. Whole items are split into more boxes when needed, and fixed-price boxes can be mixed in to reduce the total.</p>
                                    </div>
                                    <x-ui.button type="button" color="outline" class="w-full shrink-0 sm:w-auto" x-on:click="method.weight_tiers.push({ max_weight_grams: '', price: '' })">Add Weight Tier</x-ui.button>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <div class="min-w-0">
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Cubic weight divisor (mm³ per gram)</label>
                                        <x-ui.input-control type="number" min="1" step="1" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][cubic_divisor]`" x-model="method.cubic_divisor" x-bind:class="hasFieldError(`shipping_methods.${index}.cubic_divisor`) && 'border-red-400'" />
                                        <p class="mt-1 text-sm text-gray-500">Length × width × height in mm ÷ divisor = cubic weight in grams. For example, 200 × 200 × 200 ÷ 4000 = 2000 g. Uses estimated packed box dimensions, including empty space. Clear the divisor and remove all tiers to disable calculated pricing.</p>
                                        <template x-for="message in fieldErrors(`shipping_methods.${index}.cubic_divisor`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                    </div>
                                    <div class="min-w-0">
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Packaging cost per box (inc. GST)</label>
                                        <x-ui.input-control type="number" min="0" step="0.01" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][calculated_packaging_cost]`" x-model="method.calculated_packaging_cost" x-bind:class="hasFieldError(`shipping_methods.${index}.calculated_packaging_cost`) && 'border-red-400'" />
                                        <p class="mt-1 text-sm text-gray-500">Added to the postage tier for every calculated box, including additional boxes in a shipment. Enter 0 if packaging is already included. Fixed-price boxes already include packaging.</p>
                                        <template x-for="message in fieldErrors(`shipping_methods.${index}.calculated_packaging_cost`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                    </div>
                                </div>
                                <template x-for="message in fieldErrors(`shipping_methods.${index}.weight_tiers`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                    <template x-for="(tier, tierIndex) in method.weight_tiers" :key="tierIndex">
                                        <div class="grid items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-gray-700">Chargeable weight up to (g)</label>
                                                <x-ui.input-control type="number" min="1" step="1" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][weight_tiers][${tierIndex}][max_weight_grams]`" x-model="tier.max_weight_grams" x-bind:class="hasFieldError(`shipping_methods.${index}.weight_tiers.${tierIndex}.max_weight_grams`) && 'border-red-400'" />
                                                <template x-for="message in fieldErrors(`shipping_methods.${index}.weight_tiers.${tierIndex}.max_weight_grams`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-gray-700">Postage price (inc. GST)</label>
                                                <x-ui.input-control type="number" min="0" step="0.01" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][weight_tiers][${tierIndex}][price]`" x-model="tier.price" x-bind:class="hasFieldError(`shipping_methods.${index}.weight_tiers.${tierIndex}.price`) && 'border-red-400'" />
                                                <template x-for="message in fieldErrors(`shipping_methods.${index}.weight_tiers.${tierIndex}.price`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                            </div>
                                            <x-ui.button variant="plain" type="button" class="inline-flex h-11 w-11 items-center justify-center justify-self-end rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600 sm:mt-6" x-on:click="method.weight_tiers.splice(tierIndex, 1)" title="Remove tier" aria-label="Remove tier">
                                                <i class="fa-solid fa-trash text-sm" aria-hidden="true"></i>
                                            </x-ui.button>
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-3 text-sm text-gray-500">Limits are inclusive and sorted by weight when saved. A single item above the highest limit needs another eligible shipping option or a manual quote.</p>
                            </x-ui.collapsible-section>

                            <x-ui.collapsible-section title="Fixed-price Box Options" variant="product" class="mt-4 min-w-0 [&_.ui-collapsible-section__summary-text--title]:whitespace-normal" x-on:invalid.capture="$el.open = true" x-bind:open="Object.keys(validationErrors).some(key => key.startsWith(`shipping_methods.${index}.packages`))">
                                <x-slot:summary><span x-text="method.packages.length ? `${method.packages.filter(item => item.is_active).length} active / ${method.packages.length} box option${method.packages.length === 1 ? '' : 's'}` : 'No fixed-price boxes'"></span></x-slot:summary>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="mt-1 text-sm text-gray-600">Dimensions are the usable internal measurements of each box. Price is the GST-inclusive customer charge and includes the box and postage.</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 sm:shrink-0">
                                        <x-ui.button type="button" color="outline" x-on:click="loadStandardBoxes(index)">Load Standard Boxes</x-ui.button>
                                        <x-ui.button type="button" color="outline" x-on:click="addPackage(index)">Add Box</x-ui.button>
                                    </div>
                                </div>

                                <div x-show="!method.is_pickup && !method.packages.some(item => item.is_active) && method.weight_tiers.length === 0" x-cloak class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                    No active shipping prices are configured. This channel requires a manual quote until you add a fixed-price box or calculated tiers.
                                </div>

                                <div class="mt-4 grid items-start gap-4 xl:grid-cols-2" x-show="!channelUsesFreeCollection(method) || method.packages.length > 0" x-cloak>
                                    <template x-for="(packageOption, packageIndex) in method.packages" :key="packageOption.id ?? `package-${packageIndex}`">
                                        <section
                                            class="rounded-2xl border bg-white p-4 shadow-sm"
                                            :class="Object.keys(validationErrors).some((key) => key.startsWith(`shipping_methods.${index}.packages.${packageIndex}.`)) ? 'border-red-300' : 'border-gray-200'"
                                        >
                                            <input type="hidden" :name="`shipping_methods[${index}][packages][${packageIndex}][id]`" :value="packageOption.id ?? ''">
                                            <input type="hidden" :name="`shipping_methods[${index}][packages][${packageIndex}][is_active]`" :value="packageOption.is_active ? 1 : 0">

                                            <div class="flex flex-wrap items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h4 class="font-semibold text-gray-900" x-text="packageOption.label || `Package ${packageIndex + 1}`"></h4>
                                                        <x-ui.badge color="gray" x-show="!packageOption.is_active" x-cloak>Inactive</x-ui.badge>
                                                    </div>
                                                    <p class="mt-1 text-sm text-gray-500">Use package rows to define the parcel sizes and prices available in this channel.</p>
                                                </div>
                                                <x-ui.button type="button" color="danger-outline" class="px-4!" x-on:click="removePackage(index, packageIndex)">Remove</x-ui.button>
                                            </div>

                                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Code</label>
                                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.code`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][code]`" x-model="packageOption.code" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.code`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Label</label>
                                                    <x-ui.input-control type="text" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.label`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][label]`" x-model="packageOption.label" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.label`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                                                    <x-ui.input-control type="number" min="1" class="{{ $inlineInputClasses }}" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][sort_order]`" x-model="packageOption.sort_order" />
                                                </div>
                                                <label class="{{ $toggleCardClasses }} px-3! py-2.5!">
                                                    <x-ui.checkbox bare small class="mt-0.5" x-model="packageOption.is_active" />
                                                    <span class="block">
                                                        <span class="block font-medium text-gray-900">Active</span>
                                                        <span class="mt-1 block text-gray-500">Available for automatic packing.</span>
                                                    </span>
                                                </label>
                                            </div>

                                            <input type="hidden" :name="`shipping_methods[${index}][packages][${packageIndex}][capacity]`" value="1.00">
                                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Internal length (mm)</label>
                                                    <x-ui.input-control type="number" min="1" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.internal_length_mm`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][internal_length_mm]`" x-model="packageOption.internal_length_mm" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.internal_length_mm`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Internal width (mm)</label>
                                                    <x-ui.input-control type="number" min="1" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.internal_width_mm`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][internal_width_mm]`" x-model="packageOption.internal_width_mm" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.internal_width_mm`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Internal height (mm)</label>
                                                    <x-ui.input-control type="number" min="1" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.internal_height_mm`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][internal_height_mm]`" x-model="packageOption.internal_height_mm" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.internal_height_mm`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Maximum weight (g)</label>
                                                    <x-ui.input-control type="number" min="1" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.max_weight_grams`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][max_weight_grams]`" x-model="packageOption.max_weight_grams" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.max_weight_grams`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Price (inc. GST)</label>
                                                    <x-ui.input-control type="number" step="0.01" min="0" class="{{ $inlineInputClasses }}" x-bind:class="hasFieldError(`shipping_methods.${index}.packages.${packageIndex}.price`) && 'border-red-400'" x-bind:name="`shipping_methods[${index}][packages][${packageIndex}][price]`" x-model="packageOption.price" />
                                                    <template x-for="message in fieldErrors(`shipping_methods.${index}.packages.${packageIndex}.price`)" :key="message"><div class="mt-1 text-sm text-red-600" x-text="message"></div></template>
                                                </div>
                                            </div>
                                        </section>
                                    </template>
                                </div>
                            </x-ui.collapsible-section>
                            </fieldset>
                        </section>
                    </template>

                    <section class="rounded-3xl border border-amber-200 bg-amber-50/80 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-900">Request Quote</h3>
                                    <x-ui.badge color="warning" variant="outline" class="font-medium">System option</x-ui.badge>
                                </div>
                                <p class="mt-2 text-sm text-gray-600">Shown only when no active channel marked “Can replace a manual quote” can fulfil the cart.</p>
                            </div>
                            <div class="w-40">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                                <x-ui.input-control type="number" min="0" max="999" name="request_quote_sort_order" value="{{ old('request_quote_sort_order', $requestQuoteSortOrder ?? 2) }}" class="{{ $inlineInputClasses }}" />
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            <div class="flex justify-end">
                <x-ui.button type="submit">Save Store Settings</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
