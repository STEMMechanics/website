<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('security.mfa.verify') }}">
        <x-slot:title>Verify administrator access</x-slot:title>
        <x-slot:header>Enter a code from your authenticator app, or one of your unused backup codes.</x-slot:header>
        <x-ui.input name="code" label="Verification code" autocomplete="one-time-code" autofocus :error="$errors->first('code')" />
        <x-slot:footer><x-ui.button type="submit" class="self-end sm:ml-auto">Verify</x-ui.button></x-slot:footer>
    </x-dialog>
</x-layout>
