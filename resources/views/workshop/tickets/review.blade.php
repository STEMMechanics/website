<x-layout title="Review booking">
    <x-mast>{{ $workshop->title }}</x-mast>
    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="relative bg-white border border-gray-200 rounded-lg shadow-sm p-5 pt-20 md:pt-5 flex gap-6">
            @include('workshop.tickets.partials.hold-countdown', ['holdExpiresAt' => $session['expires_at']])
            <div class="flex-1 min-w-0">
                <div class="mb-4 flex items-center gap-3"><x-ui.row-action label="Back" icon="fa-arrow-left" :href="route('workshop.ticket.flow.cart', $workshop)" /><h2 class="text-2xl font-bold">Review booking</h2></div>
                @foreach($errors->all() as $error)<p class="mb-3 text-sm text-red-600" role="alert">{{ $error }}</p>@endforeach
                <form x-on:submit="submitReview($event)" method="POST" action="{{ route('workshop.ticket.flow.review.save', $workshop) }}" x-data="SM.workshopBookingReview(@js(['draftUrl' => route('workshop.ticket.flow.review.draft', $workshop), 'csrf' => csrf_token(), 'bookingId' => $workshop->id, 'participants' => old('participants', $participants), 'prices' => $pricing, 'surname' => $session['purchaser']['surname']]))">
                    @csrf
                    <p x-show="saveError" x-text="saveError" role="alert" class="mb-3 text-sm text-red-600"></p>
                    <h3 class="mb-3 text-lg font-semibold">Who’s coming?</h3>
                    <template x-for="(person, index) in participants" :key="index">
                        <div class="mb-3 rounded-lg border border-gray-200 p-3">
                            <div class="flex items-center justify-between gap-2"><h4 class="font-semibold" x-text="'Participant ' + (index + 1)"></h4><x-ui.row-action icon="fa-trash" label="Remove participant" x-show="participants.length > 1" x-on:click="participants.splice(index, 1)" /></div>
                            <label class="mb-3 block text-sm font-medium">First name<x-ui.input-control x-bind:name="`participants[${index}][firstname]`" x-model="person.firstname" autocomplete="off" required /></label>
                            <label class="mb-3 block text-sm font-medium">Surname<x-ui.input-control x-bind:name="`participants[${index}][surname]`" x-model="person.surname" autocomplete="off" required /></label>
                        </div>
                    </template>
                    <x-ui.button type="button" color="secondary" x-on:click="addParticipant()" x-bind:disabled="participants.length >= 10">Add another participant</x-ui.button>
                    <h3 class="mb-2 mt-6 text-lg font-semibold">Who’s attending each workshop?</h3>
                    <p class="mb-4 text-sm text-gray-600">Tick the participants for each workshop.</p>
                    @foreach($workshops as $item)
                        <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <h4 class="font-semibold">{{ $item->title }}</h4>
                            <p class="text-sm">{{ $item->currentTicketPriceAmount() > 0 ? money($item->currentTicketPriceAmount()).' per participant' : 'Free' }}</p>
                            <p class="mt-1 text-sm text-gray-600">Ages: {{ $item->ages }}</p>
                            <p class="text-sm text-gray-600">{{ $item->getTicketTimeRangeLabel() }}</p>
                            <p class="mb-3 text-sm text-gray-600">{{ $item->getLocationDisplay(true) }}</p>
                            @if($pricing[$item->id]['capacity'] !== null)
                                <p class="mb-3 text-sm font-medium text-amber-800" x-show="participants.length > @js($pricing[$item->id]['capacity'])" x-cloak>Only {{ $pricing[$item->id]['capacity'] }} {{ $pricing[$item->id]['capacity'] === 1 ? 'spot' : 'spots' }} available.</p>
                                <p class="mb-3 text-sm text-red-600" role="alert" x-show="overCapacity(@js($item->id))" x-cloak>Please reduce the selected participants to match the available places.</p>
                            @endif
                            <template x-for="(person, index) in participants" :key="index">
                                <label class="mb-2 flex cursor-pointer items-center gap-3">
                                    <x-ui.checkbox bare :value="$item->id" x-bind:name="`participants[${index}][workshops][]`" x-model="person.workshops" x-bind:disabled="selectionFull(person, $el.value)" :aria-label="'Attend '.$item->title" />
                                    <span x-text="person.firstname.trim() ? [person.firstname, person.surname].filter(Boolean).join(' ') : 'Participant ' + (index + 1)"></span>
                                </label>
                            </template>
                            <p class="mt-3 text-sm font-semibold" x-show="quantity(@js($item->id)) > 0" x-text="quantity(@js($item->id)) + ' ticket' + (quantity(@js($item->id)) === 1 ? '' : 's') + ' · ' + (amount(@js($item->id)) > 0 ? money(amount(@js($item->id))) : 'Free')"></p>
                            @if($item->id !== $workshop->id)<p class="mt-1 text-sm text-gray-600" x-show="quantity(@js($item->id)) === 0">Not included in your booking.</p>@endif
                        </div>
                    @endforeach
                    <div class="my-5 flex justify-between border-t border-gray-200 pt-4 font-bold"><span>Subtotal</span><span x-text="total > 0 ? money(total) : 'Free'"></span></div>
                    <p class="mb-4 text-sm text-gray-600">Booking contact: {{ $session['purchaser']['firstname'] }} {{ $session['purchaser']['surname'] }}<br>{{ $session['purchaser']['email'] }} · {{ $session['purchaser']['phone'] }}</p>
                    <div class="flex justify-end"><x-ui.button type="submit" x-bind:disabled="hasOverCapacitySelection" x-text="total > 0 ? 'Continue to payment' : 'Confirm booking'">Continue</x-ui.button></div>
                </form>
            </div>
            <div class="hidden md:block w-64 shrink-0 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
