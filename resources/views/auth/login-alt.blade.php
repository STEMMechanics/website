<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('login.store') }}">
        @captcha
        <x-slot:title>Sign in another way</x-slot:title>
        <x-slot:header>Select the method to sign in to your account</x-slot:header>
        <div class="flex flex-col gap-4 mb-4">
            <x-ui.button type="button" onclick="loginUsingEmail()">Email Link</x-ui.button>
            <x-ui.button type="button" onclick="loginUsingEmail()">Enter Backup Code</x-ui.button>
        </div>
        <x-slot:footer>
            <div class="text-xs">If you need support for accessing your account, please contact STEMMechanics support at <a href="mailto:hello@stemmechanics.com.au" class="link">hello@stemmechanics.com.au</a></div>
        </x-slot:footer>
    </x-dialog>
</x-layout>
