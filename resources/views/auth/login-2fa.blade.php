@php
if(!isset($email)) {
$email = '';
if(isset($user)) {
$email = $user->email;
}
}
@endphp
<x-layout :bodyClass="'image-background'">
    <div x-data="{show:'{{ $method ?? 'tfa' }}'}">
        <x-dialog x-cloak x-show="show==='tfa'" formaction="{{ route('login.store') }}" id="login-2fa-code-form">
            <x-slot:title>
                <a class="link absolute left-0" href="{{ route('login') }}"><i class="fa-solid fa-angle-left"></i></a>
                Please enter 2FA code
            </x-slot:title>
            <x-slot:header>
                <p class="text-sm">Two-factor authentication (2FA) is enabled for your account. Please enter a code to log in.</p>
            </x-slot:header>
            <input type="hidden" name="email" value="{{ $email }}" autocomplete="username" />
            <x-altcha-proof />
            <x-ui.input
                type="tel"
                name="totp"
                id="totp"
                label="Code"
                floating
                autofocus
                autocomplete="one-time-code"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autocapitalize="off"
                spellcheck="false"
                error="{{ $errors->first('totp') }}"
            />
            <x-slot:footer>
                <div class="text-xs">
                    Having trouble? <a class="link" href="#" x-on:click.prevent="show='other'">Sign in another way</a>
                </div>
                <x-ui.button type="submit">Verify</x-ui.button>
            </x-slot:footer>
        </x-dialog>

        <x-dialog x-cloak x-show="show==='other'">
            <x-slot:title>
                <a class="link absolute left-0" href="#" x-on:click.prevent="show='tfa'"><i class="fa-solid fa-angle-left"></i></a>
                Sign in another way
            </x-slot:title>
            <x-slot:header>Select the method to sign in to your account</x-slot:header>
            <div class="flex flex-col gap-4 mb-4">
                <form method="post" action="{{ route('login.store') }}" id="login-2fa-email-form">
                    @csrf
                    <x-altcha-proof />
                    <input type="hidden" name="email" value="{{ $email }}" autocomplete="username" />
                    <input type="hidden" name="method" value="email" />
                    <x-ui.button type="submit" class="w-full">Email Link</x-ui.button>
                </form>
                <x-ui.button type="button" x-on:click.prevent="show='backup'">Enter Backup Code</x-ui.button>
            </div>
            <x-slot:footer>
                <div class="text-xs">If you need support for accessing your account, please contact STEMMechanics support at <a href="mailto:hello@stemmechanics.com.au" class="link">hello@stemmechanics.com.au</a></div>
            </x-slot:footer>
        </x-dialog>

        <x-dialog x-cloak x-show="show==='backup'" formaction="{{ route('login.store') }}" id="login-2fa-backup-form">
            <x-slot:title>
                <a class="link absolute left-0" href="#" x-on:click.prevent="show='other'"><i class="fa-solid fa-angle-left"></i></a>
                Please enter a backup code
            </x-slot:title>
            <x-slot:header>
                <p class="text-sm">Enter one of your backup codes below to log in. Once a backup codes are a 1 time use only.</p>
            </x-slot:header>
            <x-altcha-proof />
            <input type="hidden" name="email" value="{{ $email }}" autocomplete="username" />
            <x-ui.input
                type="text"
                name="backup_code"
                label="Backup Code"
                floating
                autofocus
                autocomplete="one-time-code"
                autocapitalize="off"
                spellcheck="false"
                error="{{ $errors->first('backup_code') }}"
            />
            <x-slot:footer center>
                <x-ui.button class="self-end" type="submit">Verify</x-ui.button>
            </x-slot:footer>
        </x-dialog>

    </div>

    @pushOnce('scripts')
    <script>
        const bindLogin2FaFormProcessing = () => {
            if (!window.SM || typeof window.SM.setFormProcessing !== 'function') {
                return;
            }

            const bindForm = (formId, submitLabel) => {
                const form = document.getElementById(formId);

                form.dataset.sm2faProcessingBound = '1';
                form.addEventListener('submit', () => {
                    console.log('Setting form processing state for', formId);
                    window.SM.setFormProcessing(form, true, {
                        submitLabel
                    });
                });
            };

            bindForm('login-2fa-code-form', 'Verifying...');
            bindForm('login-2fa-email-form', 'Sending...');
            bindForm('login-2fa-backup-form', 'Verifying...');
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindLogin2FaFormProcessing, {
                once: true
            });
        } else {
            bindLogin2FaFormProcessing();
        }
    </script>
    @endPushOnce
</x-layout>
