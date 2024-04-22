<x-layout :bodyClass="'image-background'">
    <x-dialog>
        <x-slot:title>Check your inbox</x-slot:title>
        <x-slot:header><p>Click the link we sent to your email address to sign in.</p></x-slot:header>
        <x-slot:footer center class="mt-8"><x-ui.button href="{{ route('index') }}">Home</x-ui.button></x-slot:footer>
    </x-dialog>
</x-layout>
