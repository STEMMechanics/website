@php
    $rememberedLoginValue = (string) ($rememberedLogin ?? '');
    $rememberEmailOld = old('remember_email');
    $rememberEmailInitial = $rememberEmailOld !== null
        ? in_array((string) $rememberEmailOld, ['1', 'on', 'true'], true)
        : ($rememberedLoginValue !== '');
@endphp

<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('login.store') }}" id="login-identifier-form">
        <x-altcha-proof />
        @if(session('status') == 'not-found')
            <x-slot:title>Sorry, we didn't recognize that login</x-slot:title>
            <x-slot:header>
                <p>Would you like to sign in with a different email or username?</p>
            </x-slot:header>
        @else
        <x-slot:title>Sign in</x-slot:title>
        <x-slot:header>
            <p>Enter the email address or username associated with your account</p>
        </x-slot:header>
        @endif
        <x-ui.input
            type="text"
            name="login"
            id="login_identifier"
            label="Email or Username"
            value="{{ old('login', old('email', $rememberedLoginValue)) }}"
            floating
            autofocus
            data-remembered-login="{{ $rememberedLoginValue }}"
        />
        <x-ui.input
            type="password"
            name="password"
            id="login_password"
            label="Password (optional)"
            value=""
            floating
            info="Leave blank to receive an email link instead. Child accounts require a password."
        />
        <input type="hidden" name="remember_email" value="0" />
        <x-ui.checkbox
            id="remember_email"
            name="remember_email"
            value="1"
            label="Remember login on this device"
            checked="{{ $rememberEmailInitial }}"
            small
        />
        <x-slot:footer>
            <div class="text-xs">Don't have an account? <a href="{{ route('register') }}" class="link">Register</a></div>
            <x-ui.button type="submit">Continue</x-ui.button>
        </x-slot:footer>
    </x-dialog>

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const loginForm = document.getElementById('login-identifier-form');
                const emailInput = document.getElementById('login_identifier');
                const rememberCheckbox = document.getElementById('remember_email');
                if (loginForm && window.SM && typeof window.SM.bindFormProcessingOnSubmit === 'function') {
                    window.SM.bindFormProcessingOnSubmit(loginForm, {
                        submitLabel: 'Logging in...',
                    });
                }

                if (!emailInput || !rememberCheckbox) {
                    return;
                }

                const remembered = String(emailInput.dataset.rememberedLogin || '').trim().toLowerCase();
                if (remembered === '') {
                    return;
                }

                emailInput.addEventListener('input', () => {
                    const current = String(emailInput.value || '').trim().toLowerCase();
                    if (current !== remembered) {
                        rememberCheckbox.checked = false;
                    }
                });
            });
        </script>
    @endPushOnce
</x-layout>
