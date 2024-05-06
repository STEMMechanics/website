<x-layout :bodyClass="'image-background'">
    <x-dialog>
        <x-slot:title>Check your inbox</x-slot:title>
        <x-slot:header><p class="text-center">Check your email for the registration link we just sent. Click the link to finish setting up your account.</p></x-slot:header>
        <x-slot:footer class="mt-8"><x-ui.button href="{{ route('index') }}">Home</x-ui.button></x-slot:footer>
    </x-dialog>
</x-layout>
