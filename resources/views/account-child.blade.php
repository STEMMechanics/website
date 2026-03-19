@php
$user = auth()->user();

$rememberedDevices = collect($rememberedDevices ?? []);
$currentRememberedTokenId = (string) ($currentRememberedTokenId ?? '');
$keepSignedInDeviceOld = old('keep_signed_in_device');
$keepSignedInDeviceChecked = $keepSignedInDeviceOld !== null
    ? in_array((string) $keepSignedInDeviceOld, ['1', 'on', 'true'], true)
    : ($currentRememberedTokenId !== '');
$avatarPreviewUrl = old('avatar_media_name', $user->avatar_media_name)
    ? ($user->avatarMedia?->thumbnail ?? '')
    : '';
$avatarZoom = (int) old('avatar_zoom', $user->avatar_zoom ?? 100);
$avatarOffsetX = (int) old('avatar_offset_x', $user->avatar_offset_x ?? 0);
$avatarOffsetY = (int) old('avatar_offset_y', $user->avatar_offset_y ?? 0);
@endphp

<x-layout>
    <x-mast description="Manage the parts of this child account that are available directly to the child user.">Child Account Settings</x-mast>

    <x-container inner-class="max-w-5xl">
        <form
            method="POST"
            action="{{ route('account.update') }}"
            id="account-settings-form"
            class="my-8"
            x-data="{
                avatarMediaName: @js((string) old('avatar_media_name', $user->avatar_media_name ?? '')),
                avatarPreviewUrl: @js($avatarPreviewUrl),
                avatarMediaLabel: '',
                avatarMediaSize: '',
                avatarZoom: {{ max(100, min(250, $avatarZoom)) }},
                avatarOffsetX: {{ max(-50, min(50, $avatarOffsetX)) }},
                avatarOffsetY: {{ max(-50, min(50, $avatarOffsetY)) }},
                avatarDragging: false,
                avatarDragStartX: 0,
                avatarDragStartY: 0,
                avatarDragOriginX: 0,
                avatarDragOriginY: 0,
                avatarDragFrameWidth: 1,
                avatarDragFrameHeight: 1,
                init() {
                    const loadAvatarDetails = (mediaName) => {
                        if (!mediaName || !window.SM?.mediaDetails) {
                            if (!mediaName) {
                                this.avatarPreviewUrl = '';
                                this.avatarMediaLabel = '';
                                this.avatarMediaSize = '';
                            }
                            return;
                        }

                        window.SM.mediaDetails(mediaName, (details) => {
                            this.avatarPreviewUrl = String(details?.thumbnail || '');
                            this.avatarMediaLabel = String(details?.name || '');
                            this.avatarMediaSize = window.SM?.bytesToString ? window.SM.bytesToString(details?.size || 0) : '';
                        });
                    };

                    loadAvatarDetails(this.avatarMediaName);
                    window.addEventListener('pointermove', (event) => this.handleAvatarDrag(event));
                    window.addEventListener('pointerup', () => this.endAvatarDrag());
                    window.addEventListener('pointercancel', () => this.endAvatarDrag());
                },
                avatarStyle() {
                    return `transform: translate(${this.avatarOffsetX}%, ${this.avatarOffsetY}%) scale(${(this.avatarZoom / 100).toFixed(2)}); transform-origin: center center;`;
                },
                openAvatarPicker() {
                    window.SMMediaPicker.open(this.avatarMediaName || '', {
                        require_mime_type: 'image/*',
                        allow_multiple: false,
                        allow_uploads: true,
                        allow_camera: true,
                    }, (value) => this.setAvatarMedia(value));
                },
                setAvatarMedia(value) {
                    this.avatarMediaName = String(value || '');

                    if (this.avatarMediaName === '') {
                        this.avatarPreviewUrl = '';
                        this.avatarMediaLabel = '';
                        this.avatarMediaSize = '';
                        this.avatarZoom = 100;
                        this.avatarOffsetX = 0;
                        this.avatarOffsetY = 0;
                        return;
                    }

                    window.SM.mediaDetails(this.avatarMediaName, (details) => {
                        this.avatarPreviewUrl = String(details?.thumbnail || '');
                        this.avatarMediaLabel = String(details?.name || '');
                        this.avatarMediaSize = window.SM?.bytesToString ? window.SM.bytesToString(details?.size || 0) : '';
                        this.avatarZoom = 100;
                        this.avatarOffsetX = 0;
                        this.avatarOffsetY = 0;
                    });
                },
                startAvatarDrag(event) {
                    if (!this.avatarPreviewUrl) {
                        return;
                    }

                    const point = event.touches?.[0] || event;
                    const rect = event.currentTarget.getBoundingClientRect();
                    this.avatarDragging = true;
                    this.avatarDragStartX = point.clientX;
                    this.avatarDragStartY = point.clientY;
                    this.avatarDragOriginX = this.avatarOffsetX;
                    this.avatarDragOriginY = this.avatarOffsetY;
                    this.avatarDragFrameWidth = rect.width || 1;
                    this.avatarDragFrameHeight = rect.height || 1;
                },
                handleAvatarDrag(event) {
                    if (!this.avatarDragging) {
                        return;
                    }

                    const point = event.touches?.[0] || event;
                    const zoomScale = this.avatarZoom / 100;
                    const deltaX = ((point.clientX - this.avatarDragStartX) / this.avatarDragFrameWidth) * 100 / zoomScale;
                    const deltaY = ((point.clientY - this.avatarDragStartY) / this.avatarDragFrameHeight) * 100 / zoomScale;
                    this.avatarOffsetX = Math.max(-50, Math.min(50, Math.round(this.avatarDragOriginX + deltaX)));
                    this.avatarOffsetY = Math.max(-50, Math.min(50, Math.round(this.avatarDragOriginY + deltaY)));
                },
                endAvatarDrag() {
                    this.avatarDragging = false;
                },
            }"
        >
            @csrf
            <input type="hidden" name="remembered_device_hint" id="remembered_device_hint" value="" />
            <input type="hidden" name="remembered_device_touch_points" id="remembered_device_touch_points" value="" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Profile</h2>
                                <p class="mt-1 text-sm text-gray-600">Child accounts can change their username and avatar only.</p>
                            </div>
                            <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                                Forum-only account
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <x-ui.input label="Username" name="username" value="{{ old('username', $user->username) }}" info="Usernames are unique across all accounts." />
                            <div class="rounded-2xl bg-gray-50 px-4 py-4 text-sm text-gray-600">
                                <div class="font-semibold text-gray-900">Managed by</div>
                                <div class="mt-1">{{ $user->parent?->forumDisplayName() ?: 'Parent account' }}</div>
                                <div class="mt-3 text-xs text-gray-500">Purchases, tickets, invoices, and address details are disabled for child accounts.</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Remembered Devices</h2>
                                <p class="mt-1 text-sm text-gray-600">Review devices that can stay signed in and remove any you no longer trust.</p>
                            </div>
                            <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $rememberedDevices->count() }} saved</div>
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

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-1">
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="text-lg font-semibold text-gray-900">Account Overview</h2>
                        <div class="mt-5 space-y-4">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Username</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $user->username ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Authentication</div>
                                <div class="mt-1 text-sm text-gray-900">Username + password</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Parent account</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $user->parent?->forumDisplayName() ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Remembered devices</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $rememberedDevices->count() }}</div>
                            </div>
                        </div>
                    </section>

                    @include('account.partials.avatar-card')
                </div>
            </div>
        </form>

        <div class="mb-8 space-y-6">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Password Login</h2>
                        <p class="mt-1 text-sm text-gray-600">Update the password used to sign in to this child account.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 grid gap-4 md:grid-cols-2">
                    @csrf
                    <x-ui.input type="password" name="password" label="New password" value="" />
                    <x-ui.input type="password" name="password_confirmation" label="Confirm password" value="" />
                    <div class="md:col-span-2 flex justify-end">
                        <x-ui.button type="submit">Update password</x-ui.button>
                    </div>
                </form>
            </section>

            @include('account.partials.two-factor-card', ['supportsEmailVerification' => false])

            <div class="rounded-3xl border border-primary-color/15 bg-primary-color-light/30 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Ready to save?</div>
                        <p class="text-sm text-gray-600">Your username, avatar, and remembered-device preferences are saved together.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <x-ui.button type="submit" form="account-settings-form">Save changes</x-ui.button>
                    </div>
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

    const initializeDeviceHints = () => {
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
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initRememberedDeviceActions();
            initializeDeviceHints();
        }, { once: true });
    } else {
        initRememberedDeviceActions();
        initializeDeviceHints();
    }
</script>
