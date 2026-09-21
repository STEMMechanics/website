@foreach($workshopBookings as $booking)
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4" x-data="SM.workshopHoldCountdown(@js($booking['expires_at']), @js($booking['id']))" x-show="remainingSeconds > 0" x-cloak>
        <div class="mb-2 flex items-center gap-2 font-semibold"><i class="fa-solid fa-ticket text-primary-color" aria-hidden="true"></i> Workshop booking</div>
        <p class="text-sm font-semibold">{{ $booking['title'] }}</p>
        <p class="mt-1 text-sm text-gray-600">{{ $booking['count'] }} {{ $booking['count'] === 1 ? 'ticket' : 'tickets' }} {{ ($booking['selection_pending'] ?? false) ? 'selected' : 'reserved' }} · <span x-text="timeRemaining"></span> remaining</p>
        <x-ui.button :href="$booking['url']" class="mt-3">Continue booking</x-ui.button>
    </div>
@endforeach
