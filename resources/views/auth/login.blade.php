@php
    $rememberedEmailValue = (string) ($rememberedEmail ?? '');
    $rememberEmailOld = old('remember_email');
    $rememberEmailInitial = $rememberEmailOld !== null
        ? in_array((string) $rememberEmailOld, ['1', 'on', 'true'], true)
        : ($rememberedEmailValue !== '');
@endphp

<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('login.store') }}">
        <x-altcha-proof />
        @if(session('status') == 'not-found')
            <x-slot:title>Sorry, we didn't recognize that email</x-slot:title>
            <x-slot:header>
                <p>Would you like to sign in with a different email?</p>
            </x-slot:header>
        @else
        <x-slot:title>Sign in with email</x-slot:title>
        <x-slot:header>
            <p>Enter the email address associated with your account</p>
        </x-slot:header>
        @endif
        <x-ui.input
            type="email"
            name="email"
            id="login_email"
            label="Email"
            value="{{ old('email', $rememberedEmailValue) }}"
            floating
            autofocus
            data-remembered-email="{{ $rememberedEmailValue }}"
        />
        <input type="hidden" name="remember_email" value="0" />
        <x-ui.checkbox
            id="remember_email"
            name="remember_email"
            value="1"
            label="Remember email on this device"
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
                const emailInput = document.getElementById('login_email');
                const rememberCheckbox = document.getElementById('remember_email');
                if (!emailInput || !rememberCheckbox) {
                    return;
                }

                const remembered = String(emailInput.dataset.rememberedEmail || '').trim().toLowerCase();
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
