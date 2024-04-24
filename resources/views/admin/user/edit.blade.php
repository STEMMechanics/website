@props(['user'])

@php
    $billing_same_home = $user->home_address === $user->billing_address
        && $user->home_address2 === $user->billing_address2
        && $user->home_city === $user->billing_city
        && $user->home_state === $user->billing_state
        && $user->home_postcode === $user->billing_postcode
        && $user->home_country === $user->billing_country;
@endphp

<x-layout>
    <x-mast backRoute="admin.user.index" backTitle="Users">Edit User</x-mast>

    <x-container>
        <form method="POST" action="{{ route('admin.user.update', $user) }}" x-data x-on:submit.prevent="SM.updateBillingAddress(); $el.submit()">
            @method('PUT')
            @csrf
            <h3 class="text-lg font-bold mt-4 mb-3">Contact Information</h3>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input label="First name" name="firstname" value="{{ $user->firstname }}" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Surname" name="surname" value="{{ $user->surname }}" />
                </div>
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input type="email" label="Email" name="email" value="{{ $user->email }}" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Phone" name="phone" value="{{ $user->phone }}" />
                </div>
            </div>

            {{ $user }}
            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Email Subscriptions</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Upcoming Workshops" name="billing_same_home" checked="{{ $billing_same_home }}" />
                </div>
            </section>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Home Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.input label="Address" name="home_address" value="{{ $user->home_address }}" />
                    <x-ui.input label="Address 2" name="home_address2" value="{{ $user->home_address2 }}" />
                    <x-ui.input label="City" name="home_city" value="{{ $user->home_city }}" />
                    <div class="flex gap-8">
                        <div class="flex-1">
                            <x-ui.input label="State" name="home_state" value="{{ $user->home_state }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="home_postcode" value="{{ $user->home_postcode }}" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="home_country" value="{{ $user->home_country }}" />
                </div>
            </section>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Billing Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Same as home address" name="billing_same_home" checked="{{ $billing_same_home }}" x-data x-on:click="SM.updateBillingAddress" />
                    <x-ui.input label="Address" name="billing_address" value="{{ $user->billing_address }}" readonly="{{ $billing_same_home }}" />
                    <x-ui.input label="Address 2" name="billing_address2" value="{{ $user->billing_address2 }}" readonly="{{ $billing_same_home }}" />
                    <x-ui.input label="City" name="billing_city" value="{{ $user->billing_city }}" readonly="{{ $billing_same_home }}" />
                    <div class="flex gap-8">
                        <div class="flex-1">
                            <x-ui.input label="State" name="billing_state" value="{{ $user->billing_state }}" readonly="{{ $billing_same_home }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="billing_postcode" value="{{ $user->billing_postcode }}" readonly="{{ $billing_same_home }}" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="billing_country" value="{{ $user->billing_country }}" readonly="{{ $billing_same_home }}" />
                </div>
            </section>

            <div class="flex justify-between mt-8">
                @if($user->id !== 1)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete account?', 'Are you sure you want to delete this account? This action cannot be undone', '{{ route('admin.user.destroy', $user) }}')">Delete</x-ui.button>
                @else
                    <div></div>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
