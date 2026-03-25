@php
    if (! isset($login)) {
        $login = '';
        if (isset($user)) {
            $login = $user->username ?: $user->email;
        }
    }

    $allowEmailMethod = (bool) ($allowEmailMethod ?? false);
    $rememberEmailValue = (string) ($rememberEmailValue ?? '0');
@endphp
<x-layout :bodyClass="'image-background'">
    <div x-data="{show:'{{ $method ?? 'password' }}'}">
        <x-dialog x-cloak x-show="show==='password'" formaction="{{ route('login.store') }}" id="login-password-form">
            <x-slot:title>
                <a class="link absolute left-0" href="{{ route('login') }}"><i class="fa-solid fa-angle-left"></i></a>
                Enter your password
            </x-slot:title>
            <x-slot:header>
                <p>Enter the password for this account to continue</p>
            </x-slot:header>
            <x-altcha-proof />
            <input type="hidden" name="login" value="{{ $login }}" autocomplete="username" />
            <input type="hidden" name="remember_email" value="{{ $rememberEmailValue }}" />
            <x-ui.input
                type="password"
                name="password"
                id="login_password"
                label="Password"
                value=""
                floating
                autocomplete="current-password"
                info="Enter your password to continue signing in."
                autofocus
                error="{{ $errors->first('password') }}"
            />
            <x-slot:footer>
                <div class="text-xs">
                    @if($allowEmailMethod)
                        Having trouble? <a class="link" href="#" x-on:click.prevent="show='other'">Sign in another way</a>
                    @endif
                </div>
                <x-ui.button type="submit">Sign in</x-ui.button>
            </x-slot:footer>
        </x-dialog>

        @if($allowEmailMethod)
            <x-dialog x-cloak x-show="show==='other'">
                <x-slot:title>
                    <a class="link absolute left-0" href="#" x-on:click.prevent="show='password'"><i class="fa-solid fa-angle-left"></i></a>
                    Sign in another way
                </x-slot:title>
                <x-slot:header>Select the method to sign in to your account</x-slot:header>
                <div class="flex flex-col gap-4 mb-4">
                    <form method="post" action="{{ route('login.store') }}" id="login-password-email-form">
                        @csrf
                        <x-altcha-proof />
                        <input type="hidden" name="login" value="{{ $login }}" autocomplete="username" />
                        <input type="hidden" name="remember_email" value="{{ $rememberEmailValue }}" />
                        <input type="hidden" name="method" value="email" />
                        <x-ui.button type="submit" class="w-full">Email Link</x-ui.button>
                    </form>
                </div>
                <x-slot:footer>
                    <div class="text-xs">If you need support for accessing your account, please contact STEMMechanics support at <a href="mailto:hello@stemmechanics.com.au" class="link">hello@stemmechanics.com.au</a></div>
                </x-slot:footer>
            </x-dialog>
        @endif
    </div>

    @pushOnce('scripts')
    <script>
        const bindLoginPasswordFormProcessing = () => {
            if (!window.SM || typeof window.SM.setFormProcessing !== 'function') {
                return;
            }

            const bindForm = (formId, submitLabel) => {
                const form = document.getElementById(formId);
                if (!form) {
                    return;
                }

                if (typeof window.SM.bindSingleSubmit === 'function') {
                    window.SM.bindSingleSubmit(form);
                }

                if (form.dataset.smPasswordProcessingBound === '1') {
                    return;
                }

                form.dataset.smPasswordProcessingBound = '1';
                form.addEventListener('submit', () => {
                    window.SM.setFormProcessing(form, true, {
                        submitLabel
                    });
                });
            };

            bindForm('login-password-form', 'Signing in...');
            bindForm('login-password-email-form', 'Sending...');
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindLoginPasswordFormProcessing, {
                once: true
            });
        } else {
            bindLoginPasswordFormProcessing();
        }

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
    @endPushOnce
</x-layout>
