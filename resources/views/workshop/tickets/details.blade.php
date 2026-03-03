<x-layout>
    <x-mast>Ticket Details</x-mast>

    <x-container class="max-w-4xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-3">Add Ticket Holder Details</h2>
                <p class="text-sm text-gray-600 mb-2">
                    Congrats, you're in. Your ticket{{ $tickets->count() === 1 ? ' is' : 's are' }} reserved for <strong>{{ $workshop->title }}</strong>.
                </p>
                <p class="text-sm text-gray-600 mb-4">Add details for each ticket holder below.</p>

                @if((string) ($session['payment_method'] ?? '') === 'bank_transfer' && is_array($bankTransferDetails ?? null))
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <div class="font-semibold text-base mb-2">Bank Transfer Details</div>
                        <div class="mb-3">Use the invoice number below as the payment reference so the transfer can be matched to your booking.</div>
                        <div class="grid gap-2 sm:grid-cols-[10rem_1fr]">
                            <div class="font-semibold">Account Name</div>
                            <div>{{ (string) ($bankTransferDetails['account_name'] ?? '-') }}</div>
                            <div class="font-semibold">BSB</div>
                            <div>{{ (string) ($bankTransferDetails['bsb'] ?? '-') }}</div>
                            <div class="font-semibold">Account Number</div>
                            <div>{{ (string) ($bankTransferDetails['account_number'] ?? '-') }}</div>
                            <div class="font-semibold">Reference</div>
                            <div class="font-mono font-semibold tracking-wide">{{ (string) ($bankTransferDetails['reference'] ?? '-') }}</div>
                        </div>
                    </div>
                @endif

                <form id="ticket-details-form" method="POST" action="{{ route('workshop.ticket.flow.details.save', $workshop) }}">
                    @csrf
                    @foreach($tickets as $index => $ticket)
                    <div class="border border-gray-400 rounded-lg p-4 mb-3">
                        <div class="font-semibold mb-2">Ticket {{ $index + 1 }} - {{ $ticket->reference_code }}</div>
                        <input type="hidden" name="tickets[{{ $index }}][id]" value="{{ $ticket->id }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-ui.input name="tickets[{{ $index }}][firstname]" label="First Name" value="{{ old('tickets.'.$index.'.firstname', $ticket->firstname) }}" required />
                            <x-ui.input name="tickets[{{ $index }}][surname]" label="Surname" value="{{ old('tickets.'.$index.'.surname', $ticket->surname) }}" required />
                            <x-ui.input type="email" name="tickets[{{ $index }}][email]" label="Email" value="{{ old('tickets.'.$index.'.email', $ticket->email) }}" required />
                            <x-ui.input name="tickets[{{ $index }}][phone]" label="Phone" value="{{ old('tickets.'.$index.'.phone', $ticket->phone) }}" required />
                        </div>
                    </div>
                    @endforeach

                    <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:justify-between">
                        <x-ui.button type="submit">Save Ticket Details</x-ui.button>
                    </div>
                </form>
            </div>
            <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>

<script>
    (() => {
        const keepAliveUrl = @js(route('workshop.ticket.flow.details.keepalive', $workshop));
        const form = document.getElementById('ticket-details-form');
        let stopped = false;
        let failureCount = 0;
        let isSubmitting = false;

        const disableFormInputs = () => {
            if (!form) {
                return;
            }
            form.querySelectorAll('input:not([type="hidden"]), select, textarea, button').forEach((element) => {
                element.disabled = true;
            });
        };

        const notifyExpired = () => {
            if (stopped) {
                return;
            }
            stopped = true;
            disableFormInputs();
            if (window.SM && typeof window.SM.notice === 'function') {
                window.SM.notice(
                    'Session expired',
                    'Your checkout session expired while this page was open. Reload this page or restart checkout before saving ticket details.',
                    'warning'
                );
            }
        };

        const ping = async () => {
            if (stopped) {
                return;
            }

            try {
                const response = await fetch(keepAliveUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    notifyExpired();
                    return;
                }

                const payload = await response.json().catch(() => null);
                if (!payload || payload.ok !== true) {
                    notifyExpired();
                    return;
                }

                failureCount = 0;
            } catch (error) {
                failureCount += 1;
                if (failureCount >= 2) {
                    notifyExpired();
                }
            }
        };

        if (form) {
            form.addEventListener('submit', (event) => {
                if (stopped || isSubmitting) {
                    event.preventDefault();
                    return;
                }
                isSubmitting = true;
                if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                    window.SM.setFormProcessing(form, true, { submitLabel: 'Saving...' });
                } else {
                    disableFormInputs();
                }
            });
        }

        ping();
        const intervalId = setInterval(ping, 60000);
        window.addEventListener('beforeunload', () => clearInterval(intervalId));
    })();
</script>
