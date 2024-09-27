<x-layout>
    <x-mast backRoute="admin.user.index" backTitle="Users">Create User</x-mast>

    <x-container>
        <form method="POST" action="{{ route('admin.user.store') }}" x-data x-on:submit.prevent="SM.updateShippingAddress(); $el.submit()">
            @csrf
            <h3 class="text-lg font-bold mt-4 mb-3">Contact Information</h3>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input label="First name" name="firstname" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Surname" name="surname" />
                </div>
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input type="email" label="Email" name="email" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Phone" name="phone" />
                </div>
            </div>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Billing Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.input label="Address" name="billing_address" />
                    <x-ui.input label="Address 2" name="billing_address2" />
                    <x-ui.input label="City" name="billing_city" />
                    <div class="flex gap-8">
                        <div class="flex-1">
                            <x-ui.input label="State" name="billing_state" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="billing_postcode" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="billing_country" />
                </div>
            </section>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Shipping Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Same as billing address" name="shipping_same_billing" checked="true" x-data x-on:click="SM.updateShippingAddress" />
                    <x-ui.input label="Address" name="shipping_address" />
                    <x-ui.input label="Address 2" name="shipping_address2" />
                    <x-ui.input label="City" name="shipping_city" />
                    <div class="flex gap-8">
                        <div class="flex-1">
                            <x-ui.input label="State" name="shipping_state" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="shipping_postcode" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="shipping_country" />
                </div>
            </section>

            <div class="flex justify-end mt-8">
                <x-ui.button type="submit">Create</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
