<x-layout>
    <x-mast backRoute="admin.shop.coupon.index" backTitle="Store Vouchers">{{ isset($coupon) ? 'Edit' : 'Create' }} Voucher</x-mast>

    @php
        $resolvedStatus = old('status', $coupon->status ?? \App\Models\Coupon::STATUS_ACTIVE);
        $resolvedDiscountType = old('discount_type', $coupon->discount_type ?? \App\Models\Coupon::DISCOUNT_TYPE_FIXED_AMOUNT);
        $resolvedAmount = trim((string) old('amount', isset($coupon) ? (string) $coupon->amount : '0.00'));
        if ($resolvedDiscountType === \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE) {
            $resolvedAmount = (string) (int) round((float) $resolvedAmount);
        } elseif ($resolvedDiscountType === \App\Models\Coupon::DISCOUNT_TYPE_FREE_SHIPPING) {
            $resolvedAmount = '0.00';
        } else {
            $resolvedAmount = number_format((float) $resolvedAmount, 2, '.', '');
        }
        $resolvedEndsAt = old('ends_at', isset($coupon) && $coupon->ends_at ? $coupon->ends_at->format('Y-m-d\TH:i') : '');
        $resolvedProductsEnabled = filter_var(old('applies_to_products', isset($coupon) ? ($coupon->applies_to_products ? '1' : '0') : '1'), FILTER_VALIDATE_BOOLEAN);
        $resolvedWorkshopsEnabled = filter_var(old('applies_to_workshops', isset($coupon) ? ($coupon->applies_to_workshops ? '1' : '0') : '1'), FILTER_VALIDATE_BOOLEAN);

        $resolvedProductIds = collect(old('product_ids', isset($coupon) ? $coupon->restrictedProductIds() : []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $resolvedWorkshopIds = collect(old('workshop_ids', isset($coupon) ? $coupon->restrictedWorkshopIds() : []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->filter()
            ->unique()
            ->values();

        $selectedProducts = $resolvedProductIds->isNotEmpty()
            ? \App\Models\Product::query()->whereIn('id', $resolvedProductIds)->orderBy('title')->get()
            : collect();
        $selectedWorkshops = $resolvedWorkshopIds->isNotEmpty()
            ? \App\Models\Workshop::query()->whereIn('id', $resolvedWorkshopIds)->orderByDesc('starts_at')->orderBy('title')->get()
            : collect();

        $selectedProductItems = $selectedProducts->map(function (\App\Models\Product $product): array {
            return [
                'id' => (string) $product->id,
                'label' => (string) $product->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $product->sku ? 'SKU '.$product->sku : null,
                    \App\Models\Product::statusLabel((string) $product->status),
                ]))),
                'status' => (string) $product->status,
            ];
        })->values()->all();

        $selectedWorkshopItems = $selectedWorkshops->map(function (\App\Models\Workshop $workshop): array {
            return [
                'id' => (string) $workshop->id,
                'label' => (string) $workshop->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $workshop->publicStatusLabel(),
                    $workshop->starts_at ? $workshop->starts_at->format('j M Y') : null,
                ]))),
                'status' => (string) $workshop->status,
            ];
        })->values()->all();

        $productPickerFilters = [
            ['value' => 'all', 'label' => 'All products'],
            ['value' => \App\Models\Product::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => \App\Models\Product::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => \App\Models\Product::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];

        $workshopPickerFilters = [
            ['value' => 'all', 'label' => 'All workshops'],
            ['value' => 'public', 'label' => 'Public'],
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'hidden', 'label' => 'Hidden'],
            ['value' => 'draft', 'label' => 'Draft'],
        ];
    @endphp

    <x-container class="mt-4">
        <form
            method="POST"
            action="{{ route('admin.shop.coupon.'.(isset($coupon) ? 'update' : 'store'), $coupon ?? []) }}"
            x-data="{
                status: @js($resolvedStatus),
                discountType: @js($resolvedDiscountType),
                amountDraft: @js($resolvedAmount),
                endsAtDraft: @js($resolvedEndsAt),
                endsAtPastToastKey: null,
                productsEnabled: @js($resolvedProductsEnabled),
                workshopsEnabled: @js($resolvedWorkshopsEnabled),
                productSelections: @js($selectedProductItems),
                workshopSelections: @js($selectedWorkshopItems),
                endpoints: {
                    products: @js(route('admin.shop.coupon.product-options')),
                    workshops: @js(route('admin.shop.coupon.workshop-options')),
                },
                pickerFilters: {
                    products: @js($productPickerFilters),
                    workshops: @js($workshopPickerFilters),
                },
                picker: {
                    type: null,
                    open: false,
                    search: '',
                    filter: 'all',
                    busy: false,
                    items: [],
                    meta: {
                        current_page: 1,
                        last_page: 1,
                        total: 0,
                    },
                },
                init() {
                    this.syncAmountForType();
                    this.checkEndsAtWarning(true);
                    this.$watch('discountType', () => {
                        this.syncAmountForType();
                    });
                    this.$watch('status', () => {
                        this.checkEndsAtWarning();
                    });
                    this.$watch('endsAtDraft', () => {
                        this.checkEndsAtWarning();
                    });
                },
                syncAmountForType() {
                    if (this.discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_FREE_SHIPPING }}') {
                        this.amountDraft = '0.00';
                        return;
                    }

                    this.amountDraft = this.formatAmountForCurrentType(this.amountDraft);
                },
                formatAmountForCurrentType(value = this.amountDraft) {
                    const raw = String(value || '').trim();
                    const amount = parseFloat(raw);
                    if (!Number.isFinite(amount) || amount < 0) {
                        return this.discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE }}' ? '0' : '0.00';
                    }

                    if (this.discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE }}') {
                        return String(Math.round(amount));
                    }

                    return amount.toFixed(2);
                },
                formatAmountForType() {
                    if (this.discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_FREE_SHIPPING }}') {
                        this.amountDraft = '0.00';
                        return;
                    }

                    this.amountDraft = this.formatAmountForCurrentType();
                },
                amountStep() {
                    return this.discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE }}' ? '1' : '0.01';
                },
                amountInputMode() {
                    return this.discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE }}' ? 'numeric' : 'decimal';
                },
                isEndsAtPast() {
                    if (this.status !== '{{ \App\Models\Coupon::STATUS_ACTIVE }}') {
                        return false;
                    }

                    const raw = String(this.endsAtDraft || '').trim();
                    if (raw === '') {
                        return false;
                    }

                    const parsed = new Date(raw);
                    return Number.isFinite(parsed.getTime()) && parsed.getTime() < Date.now();
                },
                checkEndsAtWarning(force = false) {
                    if (!this.isEndsAtPast()) {
                        this.endsAtPastToastKey = null;
                        return;
                    }

                    const warningKey = String(this.endsAtDraft || '');
                    if (!force && this.endsAtPastToastKey === warningKey) {
                        return;
                    }

                    this.endsAtPastToastKey = warningKey;
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice(
                            'Voucher will be inactive',
                            'This end time is in the past, so the voucher will be inactive immediately.',
                            'warning',
                            { toast: true }
                        );
                    }
                },
                selectedItems(type) {
                    return type === 'products' ? this.productSelections : this.workshopSelections;
                },
                selectedItemIds(type) {
                    return this.selectedItems(type).map((item) => String(item.id));
                },
                selectedItemCount(type) {
                    return this.selectedItems(type).length;
                },
                openProductsPicker() {
                    this.productsEnabled = true;
                    this.openPicker('products');
                },
                openWorkshopsPicker() {
                    this.workshopsEnabled = true;
                    this.openPicker('workshops');
                },
                openPicker(type) {
                    this.picker.type = type;
                    this.picker.open = true;
                    this.picker.search = '';
                    this.picker.filter = type === 'products' ? '{{ \App\Models\Product::STATUS_ACTIVE }}' : 'all';
                    this.loadPickerItems(1);
                    this.$nextTick(() => {
                        this.$refs.scopePickerSearch?.focus?.();
                    });
                },
                closePicker() {
                    this.picker.open = false;
                    this.picker.busy = false;
                    this.picker.items = [];
                    this.picker.search = '';
                    this.picker.filter = 'all';
                    this.picker.type = null;
                },
                pickerTitle() {
                    return this.picker.type === 'products' ? 'Select products' : 'Select workshops';
                },
                pickerDescription() {
                    return this.picker.type === 'products'
                        ? 'Choose specific products to limit this voucher, or clear the selection to allow all products.'
                        : 'Choose specific workshops to limit this voucher, or clear the selection to allow all workshops.';
                },
                pickerFiltersForCurrentType() {
                    return this.pickerFilters[this.picker.type || 'products'] || [];
                },
                pickerEndpoint() {
                    return this.picker.type ? this.endpoints[this.picker.type] : '';
                },
                pickerFilterLabel(value) {
                    const match = this.pickerFiltersForCurrentType().find((item) => String(item.value) === String(value));
                    return match?.label || String(value || '');
                },
                isPickerItemSelected(item) {
                    return this.selectedItemIds(this.picker.type || 'products').includes(String(item.id));
                },
                togglePickerItem(item) {
                    if (!this.picker.type) {
                        return;
                    }

                    const key = String(item.id);
                    const list = this.selectedItems(this.picker.type);
                    const index = list.findIndex((entry) => String(entry.id) === key);
                    if (index >= 0) {
                        list.splice(index, 1);
                    } else {
                        list.push({
                            id: key,
                            label: String(item.label || ''),
                            subtitle: String(item.subtitle || ''),
                            status: String(item.status || ''),
                        });
                        list.sort((left, right) => String(left.label).localeCompare(String(right.label)));
                    }
                },
                async loadPickerItems(page = 1) {
                    if (!this.picker.type) {
                        return;
                    }

                    const endpoint = this.pickerEndpoint();
                    if (!endpoint) {
                        return;
                    }

                    this.picker.busy = true;
                    try {
                        const params = new URLSearchParams({
                            search: this.picker.search || '',
                            status: this.picker.filter || 'all',
                            per_page: '12',
                            page: String(page),
                        });
                        const response = await fetch(endpoint + '?' + params.toString(), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const payload = await response.json().catch(() => ({}));
                        this.picker.items = (payload.items || []).map((item) => ({
                            ...item,
                            selected: this.isPickerItemSelected(item),
                        }));
                        this.picker.meta = payload.meta || {
                            current_page: 1,
                            last_page: 1,
                            total: 0,
                        };
                    } finally {
                        this.picker.busy = false;
                    }
                },
                async refreshPickerItems() {
                    await this.loadPickerItems(this.picker.meta?.current_page || 1);
                },
                async setPickerFilter(value) {
                    this.picker.filter = value;
                    await this.loadPickerItems(1);
                },
                clearCurrentPickerSelection() {
                    if (this.picker.type === 'products') {
                        this.productSelections = [];
                        this.productsEnabled = true;
                    } else if (this.picker.type === 'workshops') {
                        this.workshopSelections = [];
                        this.workshopsEnabled = true;
                    }

                    this.closePicker();
                },
                finishPickerSelection() {
                    this.closePicker();
                },
            }"
            x-init="init()"
            x-effect="document.documentElement.classList.toggle('overflow-hidden', picker.open); document.body.classList.toggle('overflow-hidden', picker.open);"
        >
            @csrf
            @isset($coupon)
                @method('PUT')
            @endisset

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="code" label="Voucher Code" :value="$coupon->code ?? ''" info="Case insensitive." />
                    <x-ui.input name="description" label="Description" :value="$coupon->description ?? ''" />
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.select name="status" label="Status" x-model="status">
                        @foreach(\App\Models\Coupon::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $coupon->status ?? \App\Models\Coupon::STATUS_ACTIVE) === $status)>{{ \App\Models\Coupon::statusLabel($status) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select name="discount_type" label="Discount Type" x-model="discountType" x-on:change="syncAmountForType()">
                        @foreach(\App\Models\Coupon::DISCOUNT_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('discount_type', $coupon->discount_type ?? \App\Models\Coupon::DISCOUNT_TYPE_FIXED_AMOUNT) === $type)>{{ \App\Models\Coupon::discountTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <template x-if="discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_FIXED_AMOUNT }}'">
                        <x-ui.input
                            name="amount"
                            label="Fixed amount discount"
                            :value="$resolvedAmount"
                            x-model="amountDraft"
                            x-bind:step="amountStep()"
                            x-bind:inputmode="amountInputMode()"
                            x-on:blur="formatAmountForType()"
                            x-on:change="formatAmountForType()"
                        />
                    </template>
                    <template x-if="discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_PERCENTAGE }}'">
                        <x-ui.input
                            name="amount"
                            label="Percentage discount"
                            :value="$resolvedAmount"
                            x-model="amountDraft"
                            x-bind:step="amountStep()"
                            x-bind:inputmode="amountInputMode()"
                            x-on:blur="formatAmountForType()"
                            x-on:change="formatAmountForType()"
                        />
                    </template>
                    <template x-if="discountType === '{{ \App\Models\Coupon::DISCOUNT_TYPE_FREE_SHIPPING }}'">
                        <input type="hidden" name="amount" value="0.00">
                    </template>
                    <x-ui.input name="minimum_order_amount" label="Minimum Order Amount" :value="isset($coupon) && $coupon->minimum_order_amount !== null ? number_format((float) $coupon->minimum_order_amount, 2, '.', '') : ''" />
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <input type="checkbox" name="applies_to_products" value="1" x-model="productsEnabled" class="h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                                <span>Allow use with products</span>
                            </label>
                            <x-ui.button type="button" color="outline" class="px-4 py-1.5 text-sm" x-on:click="openProductsPicker()">Edit</x-ui.button>
                        </div>

                        <template x-if="productsEnabled">
                            <div>
                                <template x-if="productSelections.length === 0">
                                    <ul class="list-disc space-y-1 pl-12 text-sm text-gray-700">
                                        <li class="list-item">
                                            <span>All products are allowed.</span>
                                        </li>
                                    </ul>
                                </template>
                                <template x-if="productSelections.length > 0">
                                    <ul class="list-disc space-y-1 pl-12 text-sm text-gray-700">
                                        <template x-for="item in productSelections" :key="'product-' + item.id">
                                            <li class="list-item">
                                                <span class="truncate" x-text="item.label"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                        </template>
                        <template x-if="productsEnabled">
                            <div>
                                <template x-for="item in productSelections" :key="'product-input-' + item.id">
                                    <input type="hidden" name="product_ids[]" :value="item.id">
                                </template>
                            </div>
                        </template>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <input type="checkbox" name="applies_to_workshops" value="1" x-model="workshopsEnabled" class="h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                                <span>Allow use with workshops</span>
                            </label>
                            <x-ui.button type="button" color="outline" class="px-4 py-1.5 text-sm" x-on:click="openWorkshopsPicker()">Edit</x-ui.button>
                        </div>

                        <template x-if="workshopsEnabled">
                            <div>
                                <template x-if="workshopSelections.length === 0">
                                    <ul class="list-disc space-y-1 pl-12 text-sm text-gray-700">
                                        <li class="list-item">
                                            <span>All workshops are allowed.</span>
                                        </li>
                                    </ul>
                                </template>
                                <template x-if="workshopSelections.length > 0">
                                    <ul class="list-disc space-y-1 pl-12 text-sm text-gray-700">
                                        <template x-for="item in workshopSelections" :key="'workshop-' + item.id">
                                            <li class="list-item">
                                                <span class="truncate" x-text="item.label"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                        </template>
                        <template x-if="workshopsEnabled">
                            <div>
                                <template x-for="item in workshopSelections" :key="'workshop-input-' + item.id">
                                    <input type="hidden" name="workshop_ids[]" :value="item.id">
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input name="usage_limit" label="Total Usage Limit" labelInfo="(Optional)" type="number" min="1" :value="$coupon->usage_limit ?? ''" />
                    <x-ui.input name="usage_limit_per_user" label="Per User Limit" labelInfo="(Optional)" type="number" min="1" :value="$coupon->usage_limit_per_user ?? ''" />
                    <x-ui.input name="starts_at" label="Starts At" labelInfo="(Optional)" type="datetime-local" :value="old('starts_at', isset($coupon) && $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : '')" />
                    <div>
                        <x-ui.input
                            name="ends_at"
                            label="Ends At"
                            labelInfo="(Optional)"
                            type="datetime-local"
                            :value="$resolvedEndsAt"
                            x-model="endsAtDraft"
                            x-on:change="checkEndsAtWarning(true)"
                            x-on:blur="checkEndsAtWarning(true)"
                        />
                        <div x-show="isEndsAtPast()" x-cloak class="ml-2 -mt-2 text-xs text-amber-700">
                            This voucher will be inactive immediately because the end time is in the past.
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit">Save Voucher</x-ui.button>
                    @isset($coupon)
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md bg-danger-color px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition hover:bg-danger-color-dark"
                            x-data
                            x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete voucher?', 'Are you sure you want to delete this voucher? This action cannot be undone.', '{{ route('admin.shop.coupon.destroy', $coupon) }}')"
                        >Delete Voucher</button>
                    @endisset
                </div>
            </div>

            <template x-teleport="body">
                <div
                    x-show="picker.open"
                    x-cloak
                    class="fixed inset-0 z-280 flex items-end justify-center bg-black/50 p-4 sm:items-center"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="picker.type === 'products' ? 'coupon-product-picker-title' : 'coupon-workshop-picker-title'"
                    @click.self="closePicker()"
                    @keydown.escape.window="if (picker.open) { closePicker() }"
                >
                    <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="border-b border-gray-200 px-6 py-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="picker.type === 'products' ? 'Products' : 'Workshops'"></div>
                                    <h2 class="mt-1 text-xl font-bold text-gray-900" :id="picker.type === 'products' ? 'coupon-product-picker-title' : 'coupon-workshop-picker-title'" x-text="pickerTitle()"></h2>
                                    <p class="mt-2 text-sm leading-6 text-gray-600" x-text="pickerDescription()"></p>
                                </div>
                                <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closePicker()" aria-label="Close picker">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 px-6 py-4">
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                <x-ui.input
                                    name="scope_search"
                                    label="Search"
                                    no-label="true"
                                    placeholder="Search by title, slug or SKU"
                                    x-ref="scopePickerSearch"
                                    x-model="picker.search"
                                    x-on:input.debounce.300ms="loadPickerItems(1)"
                                />
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="filter in pickerFiltersForCurrentType()" :key="filter.value">
                                        <button
                                            type="button"
                                            class="rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                                            :class="String(picker.filter) === String(filter.value) ? 'border-primary-color bg-primary-color text-white' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'"
                                            x-text="filter.label"
                                            x-on:click="setPickerFilter(filter.value)"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                            <div x-show="picker.busy" x-cloak class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">Loading options...</div>

                            <div x-show="!picker.busy && picker.items.length === 0" x-cloak class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-6 text-sm text-gray-600">
                                No options matched your search.
                            </div>

                            <div class="overflow-hidden rounded-2xl border border-gray-200" x-show="!picker.busy && picker.items.length > 0" x-cloak>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th class="w-16 px-4 py-3">Select</th>
                                                <th class="px-4 py-3">Item</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <template x-for="item in picker.items" :key="picker.type + '-' + item.id">
                                                <tr
                                                    class="cursor-pointer transition hover:bg-gray-50"
                                                    :class="isPickerItemSelected(item) ? 'bg-primary-color/5' : ''"
                                                    x-on:click="togglePickerItem(item)"
                                                >
                                                    <td class="px-4 py-3 align-middle">
                                                        <span
                                                            class="inline-flex h-5 w-5 items-center justify-center rounded border text-[11px] font-semibold"
                                                            :class="isPickerItemSelected(item) ? 'border-primary-color bg-primary-color text-white' : 'border-gray-300 bg-white text-transparent'"
                                                        >
                                                            <i class="fa-solid fa-check"></i>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle">
                                                        <div class="flex items-center justify-between gap-4">
                                                            <div>
                                                                <div class="font-semibold text-gray-900" x-text="item.label"></div>
                                                                <div class="mt-0.5 text-xs text-gray-500" x-text="item.subtitle || 'No additional details'"></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 px-6 py-4">
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                                <x-ui.button type="button" color="outline" x-on:click="clearCurrentPickerSelection()">
                                    <span x-text="picker.type === 'products' ? 'Allow all products' : 'Allow all workshops'"></span>
                                </x-ui.button>
                                <x-ui.button type="button" color="secondary" x-on:click="closePicker()">Cancel</x-ui.button>
                                <x-ui.button type="button" x-on:click="finishPickerSelection()">Done</x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </form>
    </x-container>
</x-layout>
