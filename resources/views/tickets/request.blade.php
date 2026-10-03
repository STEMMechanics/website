<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('tickets.send') }}" id="tickets-request-form">
        <x-slot:title>{{ session('ticket_link_requested') ? 'Check your inbox' : 'Retrieve My Tickets' }}</x-slot:title>
        <x-slot:header>
            @if(session('ticket_link_requested'))
                <div class="w-full min-w-0 space-y-4">
                    <p>We’ve sent an email to <strong>{{ session('ticket_link_email') }}</strong>.</p>
                    <div class="flex items-start gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm leading-5 text-gray-600 ring-1 ring-inset ring-gray-200">
                        <i class="fa-regular fa-clock mt-1 shrink-0 text-gray-500" aria-hidden="true"></i>
                        <p>It should arrive within 5 minutes. Check your junk or spam folder if you don’t see it.</p>
                    </div>
                </div>
            @else
                <p>Enter your email address and we’ll send you a secure link to view your tickets.</p>
            @endif
        </x-slot:header>

        <x-slot:footer class="mt-6 sm:flex-row sm:justify-end">
            @if(session('ticket_link_requested'))
                <div class="ml-auto self-end"><x-ui.button href="{{ route('tickets.request') }}" color="outline">Back</x-ui.button></div>
            @else
                <div class="ml-auto self-end"><x-ui.button type="submit">Send link</x-ui.button></div>
            @endif
        </x-slot:footer>
        @unless(session('ticket_link_requested'))
            <x-altcha-proof submit-label="Sending..." />
            <x-ui.input type="email" label="Email" name="email" :value="old('email')" autocomplete="email" required autofocus />
        @endunless
    </x-dialog>

    @unless(session('ticket_link_requested'))
    @pushOnce('scripts')
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
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
    @endunless
</x-layout>
