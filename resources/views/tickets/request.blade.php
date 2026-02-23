<x-layout>
    <x-mast>My Tickets</x-mast>

    <x-container class="mt-4 max-w-xl mx-auto">
        @if(session('inline_message'))
            <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ session('inline_message_type') === 'success' ? 'border-green-300 bg-green-50 text-green-800' : 'border-gray-300 bg-gray-50 text-gray-800' }}">
                {{ session('inline_message') }}
            </div>
        @endif

        <p class="mb-4 text-sm text-gray-600">
            Enter your email address and we will send you a secure link to view your tickets.
        </p>

        <form id="tickets-request-form" method="POST" action="{{ route('tickets.send') }}">
            @csrf
            <x-altcha-proof />
            <x-ui.input type="email" label="Email" name="email" value="{{ old('email') }}" />
            <div class="flex justify-end mt-6">
                <x-ui.button type="submit">Send Link</x-ui.button>
            </div>
        </form>
    </x-container>

    @pushOnce('scripts')
    <script>
        (() => {
            if (!window.SM || typeof window.SM.setFormProcessing !== 'function') {
                return;
            }

            const form = document.getElementById('tickets-request-form');
            if (!(form instanceof HTMLFormElement) || form.dataset.smTicketsRequestBound === '1') {
                return;
            }

            // ALTCHA-enabled forms manage processing state in x-altcha-proof.
            if (form.querySelector('altcha-widget')) {
                return;
            }

            form.dataset.smTicketsRequestBound = '1';
            form.addEventListener('submit', () => {
                window.SM.setFormProcessing(form, true, { submitLabel: 'Sending...' });
            });
        })();
    </script>
    @endPushOnce
</x-layout>
