<x-layout title="Your workshop booking">
    <x-mast>Your workshop booking</x-mast>
    <x-container class="max-w-4xl mt-6 mx-auto">
        <div class="relative rounded-lg border border-gray-200 bg-white p-5 pt-20 shadow-sm sm:p-6 sm:pt-20">
            @include('workshop.tickets.partials.hold-countdown', ['holdExpiresAt' => $session['expires_at']])
            <div class="mb-3 flex items-center gap-3">
                <x-ui.row-action label="Back" icon="fa-arrow-left" :href="route('workshop.ticket.flow.start', $workshop)" />
                <h2 class="text-2xl font-bold">Review your workshops</h2>
            </div>
            <p class="mb-5 text-sm text-gray-600">{{ $session['participant_count'] }} {{ $session['participant_count'] === 1 ? 'participant' : 'participants' }} at each workshop. Enter their details once after checkout.</p>
            @foreach($errors->all() as $error)<p class="mb-3 text-sm text-red-600" role="alert">{{ $error }}</p>@endforeach
            <div class="divide-y divide-gray-200">
                @foreach($checkoutWorkshops as $selected)
                    <div class="flex items-start gap-3 py-4">
                        @if($selected->hero)<img src="{{ $selected->hero->url }}" alt="" class="h-16 w-16 shrink-0 rounded-lg object-cover" loading="lazy">@endif
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">{{ $selected->title }}</h3>
                            <p class="text-sm text-gray-600">{{ $selected->getTicketTimeRangeLabel() }}</p>
                            <p class="text-sm text-gray-600">{{ $selected->getLocationDisplay(true) }}</p>
                            @foreach(collect($ticketPricing['items'])->where('workshop_id', $selected->id) as $price)
                                <p class="mt-1 text-sm">{{ $price['count'] }} × {{ money($price['unit_price']) }}@if($price['is_early_bird']) · Early bird @endif <strong class="ml-2">{{ money($price['amount']) }}</strong></p>
                            @endforeach
                        </div>
                        @if($selected->id !== $workshop->id)
                            <form method="POST" action="{{ route('workshop.ticket.flow.cart.update', $workshop) }}">
                                @csrf
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="workshop_id" value="{{ $selected->id }}">
                                <x-ui.row-action type="submit" icon="fa-trash" :label="'Remove '.$selected->title" />
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="my-5 flex justify-between border-t border-gray-200 pt-4 text-lg font-bold"><span>Subtotal</span><span>{{ money($ticketPricing['subtotal_amount']) }}</span></div>
            @if($additionalWorkshops->isNotEmpty())
                <div class="mt-6 border-t border-gray-200 pt-5" x-data="{ search: '' }">
                    <h2 class="mb-2 text-xl font-bold">Other workshops you can join</h2>
                    <p class="mb-4 text-sm text-gray-600">Add sessions at any venue or online for the same participants. Check the times and allow for travel between venues.</p>
                    <x-ui.input type="search" label="Find a workshop" placeholder="Search workshops or locations…" x-model="search" autocomplete="off" />
                    <div class="grid max-h-[32rem] gap-4 overflow-y-auto sm:grid-cols-2">
                        @foreach($additionalWorkshops as $additional)
                            @php($available = app(\App\Services\WorkshopTicketService::class)->availableTickets($additional))
                            <div x-show="@js(mb_strtolower($additional->title.' '.$additional->getLocationDisplay(true))).includes(search.toLowerCase())" class="flex flex-col rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="mb-3 flex gap-3">
                                    @if($additional->hero)<img src="{{ $additional->hero->url }}" alt="" class="h-16 w-16 shrink-0 rounded-lg object-cover" loading="lazy">@endif
                                    <div class="min-w-0"><h3 class="font-semibold"><a class="text-black hover:underline" href="{{ route('workshop.show', $additional) }}" target="_blank" rel="noopener">{{ $additional->title }}</a></h3><p class="mt-1 text-sm">{{ money($additional->currentTicketPriceAmount()) }} per participant</p></div>
                                </div>
                                <p class="text-sm text-gray-600">{{ $additional->getTicketTimeRangeLabel() }}</p>
                                <p class="mb-3 text-sm text-gray-600">{{ $additional->getLocationDisplay(true) }}</p>
                                <form class="mt-auto" method="POST" action="{{ route('workshop.ticket.flow.cart.update', $workshop) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="workshop_id" value="{{ $additional->id }}">
                                    <x-ui.button type="submit" color="secondary" :disabled="($available !== null && $available < $session['participant_count']) || count($session['workshop_ids']) >= 10">{{ $available !== null && $available < $session['participant_count'] ? 'Not enough places' : 'Add workshop' }}</x-ui.button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Up to 10 workshops per booking. Adding a workshop refreshes your reservation timer, up to 30 minutes from starting checkout.</p>
                </div>
            @endif
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-5">
                <form method="POST" action="{{ route('workshop.ticket.flow.cancel', $workshop) }}">@csrf<x-ui.button type="submit" color="secondary">Cancel booking</x-ui.button></form>
                <form method="POST" action="{{ route('workshop.ticket.flow.cart.update', $workshop) }}">@csrf<input type="hidden" name="action" value="continue"><x-ui.button type="submit">{{ $ticketPricing['subtotal_amount'] > 0 ? 'Continue to payment' : 'Confirm booking' }}</x-ui.button></form>
            </div>
        </div>
    </x-container>
</x-layout>
