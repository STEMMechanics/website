@if(!empty($holdExpiresAt))
    <div class="absolute top-0 right-0 z-10 m-4 rounded-lg border border-amber-300 bg-amber-50 p-2 text-sm shadow-md" x-data="SM.workshopHoldCountdown(@js($holdExpiresAt))" x-cloak>
        <div x-show="remainingSeconds > 0" class="flex w-34 items-center gap-2" aria-label="Ticket reservation time remaining">
            <span>Remaining:</span>
            <strong class="flex-1 text-center tabular-nums" x-text="timeRemaining"></strong>
        </div>
        <div x-show="remainingSeconds === 0">
            Hold expired. <a class="font-semibold underline" href="{{ route('workshop.ticket.flow.start', $workshop) }}">Restart checkout</a>
        </div>
    </div>
@endif
