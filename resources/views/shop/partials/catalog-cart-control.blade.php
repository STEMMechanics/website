<div
    class="shop-catalog-cart-control space-y-2"
    x-data="{
        lineKey: @js($lineKey),
        fallbackMaxQuantity: {{ $fallbackMaxQuantity }},
        quantity: 0,
        maxQuantity: {{ $fallbackMaxQuantity }},
        busy: false,
        syncFromCart(cart) {
            const line = Array.isArray(cart?.lines)
                ? cart.lines.find((item) => String(item?.key || '') === String(this.lineKey))
                : null;

            this.quantity = Number(line?.quantity || 0);
            this.maxQuantity = Number(line?.max_quantity || this.fallbackMaxQuantity || 99);
        },
        async add(form) {
            if (this.busy || !(form instanceof HTMLFormElement)) {
                return;
            }

            if (!window.SM?.shopCart) {
                form.submit();
                return;
            }

            this.busy = true;

            try {
                await window.SM.shopCart.submitAddForm(form, {
                    showAddSheet: true,
                    addedLineKey: this.lineKey,
                });
            } finally {
                this.busy = false;
            }
        },
        async change(nextQuantity) {
            if (this.busy || !window.SM?.shopCart) {
                return;
            }

            const resolvedQuantity = window.SM.toBoundedInt(nextQuantity, {
                min: 0,
                max: this.maxQuantity,
                allowNull: false,
            });
            this.busy = true;

            try {
                if (resolvedQuantity <= 0) {
                    await window.SM.shopCart.removeLine(this.lineKey, {
                        shippingCountry: window.SM.shopCart.getState()?.shipping_country || 'Australia',
                        showNotice: false,
                    });
                    if (typeof window.SM.notice === 'function') {
                        window.SM.alert('Removed from cart', @js($removeMessage), 'danger');
                    }
                    return;
                }

                await window.SM.shopCart.updateQuantity(this.lineKey, resolvedQuantity, {
                    max: this.maxQuantity,
                    shippingCountry: window.SM.shopCart.getState()?.shipping_country || 'Australia',
                    showNotice: false,
                });
            } finally {
                this.busy = false;
            }
        },
        init() {
            if (!window.SM?.shopCart) {
                return;
            }

            this.syncFromCart(window.SM.shopCart.getState());
            window.SM.shopCart.subscribe((cart) => this.syncFromCart(cart));
        },
    }"
>
    <form
        method="POST"
        action="{{ route('shop.cart.add', $product) }}"
        x-on:submit.prevent="add($event.target)"
        x-show="quantity <= 0"
        class="m-0"
    >
        @csrf
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
        @if($defaultVariant)
            <input type="hidden" name="product_variant_id" value="{{ $defaultVariant->id }}">
        @endif
        <x-ui.button type="submit" class="shop-catalog-add-button w-full" x-bind:disabled="busy">
            <span x-show="!busy">Add to Cart</span>
            <span x-show="busy" x-cloak>Adding...</span>
        </x-ui.button>
    </form>

    <div x-show="quantity > 0" x-cloak>
        <div class="shop-catalog-stepper flex items-center gap-2 rounded border border-gray-300 bg-white">
            <button
                type="button"
                class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-r-gray-300 border-r text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="busy"
                @click="change(quantity - 1)"
            >-</button>
            <input
                type="number"
                min="0"
                :max="maxQuantity"
                :value="quantity"
                class="shop-catalog-stepper-input h-9 min-w-14 p-1 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                :disabled="busy"
                @change="change($event.target.value)"
            />
            <button
                type="button"
                class="shop-catalog-stepper-button inline-flex h-9 w-9 items-center justify-center p-1 border-l-gray-300 border-l text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="busy || quantity >= maxQuantity"
                @click="change(quantity + 1)"
            >+</button>
        </div>
    </div>
</div>
