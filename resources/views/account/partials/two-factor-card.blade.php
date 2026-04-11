@php
    $supportsEmailVerification = (bool) ($supportsEmailVerification ?? true);
    /** @var \App\Models\User|null $user */
    $user = auth()->user();
    $passwordConfigured = trim((string) ($user?->password ?? '')) !== '';
    $passwordLoginAvailable = (bool) ($user?->canUsePasswordLogin() ?? false);
@endphp

<section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Login Authentication</h2>
            <p class="mt-1 text-sm text-gray-600">Manage how sign-in verification works for your account.</p>
        </div>
        <x-ui.badge color="success" uppercase="true" size="xxs" x-cloak x-show="$store.tfa.enabled">
            <span x-text="'Authenticator enabled'"></span>
        </x-ui.badge>
        <x-ui.badge color="gray" uppercase="true" size="xxs" x-cloak x-show="!$store.tfa.enabled">
            <span x-text="'{{ $supportsEmailVerification ? 'Email only' : 'Authenticator not linked' }}'"></span>
        </x-ui.badge>
    </div>

    <div class="mt-6 grid gap-4 {{ $supportsEmailVerification ? 'xl:grid-cols-3' : 'xl:grid-cols-2' }}">
        @if($supportsEmailVerification)
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-gray-700 ring-1 ring-gray-200">
                        <i class="fa-solid fa-envelope text-xl"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-900">Email verification</h3>
                            <x-ui.badge color="success" size="xxs" uppercase="true">Enabled</x-ui.badge>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">Security links are always sent to your account email when required.</p>
                    </div>
                </div>
            </div>
        @endif

        <div
            class="rounded-2xl border border-gray-200 bg-gray-50 p-4"
            x-data="{ passwordDialogOpen: {{ $errors->has('password') || $errors->has('password_confirmation') ? 'true' : 'false' }} }"
        >
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-gray-700 ring-1 ring-gray-200">
                    <i class="fa-solid fa-key text-xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">Password login</h3>
                        <x-ui.badge :color="$passwordConfigured ? 'success' : 'gray'" size="xxs" uppercase="true">
                            {{ $passwordConfigured ? 'Enabled' : 'Not set' }}
                        </x-ui.badge>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ $passwordConfigured ? 'A password has been set for this account.' : 'No password has been set for this account.' }}
                    </p>
                    @if($passwordConfigured && ! $passwordLoginAvailable && $supportsEmailVerification)
                        <p class="mt-2 text-sm text-amber-700">Password sign-in becomes available after your email is verified.</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if($passwordConfigured)
                            <form method="POST" action="{{ route('account.password.update') }}" class="m-0">
                                @csrf
                                <x-ui.button type="submit" color="danger-outline" class="px-5!" name="clear_password" value="1">Clear</x-ui.button>
                            </form>
                        @endif
                        <x-ui.button type="button" class="px-5!" color="primary-outline" x-on:click="passwordDialogOpen = true">{{ $passwordConfigured ? 'Change' : 'Setup' }}</x-ui.button>
                    </div>
                </div>
            </div>

            <template x-teleport="body">
                <div
                    x-show="passwordDialogOpen"
                    x-cloak
                    class="fixed inset-0 z-280 flex items-end justify-center bg-slate-950/55 p-4 sm:items-center"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="account-password-dialog-title"
                    @click.self="passwordDialogOpen = false"
                    @keydown.escape.window="if (passwordDialogOpen) { passwordDialogOpen = false }"
                >
                    <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded bg-white shadow-2xl">
                        <div class="border-b border-gray-200 px-6 py-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 id="account-password-dialog-title" class="text-xl font-bold text-gray-900">{{ $passwordConfigured ? 'Change password' : 'Set password' }}</h2>
                                    <p class="mt-2 text-sm leading-6 text-gray-600">
                                        {{ $passwordConfigured ? 'Update the password used to sign in to this account.' : 'Set a password for this account so you can sign in without waiting for an email link.' }}
                                    </p>
                                </div>
                                <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="passwordDialogOpen = false" aria-label="Close password dialog">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </button>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('account.password.update') }}" class="overflow-y-auto px-6 py-5">
                            @csrf
                            <div class="grid gap-2">
                                <x-ui.input type="password" name="password" label="{{ $passwordConfigured ? 'New password' : 'Password' }}" value="" autocomplete="new-password" />
                                <x-ui.input type="password" name="password_confirmation" label="{{ $passwordConfigured ? 'Confirm new password' : 'Confirm password' }}" value="" autocomplete="new-password" />
                            </div>
                            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                                <x-ui.button type="button" color="secondary" x-on:click.prevent="passwordDialogOpen = false">Cancel</x-ui.button>
                                <x-ui.button type="submit">{{ $passwordConfigured ? 'Save Password' : 'Set Password' }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-gray-700 ring-1 ring-gray-200">
                    <i class="fa-solid fa-mobile-screen-button text-xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">Authenticator app</h3>
                        <span x-cloak x-show="!$store.tfa.enabled" class="rounded-full bg-red-100 px-2 py-0.5 text-xxs font-semibold text-red-700">Disabled</span>
                        <span x-cloak x-show="$store.tfa.enabled" class="rounded-full bg-green-100 px-2 py-0.5 text-xxs font-semibold text-green-800">Enabled</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">Use a time-based code from your authenticator app during sign-in.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="#" x-show="$store.tfa.enabled" x-data x-on:click.prevent="resetBackupCodes($event)" class="text-sm font-medium text-primary-color hover:text-primary-color-dark">Reset backup codes</a>
                        <x-ui.button x-show="$store.tfa.enabled" type="button" color="danger-outline" class="px-5!" x-data x-on:click.prevent="destroyTFA()">Disable</x-ui.button>
                        <x-ui.button x-show="!$store.tfa.enabled" id="tfa_button" type="button" color="primary-outline" class="px-5!" x-data x-on:click.prevent="setupTFA()">Setup</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5" x-cloak x-show="$store.tfa.show && !$store.tfa.loading">
        <div class="grid gap-6 lg:grid-cols-[12rem_minmax(0,1fr)] lg:items-center">
            <div class="flex items-center justify-center">
                <img src="{{ asset('loading.gif') }}" id="tfa_image_loader" alt="loading" width="100" height="100"/>
                <img src="" id="tfa_image" alt="QR Code" width="150" height="150" style="display:none" onload="handleTfaImageLoad()"/>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">Link your authenticator app</p>
                <p class="mt-2 text-sm text-gray-600">Scan the QR code or enter the key <span class="font-semibold text-gray-900" id="tfa_key"></span>, then confirm with a code from your app.</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-ui.input name="code" id="code" class="mb-0 sm:flex-1" label="Verification code" />
                    <x-ui.button class="sm:mb-4" type="button" color="primary-outline" x-on:click.prevent="linkTFA()">Link</x-ui.button>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-center" x-cloak x-show="$store.tfa.loading">
        <img src="{{ asset('loading.gif') }}" alt="loading" width="100" height="100"/>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-5" x-cloak x-show="$store.tfa.codes && !$store.tfa.loading">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
            <div>
                <p class="text-sm font-semibold text-gray-900">Backup codes</p>
                <ul class="mt-3 space-y-1 text-sm text-gray-600">
                    <li>Keep these backup codes somewhere safe.</li>
                    <li>Each code works once.</li>
                    <li>Generating new codes invalidates existing ones.</li>
                </ul>
            </div>
            <div class="rounded-2xl bg-white p-4 font-mono text-sm ring-1 ring-gray-200">
                <template x-for="(code, idx) in $store.tfa.codes" :key="idx">
                    <p class="py-1" x-text="code"></p>
                </template>
            </div>
        </div>
    </div>
</section>
