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

            <section x-data="{ open: false }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}"
                       class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Two Factor Authentication</h3>
                </a>
                <div class="px-4 mb-4" x-show="open">
                    <div class="flex items-center border border-gray-300 rounded bg-white pl-2 pr-4 py-3 mb-4">
                        <div class="bg-gray-200 rounded-full w-14 h-14 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-envelope text-2xl"></i>
                        </div>
                        <div class="mx-4 flex-grow">
                            <p class="flex mb-2">
                                <span class="text-sm font-bold mr-2">Use Email</span>
                                <span class="text-xs bg-green-500 text-white rounded px-2 py-0.5">Enabled</span>
                            </p>
                            <p class="text-xs">Use the security link sent to your email address as your two-factor authentication (2FA). The security link will be sent to the address associated with your account.</p>
                        </div>
                    </div>
                    <div class="border border-gray-300 rounded bg-white pl-2 pr-4 py-3">
                        <div class="flex items-center">
                            <div class="bg-gray-200 rounded-full w-14 h-14 flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-mobile-screen-button text-2xl"></i>
                            </div>
                            <div class="mx-4 flex-grow">
                                <p class="flex mb-2">
                                    <span class="text-sm font-bold mr-2">Use Authenticator App</span>
                                    <span x-cloak x-show="!$store.tfa.enabled" class="text-xs bg-red-500 text-white rounded px-2 py-0.5">Disabled</span>
                                    <span x-cloak x-show="$store.tfa.enabled" class="text-xs bg-green-500 text-white rounded px-2 py-0.5">Enabled</span>
                                </p>
                                <p class="text-xs">Use an Authenticator App as your two-factor authenticator. When you sign in you'll be asked to use the security code provided by your Authenticator App.</p>
                            </div>
                            <div class="flex flex-col text-nowrap gap-2">
                                <x-ui.button x-show="!$store.tfa.enabled" id="tfa_button" type="button" color="primary-outline" x-data x-on:click.prevent="setupTFA()">Setup</x-ui.button>
                                <x-ui.button x-show="$store.tfa.enabled" type="button" color="danger-outline" x-data x-on:click.prevent="destroyTFA()">Disable</x-ui.button>
                                <a href="#" x-show="$store.tfa.enabled" x-on:click.prevent="resetBackupCodes($event)" class="text-xs link">Reset Backup Codes</a>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t flex items-center justify-center gap-4" x-cloak x-show="$store.tfa.show && !$store.tfa.loading">
                            <img src="/loading.gif" id="tfa_image_loader" alt="loading" width="100" height="100"/>
                            <img src="" id="tfa_image" alt="QR Code" width="150" height="150" style="display:none" onload="this.style.display='block';document.getElementById('tfa_image_loader').style.display='none';"/>
                            <div>
                                <p class="text-xs mb-2">Scan the QR Code into your Authenticator App and enter the code provided below</p>
                                <div class="flex items-center gap-4 justify-center">
                                    <x-ui.input name="code" id="code" class="mb-0" />
                                    <x-ui.button class="mt-1" type="button" color="primary-outline" x-on:click.prevent="linkTFA()">Link</x-ui.button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t flex justify-center" x-cloak x-show="$store.tfa.loading">
                            <img src="/loading.gif" alt="loading" width="100" height="100"/>
                        </div>
                        <div class="mt-4 pt-4 border-t flex justify-center" x-cloak x-show="$store.tfa.codes && !$store.tfa.loading">
                            <div class="w-[34rem] flex items-center gap-4">
                                <div class="w-[18rem] mx-auto">
                                    <p class="text-sm font-bold mb-1">Save your Backup Codes</p>
                                    <ul class="ml-6 mb-4 text-xs list-disc">
                                        <li>Keep these backup codes safe</li>
                                        <li>You can only use each one once</li>
                                        <li>They will not be shown again</li>
                                        <li>Any existing codes can no longer be used</li>
                                    </ul>
                                </div>
                                <div class="w-[16rem] bg-gray-200 p-4 text-sm font-mono flex flex-wrap justify-center">
                                    <template x-for="(code, idx) in $store.tfa.codes" :key="idx">
                                        <p class="mx-4" x-text="code"></p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
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

{{ $codes ?? '' }}

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('tfa', {
            show: false,
            secret: null,
            enabled: {{ $user->tfa_secret !== null ? 'true' : 'false'}},
            codes: null,
            loading: false
        });
    });

    function setupTFA() {
        document.getElementById('tfa_button').disabled = true;
        axios.get('/account/2fa')
            .then(response => {
                if(response.data.secret) {
                    Alpine.store('tfa').show = true;
                    Alpine.store('tfa').secret = response.data.secret;
                    document.getElementById('tfa_image').src = '/account/2fa/image?secret=' + response.data.secret;
                } else {
                    SM.alert('2FA Error', 'An error occurred while setting up two-factor authentication. Please try again later', 'danger');
                }
            })
            .catch(() => {
                SM.alert('2FA Error', 'An error occurred while setting up two-factor authentication. Please try again later', 'danger');
            });
    }

    function linkTFA() {
        Alpine.store('tfa').loading = true;
        axios.post('/account/2fa', {
            code: document.getElementById('code').value,
            secret: Alpine.store('tfa').secret,
        })
            .then(response => {
                console.log(response.data);
                if(response.data.success) {
                    SM.alert('2FA Linked', 'Two-factor authentication has been successfully linked to your account', 'success');
                    document.getElementById('tfa_button').disabled = false;
                    document.getElementById('code').value = '';
                    document.getElementById('tfa_image').src = '';
                    Alpine.store('tfa').show = false;
                    Alpine.store('tfa').enabled = true;
                    Alpine.store('tfa').codes = response.data.codes;
                } else {
                    SM.alert('2FA Error', 'An error occurred while linking two-factor authentication. Please try again later', 'danger');
                }
            })
            .catch(() => {
                SM.alert('2FA Error', 'An error occurred while linking two-factor authentication. Please try again later', 'danger');
            })
            .finally(() => {
                Alpine.store('tfa').loading = false;
            });
    }

    function resetBackupCodes(event) {
        event.target.classList.add('disabled');
        Alpine.store('tfa').codes = null;
        Alpine.store('tfa').loading = true;

        axios.post('/account/2fa/reset-backup-codes')
            .then(response => {
                if(response.data.success) {
                    Alpine.store('tfa').codes = response.data.codes;
                } else {
                    SM.alert('2FA Error', 'An error occurred while resetting your backup codes. Please try again later', 'danger');
                }
            })
            .catch(() => {
                SM.alert('2FA Error', 'An error occurred while resetting your backup codes. Please try again later', 'danger');
            })
            .finally(() => {
                event.target.classList.remove('disabled');
                Alpine.store('tfa').loading = false;
            });
    }

    function destroyTFA() {
        SM.confirm('Disable 2FA', 'Are you sure you want to remove two-factor authentication from your account?', 'Disable', (confirm) => {
            if(confirm) {
                axios.delete('/account/2fa')
                    .then(response => {
                        if (response.data.success) {
                            SM.alert('2FA Disabled', 'Two-factor authentication has been successfully disabled on your account', 'success');
                            Alpine.store('tfa').enabled = false;
                            Alpine.store('tfa').codes = null;
                        } else {
                            SM.alert('2FA Error', 'An error occurred while disabling two-factor authentication. Please try again later', 'danger');
                        }
                    })
                    .catch(() => {
                        SM.alert('2FA Error', 'An error occurred while disabling two-factor authentication. Please try again later', 'danger');
                    });
            }
        }, {
            confirmButtonText: 'Disable'
        });
    }
</script>
