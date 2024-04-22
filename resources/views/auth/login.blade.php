<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('login.store') }}">
        @if(session('status') == 'not-found')
            <x-slot:title>Sorry, we didn't recognize that email</x-slot:title>
            <x-slot:header>
                <p>Would you like to sign in with a different email?</p>
            </x-slot:header>
        @else
        <x-slot:title>Sign in with email</x-slot:title>
        <x-slot:header>
            <p>Enter the email address associated with your account, and we'll send a magic link to your inbox.</p>
        </x-slot:header>
        @endif
        <x-ui.input type="email" name="email" label="Email" floating autofocus />
        <x-slot:footer>
            <div class="text-xs">Don't have an account? <a href="{{ route('register') }}" class="link">Register</a></div>
            <x-ui.button type="submit">Continue</x-ui.button>
        </x-slot:footer>
    </x-dialog>
</x-layout>
