<x-layout :bodyClass="'image-background'">
    <x-dialog formaction="{{ route('sponsor.manage.send') }}" id="sponsorship-manage-request-form">
        <x-slot:title>{{ session('manage_link_requested') ? 'Check your inbox' : 'Manage My Sponsorship' }}</x-slot:title>
        <x-slot:header>
            @if(session('manage_link_requested'))
                <div class="w-full min-w-0 space-y-4">
                    <p class="text-center">If a sponsorship is linked to <strong>{{ session('manage_link_email') }}</strong>, we’ve sent a secure link there.</p>
                    <div class="flex items-start gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm leading-5 text-gray-600 ring-1 ring-inset ring-gray-200">
                        <i class="fa-regular fa-clock mt-1 shrink-0 text-gray-500" aria-hidden="true"></i>
                        <p>It should arrive within 5 minutes. Check your junk or spam folder if you don’t see it.</p>
                    </div>
                </div>
            @else
                <p class="text-center">Enter the email address used at checkout and we’ll send a link to view your sponsorships.</p>
            @endif
        </x-slot:header>

        @unless(session('manage_link_requested'))
            @if(session('message'))<p class="mb-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800">{{ session('message') }}</p>@endif
            <x-ui.input type="email" name="email" label="Email" autocomplete="email" :value="old('email')" required autofocus />
        @endunless

        <x-slot:footer class="mt-6 sm:flex-row sm:justify-end">
            @if(session('manage_link_requested'))
                <div class="ml-auto self-end"><x-ui.button href="{{ route('sponsor.manage.request') }}" color="outline">Back</x-ui.button></div>
            @else
                <div class="ml-auto self-end"><x-ui.button type="submit">Send link</x-ui.button></div>
            @endif
        </x-slot:footer>
    </x-dialog>

    @unless(session('manage_link_requested'))
        @pushOnce('scripts')
            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
                (() => {
                    const form = document.getElementById('sponsorship-manage-request-form');
                    if (!(form instanceof HTMLFormElement) || !window.SM || typeof window.SM.bindFormProcessingOnSubmit !== 'function') {
                        return;
                    }

                    window.SM.bindFormProcessingOnSubmit(form, { submitLabel: 'Sending...' });
                })();
            </script>
        @endPushOnce
    @endunless
</x-layout>
