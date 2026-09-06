<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('logout') }}" id="logout-confirm-form">
        <x-slot:title>Log out</x-slot:title>
        <x-slot:header>Are you sure you want to log out of your account?</x-slot:header>
        <x-slot:footer>
            <div class="flex w-full flex-wrap items-center justify-between gap-4">
                <x-ui.button color="outline" href="{{ route('index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit" color="danger">Log out</x-ui.button>
            </div>
        </x-slot:footer>
    </x-dialog>

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const logoutForm = document.getElementById('logout-confirm-form');
                if (!(logoutForm instanceof HTMLFormElement) || !window.SM) {
                    return;
                }

                if (typeof window.SM.bindSingleSubmit === 'function') {
                    window.SM.bindSingleSubmit(logoutForm);
                }

                if (typeof window.SM.bindFormProcessingOnSubmit === 'function') {
                    window.SM.bindFormProcessingOnSubmit(logoutForm, {
                        submitLabel: 'Logging out...',
                    });
                }
            });

            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>
    @endPushOnce
</x-layout>
