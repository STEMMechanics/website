<x-layout :bodyClass="'image-background'">
    <div class="flex items-center justify-center grow py-24">
        <div id="password-panel" class="w-full mx-2 max-w-lg p-8 pb-6 bg-white rounded-md shadow-deep">
            <h2 class="text-2xl font-bold mb-4 text-center">Password Required</h2>
            <div class="flex justify-center gap-4 mb-4">
                A password is required to access this file.
            </div>
            <form id="media-password-form" method="POST" action="{{ route('media.download.unlock', $media) }}">
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
        <div id="requesting-panel" class="hidden w-full mx-2 max-w-lg p-8 pb-6 bg-white rounded-md shadow-deep">
            <h2 class="text-2xl font-bold mb-4 text-center">Requesting file</h2>
            <div class="flex justify-center mb-8">
                The file has been requested from the server...
            </div>
            <div class="flex justify-center">
                <img src="{{ asset('loading.gif') }}" class="h-32 w-32" alt="Please wait" />
            </div>
        </div>
    </div>
</x-layout>

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('media-password-form');
            const panel = document.getElementById('password-panel');
            const requesting = document.getElementById('requesting-panel');

            if (!form || !panel || !requesting) {
                return;
            }

            form.addEventListener('submit', function () {
                panel.classList.add('hidden');
                requesting.classList.remove('hidden');
            });
        })();
    </script>
@endpush
