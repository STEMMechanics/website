<x-layout :bodyClass="'image-background'">
    <div class="flex items-center justify-center grow py-24">
        <div class="w-full mx-2 max-w-lg p-8 pb-6 bg-white rounded-md shadow-deep">
            <h2 class="text-2xl font-bold mb-4 text-center">Password Required</h2>
            <div class="flex justify-center gap-4 mb-4">
                A password is required to access this file.
            </div>
            <form method="POST" action="{{ route('media.download.unlock', $media) }}">
                @csrf
                <x-ui.input
                    type="password"
                    name="password"
                    label="Password"
                    floating
                    autofocus
                    error="{{ $error ?? '' }}"
                />
                <div class="flex flex-col items-center gap-4 justify-center">
                    <x-ui.button type="submit">Continue</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layout>
