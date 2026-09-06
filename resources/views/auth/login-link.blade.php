<x-layout :bodyClass="'image-background'">
    <x-dialog>
        <x-slot:title>
            <a class="link absolute left-0" href="{{ route('login') }}"><i class="fa-solid fa-angle-left"></i></a>
            Check your inbox
        </x-slot:title>
        <x-slot:header><p>If this account supports email sign-in, you will receive a link. Check your inbox and spam folder.</p></x-slot:header>
        <x-slot:footer center class="mt-8"><x-ui.button href="{{ route('index') }}">Home</x-ui.button></x-slot:footer>
    </x-dialog>
</x-layout>
