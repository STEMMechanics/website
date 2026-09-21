<x-layout title="Your workshop booking">
    <x-mast>{{ $workshop->title }}</x-mast>
    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="relative bg-white border border-gray-200 rounded-lg shadow-sm p-5 pt-20 md:pt-5 flex gap-6">
            @include('workshop.tickets.partials.hold-countdown', ['holdExpiresAt' => $session['expires_at']])
            <div class="flex-1 min-w-0" x-data="SM.workshopSuggestions(@js(['availability' => $availability, 'participantCount' => count($session['review_draft'] ?? $session['participants'] ?? []) ?: $session['participant_count'], 'bookingId' => $workshop->id, 'selected' => $session['workshop_ids'], 'url' => route('workshop.ticket.flow.cart.update', $workshop), 'csrf' => csrf_token()]))">
            <div class="mb-3 flex items-center gap-3">
                <x-ui.row-action label="Back" icon="fa-arrow-left" :href="route('workshop.ticket.flow.start', $workshop)" />
                <h2 class="text-2xl font-bold">More workshops</h2>
            </div>
            <p x-show="error" x-text="error" x-cloak class="mb-4 text-sm text-red-600" role="alert"></p>
            @foreach($errors->all() as $error)<p class="mb-3 text-sm text-red-600" role="alert">{{ $error }}</p>@endforeach
            @if($additionalWorkshops->isNotEmpty())
                <div>
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($additionalWorkshops as $additional)
                            <div class="flex flex-col rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="mb-3 flex gap-3">
                                    @if($additional->hero)<img src="{{ $additional->hero->url }}" alt="" class="h-16 w-16 shrink-0 rounded-lg object-cover" loading="lazy">@endif
                                    <div class="min-w-0"><h3 class="font-semibold"><a class="text-black hover:underline" href="{{ route('workshop.show', $additional) }}" target="_blank" rel="noopener">{{ $additional->title }}</a></h3><p class="mt-1 text-sm">{{ $additional->currentTicketPriceAmount() > 0 ? money($additional->currentTicketPriceAmount()).' per participant' : 'Free' }}</p></div>
                                </div>
                                <p class="text-sm text-gray-600">{{ $additional->getTicketTimeRangeLabel() }}</p>
                                <p class="text-sm text-gray-600">{{ $additional->getLocationDisplay(true) }}</p>
                                <p class="mb-3 text-sm text-gray-600">Ages: {{ $additional->ages }}</p>
                                <div class="mt-auto flex flex-wrap items-center gap-3 justify-end">
                                    <span class="text-sm font-semibold text-green-700" x-show="selected.includes(@js($additional->id))" x-cloak><i class="fa-solid fa-check" aria-hidden="true"></i> Added</span>
                                    <x-ui.button type="button" color="secondary" data-workshop-id="{{ $additional->id }}" x-on:click="change($el.dataset.workshopId)" x-bind:disabled="busy || (!selected.includes($el.dataset.workshopId) && (selected.length >= 10 || soldOut($el.dataset.workshopId)))" x-text="label($el.dataset.workshopId)">Add workshop</x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Up to 10 workshops per booking. Adding a workshop refreshes your reservation timer, up to 30 minutes from starting checkout.</p>
                </div>
            @endif
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-5">
                <form method="POST" action="{{ route('workshop.ticket.flow.cancel', $workshop) }}">@csrf<x-ui.button type="submit" color="secondary">Cancel booking</x-ui.button></form>
                <form method="POST" action="{{ route('workshop.ticket.flow.cart.update', $workshop) }}">@csrf<input type="hidden" name="action" value="continue"><x-ui.button type="submit" x-bind:disabled="busy">Review booking</x-ui.button></form>
            </div>
            </div>
            <div class="hidden md:block w-64 shrink-0 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
