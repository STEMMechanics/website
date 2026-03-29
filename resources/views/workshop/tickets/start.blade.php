<x-layout>
    <x-mast>{{ $workshop->title }}</x-mast>

    <x-container class="max-w-3xl mt-6 mx-auto">
        @php($checkoutUser = auth()->user())
        @php($isClassroomAccess = $workshop->usesClassroomRegistration())
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-3">{{ $isClassroomAccess ? 'Get Classroom Access' : 'Get Tickets' }}</h2>
                <p class="text-sm text-gray-600 mb-4">{{ $isClassroomAccess ? 'Complete this checkout to reserve your classroom access.' : 'Complete this checkout to reserve your tickets.' }}</p>
                @if($requiresPrivateCode ?? false)
                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-4">
                        This is a private workshop. Enter the access code to continue.
                    </p>
                @endif

                @include('workshop.tickets.partials.summary', [
                    'workshop' => $workshop,
                    'hideLocation' => (bool) ($requiresPrivateCode ?? false),
                    'rows' => [
                        ['label' => 'Price', 'value' => $ticketPriceAmount > 0 ? '$'.number_format($ticketPriceAmount, 2).' per '.($isClassroomAccess ? 'access' : 'ticket') : 'Free'],
                        ['label' => $isClassroomAccess ? 'Access Slots' : 'Places', 'value' => $availableTickets ?? 'Unlimited'],
                    ],
                ])

                @if($checkoutUser?->isChildAccount())
                    <div class="text-sm bg-amber-50 border border-amber-200 rounded p-3 mb-4">
                        <p>
                            You are logged in as a child account. The details from this account will not be used for this {{ $isClassroomAccess ? 'classroom access' : 'ticket' }} purchase.
                            <a href="{{ route('logout.show') }}" class="link">Log out</a>
                        </p>
                    </div>
                @elseif(auth()->guest())
                    <div class="text-sm bg-blue-50 border border-blue-200 rounded p-3 mb-4">
                        Already have an account?
                        <a href="{{ route('workshop.ticket.flow.login', $workshop) }}" class="link">Log in and continue</a>
                        or checkout as guest below.
                    </div>
                @endif

                <form id="ticket-start-form" method="POST" action="{{ route('workshop.ticket.flow.begin', $workshop) }}">
                    @csrf
                    <x-altcha-proof />

                    @if($requiresPrivateCode ?? false)
                        <x-ui.input name="private_code" label="Access Code" value="{{ old('private_code') }}" required />
                    @endif
                    <x-ui.input type="number" name="quantity" label="{{ $isClassroomAccess ? 'Number of Access Slots' : 'Number of Tickets' }}" min="1" max="{{ $availableTickets ?? 10 }}" value="{{ old('quantity', 1) }}" />
                    <x-ui.input name="firstname" label="Purchaser First Name" value="{{ old('firstname', $prefill['firstname']) }}" required />
                    <x-ui.input name="surname" label="Purchaser Surname" value="{{ old('surname', $prefill['surname']) }}" required />
                    <x-ui.input type="email" name="email" label="Purchaser Email" value="{{ old('email', $prefill['email']) }}" required />
                    <x-ui.input name="phone" label="Purchaser Phone" value="{{ old('phone', $prefill['phone']) }}" required />

                    <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:justify-between">
                        <x-ui.button color="outline" href="{{ route('workshop.show', $workshop) }}">Back</x-ui.button>
                        <x-ui.button type="submit">{{ $ticketPriceAmount > 0 ? 'Continue to Payment' : ($isClassroomAccess ? 'Reserve Access' : 'Reserve Tickets') }}</x-ui.button>
                    </div>
                </form>
            </div>
            <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>

    @pushOnce('scripts')
    <script>
        (() => {
            const form = document.getElementById('ticket-start-form');
            if (!(form instanceof HTMLFormElement) || !window.SM || typeof window.SM.setFormProcessing !== 'function') {
                return;
            }

            // ALTCHA-enabled forms manage processing state in x-altcha-proof.
            if (form.querySelector('altcha-widget')) {
                return;
            }

            if (form.dataset.smCheckoutProcessingBound === '1') {
                return;
            }

            form.dataset.smCheckoutProcessingBound = '1';
            form.addEventListener('submit', () => {
                window.SM.setFormProcessing(form, true, { submitLabel: 'Continuing...' });
            });
        })();
    </script>
    @endPushOnce
</x-layout>
