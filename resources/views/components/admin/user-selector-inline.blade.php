@props([
'users' => collect(),
'selectedUserId' => '',
'fieldName' => 'user_id',
'lookupName' => 'linked_user_lookup',
'label' => 'Linked User',
'info' => 'Search by name/company/email. Select a suggestion to link this record.',
'disabled' => false,
'allowCreate' => true,
])

@php
$userLookupOptions = collect($users ?? [])->map(function ($user) {
$name = trim((string) $user->getName());
$email = trim((string) ($user->email ?? ''));
$company = trim((string) ($user->company ?? ''));

$displayLabel = $name !== '' ? $name : $email;
if ($company !== '') {
$displayLabel .= ' - '.$company;
}
if ($email !== '') {
$displayLabel .= ' ('.$email.')';
}

return [
'id' => (string) $user->id,
'label' => $displayLabel,
];
})->values();
$userLookupMap = $userLookupOptions->mapWithKeys(fn ($item) => [$item['label'] => $item['id']])->all();
$resolvedSelectedUserId = (string) $selectedUserId;
$selectedUser = $userLookupOptions->first(fn ($item) => $item['id'] === $resolvedSelectedUserId);
$selectedUserLabel = is_array($selectedUser) ? ($selectedUser['label'] ?? '') : '';
@endphp

<div class="mb-2" x-data="{
    linkedUserLabel: @js($selectedUserLabel),
    linkedUserMap: @js($userLookupMap),
    linkedUsers: @js($userLookupOptions->all()),
    linkedUserOpen: false,
    linkedUserSelectedIndex: -1,
    linkedUserFiltered: [],
    createUserOpen: false,
    createUserSubmitting: false,
    createUserError: '',
    createUserTab: 'contact',
    shippingSameBilling: true,
    newUser: {
        firstname: '',
        surname: '',
        company: '',
        email: '',
        phone: '',
        billing_address: '',
        billing_address2: '',
        billing_city: '',
        billing_state: '',
        billing_postcode: '',
        billing_country: '',
        shipping_address: '',
        shipping_address2: '',
        shipping_city: '',
        shipping_state: '',
        shipping_postcode: '',
        shipping_country: '',
    },
    syncLinkedUserId() {
        const matched = this.linkedUsers.find((option) => option.label === this.linkedUserLabel);
        const userId = matched?.id || this.linkedUserMap[this.linkedUserLabel] || '';
        this.$refs.linkedUserId.value = userId;
    },
    refreshLinkedUsers() {
        const needle = String(this.linkedUserLabel || '').toLowerCase().trim();
        if (needle === '') {
            this.linkedUserFiltered = [];
            this.linkedUserSelectedIndex = -1;
            this.linkedUserOpen = false;
            return;
        }
        this.linkedUserFiltered = this.linkedUsers
            .filter((option) => String(option?.label || '').toLowerCase().includes(needle))
            .slice(0, 8);
        this.linkedUserSelectedIndex = this.linkedUserFiltered.length > 0 ? 0 : -1;
        this.linkedUserOpen = this.linkedUserFiltered.length > 0;
    },
    moveLinkedUser(step) {
        if (!this.linkedUserOpen) {
            this.refreshLinkedUsers();
            return;
        }
        const len = this.linkedUserFiltered.length;
        if (!len) {
            return;
        }
        this.linkedUserSelectedIndex = (this.linkedUserSelectedIndex + step + len) % len;
    },
    chooseLinkedUser(option) {
        this.linkedUserLabel = option?.label || '';
        this.$refs.linkedUserId.value = option?.id || '';
        this.linkedUserOpen = false;
        this.linkedUserSelectedIndex = -1;
    },
    confirmLinkedUser() {
        if (!this.linkedUserOpen) {
            return;
        }
        if (this.linkedUserSelectedIndex < 0 || this.linkedUserSelectedIndex >= this.linkedUserFiltered.length) {
            return;
        }
        this.chooseLinkedUser(this.linkedUserFiltered[this.linkedUserSelectedIndex]);
    },
    openCreateUser() {
        this.createUserOpen = true;
        this.createUserTab = 'contact';
        this.shippingSameBilling = true;
        this.createUserError = '';
        this.newUser = {
            firstname: '',
            surname: '',
            company: '',
            email: '',
            phone: '',
            billing_address: '',
            billing_address2: '',
            billing_city: '',
            billing_state: '',
            billing_postcode: '',
            billing_country: '',
            shipping_address: '',
            shipping_address2: '',
            shipping_city: '',
            shipping_state: '',
            shipping_postcode: '',
            shipping_country: '',
        };
    },
    closeCreateUser() {
        this.createUserOpen = false;
        this.createUserError = '';
    },
    syncShippingAddress() {
        this.newUser.shipping_address = this.newUser.billing_address;
        this.newUser.shipping_address2 = this.newUser.billing_address2;
        this.newUser.shipping_city = this.newUser.billing_city;
        this.newUser.shipping_state = this.newUser.billing_state;
        this.newUser.shipping_postcode = this.newUser.billing_postcode;
        this.newUser.shipping_country = this.newUser.billing_country;
    },
    async submitCreateUser() {
        if (this.createUserSubmitting) {
            return;
        }
        this.createUserSubmitting = true;
        this.createUserError = '';
        try {
            if (this.shippingSameBilling) {
                this.syncShippingAddress();
            }
            const response = await fetch('{{ route('admin.user.store-inline') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(this.newUser),
            });
            const payload = await response.json();
            if (!response.ok || !payload?.success || !payload?.user) {
                const firstError = payload?.errors ? Object.values(payload.errors)?.[0]?.[0] : null;
                throw new Error(firstError || payload?.message || 'Unable to create user.');
            }
            const user = payload.user;
            this.linkedUserMap[user.label] = user.id;
            this.linkedUsers = [...this.linkedUsers, { id: user.id, label: user.label }]
                .filter((value, index, array) => array.findIndex((item) => item.id === value.id) === index);
            this.linkedUserLabel = user.label;
            this.$refs.linkedUserId.value = user.id;
            this.refreshLinkedUsers();
            this.closeCreateUser();
        } catch (error) {
            this.createUserError = error?.message || 'Unable to create user.';
        } finally {
            this.createUserSubmitting = false;
        }
    },
}" x-init="syncLinkedUserId()">
    <div class="flex items-end gap-2">
        <div class="flex-1">
            <label for="{{ $lookupName }}" class="block text-sm pl-1">{{ $label }}</label>
            <div class="relative mt-1" x-on:click.away="linkedUserOpen = false">
                <div class="flex items-center gap-4">
                    <input
                        id="{{ $lookupName }}"
                        type="text"
                        class="disabled:bg-gray-100 bg-white block px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 border-gray-300 focus:border-indigo-300 focus:ring-indigo-300"
                        x-model="linkedUserLabel"
                        x-on:focus="linkedUserOpen = false"
                        x-on:input="syncLinkedUserId(); refreshLinkedUsers()"
                        x-on:keydown.arrow-down.prevent="moveLinkedUser(1)"
                        x-on:keydown.arrow-up.prevent="moveLinkedUser(-1)"
                        x-on:keydown.enter.prevent="confirmLinkedUser()"
                        x-on:keydown.escape.prevent="linkedUserOpen = false"
                        x-on:blur="syncLinkedUserId(); setTimeout(() => { linkedUserOpen = false }, 120)"
                        autocomplete="off"
                        placeholder="Search by name/company/email"
                        @if($disabled) disabled @endif />
                    @if(! $disabled && $allowCreate)
                    <x-ui.button type="button" x-on:click.prevent="openCreateUser()">New User</x-ui.button>
                    @endif
                </div>
                <div x-show="linkedUserOpen" x-cloak class="absolute z-40 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden">
                    <ul class="max-h-60 overflow-auto py-1">
                        <template x-for="(item, index) in linkedUserFiltered" :key="item.id + '-' + index">
                            <li
                                class="cursor-pointer px-3 py-2 text-sm"
                                :class="index === linkedUserSelectedIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-800 hover:bg-gray-100'"
                                x-on:mouseenter="linkedUserSelectedIndex = index"
                                x-on:mousedown.prevent="chooseLinkedUser(item)"
                                x-text="item.label"></li>
                        </template>
                    </ul>
                </div>
            </div>
            <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
            @if($errors->has($fieldName))
                <div class="text-xs text-red-600 ml-2 mt-1">{{ $errors->first($fieldName) }}</div>
            @endif
            <input type="hidden" name="{{ $fieldName }}" x-ref="linkedUserId" value="{{ $resolvedSelectedUserId }}">
        </div>
    </div>

    @if($allowCreate)
    <div x-show="createUserOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="closeCreateUser()" x-on:keydown.enter.prevent="submitCreateUser()">
        <div class="w-full max-w-xl rounded-lg bg-white p-4 shadow-lg">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Create User</h3>
                <button type="button" class="text-gray-600 hover:text-black" x-on:click.prevent="closeCreateUser()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="mb-4 flex gap-2 border-b border-gray-200 pb-2">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold" :class="createUserTab === 'contact' ? 'bg-primary-color text-white' : 'bg-gray-100 text-gray-700'" x-on:click.prevent="createUserTab = 'contact'">Contact</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold" :class="createUserTab === 'billing' ? 'bg-primary-color text-white' : 'bg-gray-100 text-gray-700'" x-on:click.prevent="createUserTab = 'billing'">Billing</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold" :class="createUserTab === 'shipping' ? 'bg-primary-color text-white' : 'bg-gray-100 text-gray-700'" x-on:click.prevent="createUserTab = 'shipping'">Shipping</button>
            </div>

            <div x-show="createUserTab === 'contact'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.input noLabel="true" label="First Name" name="new_user_firstname" x-model="newUser.firstname" />
                <x-ui.input noLabel="true" label="Surname" name="new_user_surname" x-model="newUser.surname" />
                <x-ui.input noLabel="true" label="Company" name="new_user_company" x-model="newUser.company" />
                <x-ui.input noLabel="true" label="Phone" name="new_user_phone" x-model="newUser.phone" />
                <div class="sm:col-span-2">
                    <x-ui.input noLabel="true" label="Email (Required)" name="new_user_email" x-model="newUser.email" />
                </div>
            </div>

            <div x-show="createUserTab === 'billing'" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2" x-on:input="if (shippingSameBilling) { syncShippingAddress(); }">
                <div class="sm:col-span-2">
                    <x-ui.input noLabel="true" label="Billing Address" name="new_user_billing_address" x-model="newUser.billing_address" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input noLabel="true" label="Billing Address 2" name="new_user_billing_address2" x-model="newUser.billing_address2" />
                </div>
                <x-ui.input noLabel="true" label="Billing City" name="new_user_billing_city" x-model="newUser.billing_city" />
                <x-ui.input noLabel="true" label="Billing State" name="new_user_billing_state" x-model="newUser.billing_state" />
                <x-ui.input noLabel="true" label="Billing Postcode" name="new_user_billing_postcode" x-model="newUser.billing_postcode" />
                <x-ui.input noLabel="true" label="Billing Country" name="new_user_billing_country" x-model="newUser.billing_country" />
            </div>

            <div x-show="createUserTab === 'shipping'" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.checkbox
                    class="sm:col-span-2 mb-1"
                    label="Same as billing address"
                    name="new_user_shipping_same_billing"
                    x-model="shippingSameBilling"
                    x-on:change="if (shippingSameBilling) { syncShippingAddress(); }" />
                <div class="sm:col-span-2">
                    <x-ui.input noLabel="true" label="Shipping Address" name="new_user_shipping_address" x-model="newUser.shipping_address" x-bind:readonly="shippingSameBilling" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input noLabel="true" label="Shipping Address 2" name="new_user_shipping_address2" x-model="newUser.shipping_address2" x-bind:readonly="shippingSameBilling" />
                </div>
                <x-ui.input noLabel="true" label="Shipping City" name="new_user_shipping_city" x-model="newUser.shipping_city" x-bind:readonly="shippingSameBilling" />
                <x-ui.input noLabel="true" label="Shipping State" name="new_user_shipping_state" x-model="newUser.shipping_state" x-bind:readonly="shippingSameBilling" />
                <x-ui.input noLabel="true" label="Shipping Postcode" name="new_user_shipping_postcode" x-model="newUser.shipping_postcode" x-bind:readonly="shippingSameBilling" />
                <x-ui.input noLabel="true" label="Shipping Country" name="new_user_shipping_country" x-model="newUser.shipping_country" x-bind:readonly="shippingSameBilling" />
            </div>

            <div x-show="createUserError !== ''" class="mt-2 text-sm text-red-600" x-text="createUserError"></div>

            <div class="mt-4 flex justify-end gap-2">
                <x-ui.button type="button" color="secondary" x-on:click.prevent="closeCreateUser()">Cancel</x-ui.button>
                <x-ui.button type="button" x-bind:disabled="createUserSubmitting" x-on:click.prevent="submitCreateUser()">
                    <span x-show="!createUserSubmitting">Create</span>
                    <span x-show="createUserSubmitting" x-cloak>Saving...</span>
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif
</div>
