@if($allItems->isNotEmpty())
@if(($showWorkshopHeading ?? false))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:780px; margin:38px auto 16px auto;">
    <tr><td style="padding:0 0 14px 0; text-align:left;">
        <div style="font-size:30px; line-height:1.1; font-weight:900; color:#0f172a; letter-spacing:-0.03em;">Upcoming workshops</div>
        <div style="max-width:680px; margin:10px 0 0 0; color:#64748b; font-size:15px; line-height:1.6;">Book a hands-on session and keep the making going.</div>
    </td></tr>
</table>
@endif
@foreach($allItems as $workshop)
@php
    $accent = $workshop->getLocationName() === 'Online' ? '#16a34a' : '#2563eb';
    $badgeText = $workshop->getLocationName();
@endphp
@include('emails.partials.upcoming-workshop-card', [
    'workshop' => $workshop,
    'accent' => $accent,
    'badgeText' => $badgeText,
    'showSummary' => true,
    'showScheduleLines' => false,
    'showImage' => true,
    'compact' => false,
    'showLocationFooter' => false,
])
@endforeach

<p class="tall center" style="margin-top:28px; margin-bottom:34px;">
    <a href="https://stemmechanics.com.au/workshops" target="_blank" rel="noopener" class="newsletter-hero-cta" style="display:inline-block; background:#0f172a; color:#ffffff; text-decoration:none; font-size:18px; font-weight:800; padding:18px 34px; border-radius:24px;">{{ $heroButtonLabel ?? 'View All Workshops' }}</a>
</p>
@endif
