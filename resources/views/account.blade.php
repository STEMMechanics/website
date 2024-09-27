@php
$user = auth()->user();

$shipping_same_billing = $user->shipping_address === $user->billing_address
    && $user->shipping_address2 === $user->billing_address2
    && $user->shipping_city === $user->billing_city
    && $user->shipping_state === $user->billing_state
    && $user->shipping_postcode === $user->billing_postcode
    && $user->shipping_country === $user->billing_country;
@endphp

<x-layout>
    <x-mast>Account Settings</x-mast>
    <x-container>
        <form method="POST" action="{{ route('account.update') }}" x-data x-on:submit.prevent="SM.updateShippingAddress(); $el.submit()">
            @csrf
            <h3 class="text-lg font-bold mt-4 mb-3">Contact Information</h3>
            <div class="flex flex-col sm:gap-8 sm:flex-row">
                <div class="flex-1">
                    <x-ui.input label="First name" name="firstname" value="{{ $user->firstname }}" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Surname" name="surname" value="{{ $user->surname }}" />
                </div>
            </div>
            <div class="flex flex-col sm:gap-8 sm:flex-row">
                <div class="flex-1">
                    <x-ui.input type="email" label="Email" name="email" value="{{ $user->email }}" info="{{ $user->email_update_pending ? 'Pending request to change to ' . $user->email_update_pending : '' }}"/>
                </div>
                <div class="flex-1">
                    <x-ui.input label="Phone" name="phone" value="{{ $user->phone }}" />
                </div>
            </div>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Email Subscriptions</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Upcoming Workshops" name="subscribed" checked="{{ $user->subscribed }}" />
                </div>
            </section>


            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Billing Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.input label="Address" name="billing_address" value="{{ $user->billing_address }}" />
                    <x-ui.input label="Address 2" name="billing_address2" value="{{ $user->billing_address2 }}" />
                    <x-ui.input label="City" name="billing_city" value="{{ $user->billing_city }}" />
                    <div class="flex flex-col sm:gap-8 sm:flex-row">
                        <div class="flex-1">
                            <x-ui.input label="State" name="billing_state" value="{{ $user->billing_state }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="billing_postcode" value="{{ $user->billing_postcode }}" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="billing_country" value="{{ $user->billing_country }}" />
                </div>
            </section>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Shipping Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Same as billing address" name="shipping_same_billing" checked="{{ $shipping_same_billing }}" x-data x-on:click="SM.updateShippingAddress" />
                    <x-ui.input label="Address" name="shipping_address" value="{{ $user->shipping_address }}" readonly="{{ $shipping_same_billing }}" />
                    <x-ui.input label="Address 2" name="shipping_address2" value="{{ $user->shipping_address2 }}" readonly="{{ $shipping_same_billing }}" />
                    <x-ui.input label="City" name="shipping_city" value="{{ $user->shipping_city }}" readonly="{{ $shipping_same_billing }}" />
                    <div class="flex flex-col sm:gap-8 sm:flex-row">
                        <div class="flex-1">
                            <x-ui.input label="State" name="shipping_state" value="{{ $user->shipping_state }}" readonly="{{ $shipping_same_billing }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="shipping_postcode" value="{{ $user->shipping_postcode }}" readonly="{{ $shipping_same_billing }}" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="shipping_country" value="{{ $user->shipping_country }}" readonly="{{ $shipping_same_billing }}" />
                </div>
            </section>

            <div class="flex justify-between mt-8">
                @if($user->id !== 1)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete account?', 'Are you sure you want to delete your account? This action cannot be undone.<br /><br />Any workshop tickets will remain valid.', '{{ route('account.destroy') }}')">Delete</x-ui.button>
                @else
                    <div></div>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
