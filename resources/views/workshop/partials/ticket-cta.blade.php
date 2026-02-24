@if($canGetTickets)
    <x-ui.button href="{{ route('workshop.ticket.flow.start', $workshop) }}" class="mt-4 mb-2">Get Tickets</x-ui.button>
    @if($privateCodeRequired)
        <p class="text-xs text-gray-600 text-center mb-2">Private code required</p>
    @endif
    <p class="text-xs text-gray-600 text-center mb-2">
        @if($availableTickets === null)
            Tickets available now.
        @else
            {{ $availableTickets }} ticket{{ (int) $availableTickets === 1 ? '' : 's' }} remaining
        @endif
    </p>
@elseif($availableTickets !== null && (int) $availableTickets <= 0)
    <div class="sm-registration-full">This workshop is currently full.</div>
@elseif($workshop->closes_at && $workshop->closes_at->isPast())
    <div class="sm-registration-closed">Registration for this event has closed.</div>
@else
    <div class="sm-registration-message">Ticket checkout will be available soon.</div>
@endif
