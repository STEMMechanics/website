@php
    $workshopStartsAt = $workshop->effectiveStartsAt() ?? $workshop->starts_at;
    $workshopEndsAt = $workshop->effectiveEndsAt() ?? $workshop->ends_at;
@endphp
<div class="mt-1 text-sm font-normal text-white/90">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
        <span class="inline-flex items-center gap-1.5">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span><span class="font-semibold">Starts:</span> {{ $workshopStartsAt?->format('D j M Y, g:i a') ?? 'Date not set' }}</span>
        </span>
        @if($workshopEndsAt)
            <span class="inline-flex items-center gap-1.5">
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                <span><span class="font-semibold">Ends:</span> {{ $workshopEndsAt->format('D j M Y, g:i a') }}</span>
            </span>
        @endif
    </div>
    <div class="mt-1 inline-flex items-center gap-1.5">
        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        <span><span class="font-semibold">Location:</span> {{ $workshop->getLocationDisplay() }}</span>
    </div>
</div>
