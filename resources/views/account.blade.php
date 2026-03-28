@php
$user = auth()->user();

$shipping_same_billing = $user->shipping_address === $user->billing_address
    && $user->shipping_address2 === $user->billing_address2
    && $user->shipping_city === $user->billing_city
    && $user->shipping_state === $user->billing_state
    && $user->shipping_postcode === $user->billing_postcode
    && $user->shipping_country === $user->billing_country;
$groupSlugs = $user->groupSlugs();
$rememberedDevices = collect($rememberedDevices ?? []);
$currentRememberedTokenId = (string) ($currentRememberedTokenId ?? '');
$keepSignedInDeviceOld = old('keep_signed_in_device');
$keepSignedInDeviceChecked = $keepSignedInDeviceOld !== null
    ? in_array((string) $keepSignedInDeviceOld, ['1', 'on', 'true'], true)
    : ($currentRememberedTokenId !== '');
$discussionNotificationCount = (int) ($discussionNotificationCount ?? 0);
@endphp

<x-layout>
    <x-mast description="Manage your public profile, addresses, subscriptions, and sign-in settings.">Account Settings</x-mast>

    <x-container inner-class="max-w-6xl">
        <form
            method="POST"
            action="{{ route('account.update') }}"
            id="account-settings-form"
            class="my-8"
            x-data
            x-on:submit.prevent="SM.updateShippingAddress(); $el.submit()"
        >
            @csrf
            <input type="hidden" name="remembered_device_hint" id="remembered_device_hint" value="" />
            <input type="hidden" name="remembered_device_touch_points" id="remembered_device_touch_points" value="" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Profile Details</h2>
                                <p class="mt-1 text-sm text-gray-600">Update the information used for your account, invoices, and contact history.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <x-ui.input label="First name" name="firstname" value="{{ $user->firstname }}" />
                            <x-ui.input label="Surname" name="surname" value="{{ $user->surname }}" />
                            <x-ui.input type="email" label="Email" name="email" value="{{ $user->email }}" info="{{ $user->email_update_pending ? 'Pending request to change to ' . $user->email_update_pending : '' }}"/>
                            <x-ui.input label="Username" name="username" value="{{ old('username', $user->username) }}" info="This can be changed at any time and is unique across the site. Don't use your real name!" />
                            <x-ui.input label="Phone" name="phone" value="{{ $user->phone }}" />
                            <x-ui.input label="Company (Optional)" name="company" value="{{ $user->company }}" />
                        </div>
                        @if($groupSlugs !== [])
                            <div class="mt-2 border-t border-gray-100 pt-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Your groups</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($groupSlugs as $groupSlug)
                                        <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-700">{{ $groupSlug }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Addresses</h2>
                            <p class="mt-1 text-sm text-gray-600">Keep your billing and shipping details current for orders and invoices.</p>
                        </div>

                        <div class="mt-6 grid gap-6 grid-cols-2">
                            <div class="rounded-2xl bg-gray-50 p-4 flex flex-col justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Billing address</h3>
                                <div class="mt-4">
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
                            </div>

                            <div class="rounded-2xl bg-gray-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">Shipping address</h3>
                                        <x-ui.checkbox class="mt-4" label="Same as billing" name="shipping_same_billing" checked="{{ $shipping_same_billing }}" x-data x-on:click="SM.updateShippingAddress" />
                                    </div>
                                </div>
                                <div class="mt-0">
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
                            </div>
                        </div>
                    </section>
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-1">
                    @include('account.partials.avatar-card')

                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Email Notifications</h2>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6">
                            <div class="rounded-2xl bg-gray-50 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">Receive Email subscriptions</h3>
                                <div class="mt-4">
                                    <x-ui.checkbox label="Upcoming Workshops" name="subscribed" checked="{{ $user->subscribed }}" />
                                </div>
                            </div>

                            <div class="rounded-2xl bg-gray-50 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">Discussion notifications</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    You are subscribed to
                                    <span class="font-semibold text-gray-900">{{ $discussionNotificationCount }}</span>
                                    {{ \Illuminate\Support\Str::plural('discussion thread', $discussionNotificationCount) }}.
                                </p>
                                <div class="mt-4 text-center">
                                    <x-ui.button type="submit" color="outline" form="discussion-unsubscribe-form">Unsubscribe from all</x-ui.button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="mt-6 space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Remembered Devices</h2>
                            <p class="mt-1 text-sm text-gray-600">Review devices that can stay signed in and remove any you no longer trust.</p>
                        </div>
                        <div class="whitespace-nowrap rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $rememberedDevices->count() }} saved</div>
                    </div>

                    <div class="rounded-2xl mt-4 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">This device</h3>
                        <p class="mt-1 text-sm text-gray-600">Control whether this browser stays signed in between visits.</p>
                        <div class="mt-4">
                            <input type="hidden" name="keep_signed_in_device" value="0" />
                            <x-ui.checkbox
                                    id="keep_signed_in_device"
                                    label="Keep me signed in on this device"
                                    name="keep_signed_in_device"
                                    checked="{{ $keepSignedInDeviceChecked }}"
                            />
                        </div>
                    </div>

                    <p id="remembered-devices-empty" class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 {{ $rememberedDevices->isEmpty() ? '' : 'hidden' }}">No remembered devices have been saved.</p>
                    <div id="remembered-devices-list" data-current-token-id="{{ $currentRememberedTokenId }}" class="mt-5 space-y-3 {{ $rememberedDevices->isEmpty() ? 'hidden' : '' }}">
                        @foreach($rememberedDevices as $device)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4" data-device-row data-device-id="{{ $device['id'] }}">
                                @php
                                    $defaultDeviceTitle = (string) ($device['default_title'] ?? 'Browser Device');
                                    $displayDeviceTitle = (string) ($device['title'] ?? $defaultDeviceTitle);
                                @endphp
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-semibold text-gray-900" data-device-title>{{ $displayDeviceTitle }}</div>
                                            <button
                                                type="button"
                                                class="text-gray-500 hover:text-primary-color"
                                                title="Edit device name"
                                                data-device-edit
                                                data-device-id="{{ $device['id'] }}"
                                                data-device-title="{{ $displayDeviceTitle }}"
                                                data-device-nickname="{{ (string) ($device['nickname'] ?? '') }}"
                                                data-device-default-title="{{ $defaultDeviceTitle }}"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            @if(! empty($device['is_current']))
                                                <div class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xxs font-semibold text-green-800">Current device</div>
                                            @endif
                                        </div>
                                        <div class="mt-2 grid gap-1 text-xs text-gray-600 sm:grid-cols-2">
                                            <div>IP: {{ $device['ip_address'] ?? '-' }}</div>
                                            @if(! empty($device['browser']))
                                                <div>Browser: {{ $device['browser'] }}</div>
                                            @endif
                                            <div>Added: {{ $device['created_label'] ?? '-' }}</div>
                                            <div>Last used: {{ $device['last_used_label'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <x-ui.button
                                        type="button"
                                        color="danger-outline"
                                        class="px-4! py-1.5!"
                                        data-device-remove
                                        data-device-id="{{ $device['id'] }}"
                                    >
                                        Remove
                                    </x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </form>

        <form id="discussion-unsubscribe-form" method="POST" action="{{ route('account.discussions.unsubscribe-all') }}" class="hidden">
            @csrf
        </form>

        <div class="mb-8 space-y-6">
            @include('account.partials.two-factor-card')

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @if($user->id !== 1)
                    <form method="POST" action="{{ route('account.destroy') }}" x-data x-on:submit.prevent="SM.confirmAccountDelete($el)">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="delete_discussion_threads" value="0" />
                        <x-ui.button type="submit" color="danger-outline">Delete account</x-ui.button>
                    </form>
                @else
                    <div></div>
                @endif
                <div class="flex justify-end">
                    <x-ui.button type="submit" form="account-settings-form">Save changes</x-ui.button>
                </div>
            </div>
        </div>
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

    function handleTfaImageLoad() {
        const image = document.getElementById('tfa_image');
        const loader = document.getElementById('tfa_image_loader');

        if (image) {
            image.style.display = 'block';
        }

        if (loader) {
            loader.style.display = 'none';
        }
    }

    function setupTFA() {
        document.getElementById('tfa_button').disabled = true;
        axios.get('/account/2fa')
            .then(response => {
                if(response.data.secret) {
                    Alpine.store('tfa').show = true;
                    Alpine.store('tfa').secret = response.data.secret;
                    document.getElementById('tfa_image').src = '/account/2fa/image?secret=' + response.data.secret;
                    document.getElementById('tfa_key').textContent = response.data.secret;
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

    const initRememberedDeviceActions = () => {
        const devicesList = document.getElementById('remembered-devices-list');
        const emptyState = document.getElementById('remembered-devices-empty');
        const keepSignedInCheckbox = document.getElementById('keep_signed_in_device');

        const updateEmptyState = () => {
            if (!devicesList || !emptyState) {
                return;
            }

            const remainingRows = devicesList.querySelectorAll('[data-device-row]').length;
            const hasRows = remainingRows > 0;
            devicesList.classList.toggle('hidden', !hasRows);
            emptyState.classList.toggle('hidden', hasRows);
        };

        const handleDeviceRename = (button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const deviceId = String(button.dataset.deviceId || '').trim();
            const defaultTitle = String(button.dataset.deviceDefaultTitle || 'Browser Device').trim();
            const nicknameValue = String(button.dataset.deviceNickname || '').trim();
            const row = button.closest('[data-device-row]');
            const titleElement = row ? row.querySelector('[data-device-title]') : null;

            if (deviceId === '' || !(titleElement instanceof HTMLElement)) {
                return;
            }

            const saveNickname = (nicknameRaw) => {
                const nickname = String(nicknameRaw || '').trim();
                button.disabled = true;

                axios.patch(`/account/devices/${encodeURIComponent(deviceId)}/nickname`, {
                    nickname: nickname,
                }, {
                    headers: {
                        'Accept': 'application/json',
                    },
                }).then(() => {
                    button.dataset.deviceNickname = nickname;
                    titleElement.textContent = nickname !== '' ? nickname : defaultTitle;
                    SM.alert('Device updated', 'Device name saved.', 'success');
                }).catch(() => {
                    SM.alert('Update failed', 'Could not save the device name right now.', 'danger');
                }).finally(() => {
                    button.disabled = false;
                });
            };

            if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
                Swal.fire({
                    title: 'Edit device name',
                    input: 'text',
                    inputValue: nicknameValue,
                    inputLabel: `Leave empty to use "${defaultTitle}"`,
                    inputPlaceholder: 'Device nickname',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    confirmButtonColor: '#0284c7',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    inputAttributes: {
                        maxlength: '60',
                        autocapitalize: 'off',
                        autocorrect: 'off',
                    },
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    saveNickname(result.value);
                });
                return;
            }

            const fallbackValue = window.prompt(`Edit device name (leave empty to use "${defaultTitle}")`, nicknameValue);
            if (fallbackValue === null) {
                return;
            }

            saveNickname(fallbackValue);
        };

        const handleDeviceRemove = (button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const deviceId = String(button.dataset.deviceId || '').trim();
            if (deviceId === '') {
                return;
            }

            const deleteRequest = () => {
                button.disabled = true;

                axios.delete(`/account/devices/${encodeURIComponent(deviceId)}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                }).then((response) => {
                    if (!response.data || !response.data.success) {
                        throw new Error('Device removal failed.');
                    }

                    const row = button.closest('[data-device-row]');
                    if (row instanceof HTMLElement) {
                        row.remove();
                    }

                    if (devicesList && keepSignedInCheckbox instanceof HTMLInputElement) {
                        const currentTokenId = String(devicesList.dataset.currentTokenId || '').trim();
                        if (currentTokenId !== '' && currentTokenId === deviceId) {
                            keepSignedInCheckbox.checked = false;
                            devicesList.dataset.currentTokenId = '';
                        }
                    }

                    updateEmptyState();
                    SM.alert('Device removed', 'The device has been removed.', 'success');
                }).catch(() => {
                    SM.alert('Remove failed', 'Could not remove the device right now.', 'danger');
                    button.disabled = false;
                });
            };

            if (window.SM && typeof window.SM.confirm === 'function') {
                SM.confirm('Remove device?', 'This device will no longer stay signed in.', 'Remove', (isConfirmed) => {
                    if (!isConfirmed) {
                        return;
                    }

                    deleteRequest();
                });
            }
        };

        document.querySelectorAll('[data-device-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                handleDeviceRename(button);
            });
        });

        document.querySelectorAll('[data-device-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                handleDeviceRemove(button);
            });
        });

        updateEmptyState();
    };

    document.addEventListener('DOMContentLoaded', () => {
        initRememberedDeviceActions();

        const hintInput = document.getElementById('remembered_device_hint');
        const touchPointsInput = document.getElementById('remembered_device_touch_points');
        if (!hintInput || !touchPointsInput) {
            return;
        }

        const touchPoints = Number.isFinite(navigator.maxTouchPoints) ? navigator.maxTouchPoints : 0;
        touchPointsInput.value = String(Math.max(0, Math.floor(touchPoints)));

        const ua = String(navigator.userAgent || '');
        const lowerUa = ua.toLowerCase();
        const isMacintosh = lowerUa.includes('macintosh');

        if (lowerUa.includes('ipad') || (isMacintosh && touchPoints > 1)) {
            hintInput.value = 'ipad';
            return;
        }
        if (lowerUa.includes('iphone')) {
            hintInput.value = 'iphone';
            return;
        }
        if (lowerUa.includes('android')) {
            hintInput.value = 'android';
            return;
        }
        if (isMacintosh) {
            hintInput.value = 'mac';
            return;
        }
        if (lowerUa.includes('windows')) {
            hintInput.value = 'windows';
            return;
        }

        hintInput.value = 'other';
    });
</script>
