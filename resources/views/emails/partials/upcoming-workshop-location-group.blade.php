@php
    $accent = $accent ?? '#2563eb';
    $groups = collect($workshops ?? [])->groupBy(fn ($workshop) => $workshop->getLocationName());
@endphp

@foreach($groups as $locationName => $locationWorkshops)
<div style="margin:18px 0 10px 0; padding:10px 14px; border-radius:14px; background:#f8fafc; border-left:4px solid {{ $accent }};">
<div style="font-size:12px; font-weight:800; letter-spacing:0.12em; text-transform:uppercase; color:#334155;">{{ $locationName }}</div>
<div style="font-size:12px; color:#64748b; margin-top:3px;">{{ $locationWorkshops->count() }} upcoming {{ \Illuminate\Support\Str::plural('workshop', $locationWorkshops->count()) }}</div>
</div>

@foreach($locationWorkshops as $workshop)
@include('emails.partials.upcoming-workshop-card', [
    'workshop' => $workshop,
    'accent' => $accent,
    'badgeText' => $locationName,
    'showSummary' => false,
    'showScheduleLines' => false,
    'showImage' => false,
    'compact' => true,
])
@endforeach
@endforeach
