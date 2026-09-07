<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('security.mfa.verify') }}">
        <x-slot:title>Verify account</x-slot:title>
        <x-slot:header>
            <div class="min-w-0 flex-1">We need to verify your account. Please enter a code from your authenticator app, or one of your unused backup codes.</div>
        </x-slot:header>
        <x-ui.input name="code" label="Verification code" autocomplete="one-time-code" autofocus :error="$errors->first('code')" />
        <x-slot:footer>
            <a href="{{ route('logout.show') }}" class="link shrink-0">Log out</a>
            <x-ui.button type="submit" class="self-end sm:ml-auto">Verify</x-ui.button>
        </x-slot:footer>
    </x-dialog>
</x-layout>
