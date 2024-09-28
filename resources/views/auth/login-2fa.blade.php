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
        <x-dialog x-cloak x-show="show==='tfa'" formaction="{{ route('login.store') }}">
            <x-slot:title>
                <a class="link absolute left-0" href="{{ route('login') }}"><i class="fa-solid fa-angle-left"></i></a>
                Please enter 2FA code
            </x-slot:title>
            <x-slot:header>
                <p class="text-sm">Two-factor authentication (2FA) is enabled for your account. Please enter a code to log in.</p>
            </x-slot:header>
            <input type="hidden" name="email" value="{{ $email }}"/>
            <x-ui.input type="text" name="code" label="Code" floating autofocus error="{{ $errors->first('code') }}"/>
            <x-slot:footer>
                <div class="text-xs">
                    Having trouble? <a class="link" href="#" x-on:click.prevent="show='other'">Sign in another way</a>
                </div>
                <x-ui.button type="submit">Verify</x-ui.button>
            </x-slot:footer>
        </x-dialog>

        <x-dialog x-cloak x-show="show==='other'">
            @captcha
            <x-slot:title>
                <a class="link absolute left-0" href="#" x-on:click.prevent="show='tfa'"><i class="fa-solid fa-angle-left"></i></a>
                Sign in another way
            </x-slot:title>
            <x-slot:header>Select the method to sign in to your account</x-slot:header>
            <div class="flex flex-col gap-4 mb-4">
                <form method="post" action="{{ route('login.store') }}">
                    @csrf
                    @captcha
                    <input type="hidden" name="email" value="{{ $email }}" />
                    <input type="hidden" name="method" value="email" />
                    <x-ui.button type="submit" class="w-full">Email Link</x-ui.button>
                </form>
                <x-ui.button type="button" x-on:click.prevent="show='backup'">Enter Backup Code</x-ui.button>
            </div>
            <x-slot:footer>
                <div class="text-xs">If you need support for accessing your account, please contact STEMMechanics support at <a href="mailto:hello@stemmechanics.com.au" class="link">hello@stemmechanics.com.au</a></div>
            </x-slot:footer>
        </x-dialog>

        <x-dialog x-cloak x-show="show==='backup'" formaction="{{ route('login.store') }}">
            <x-slot:title>
                <a class="link absolute left-0" href="#" x-on:click.prevent="show='other'"><i class="fa-solid fa-angle-left"></i></a>
                Please enter a backup code
            </x-slot:title>
            <x-slot:header>
                <p class="text-sm">Enter one of your backup codes below to log in. Once a backup codes are a 1 time use only.</p>
            </x-slot:header>
            @captcha
            <input type="hidden" name="email" value="{{ $email }}"/>
            <x-ui.input type="text" name="backup_code" label="Backup Code" floating autofocus error="{{ $errors->first('backup_code') }}" />
            <x-slot:footer center>
                <x-ui.button class="self-end" type="submit">Verify</x-ui.button>
            </x-slot:footer>
        </x-dialog>

    </div>
</x-layout>
