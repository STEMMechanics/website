@component('mail::message', ['email' => $email, 'hideHeader' => true])
@php
    $workshops = collect($workshops ?? []);
    $onlineWorkshops = collect($onlineWorkshops ?? []);
    $courses = collect($courses ?? []);
    $allItems = $workshops
        ->merge($onlineWorkshops)
        ->merge($courses)
        ->sortBy(fn ($workshop) => $workshop->starts_at?->timestamp ?? PHP_INT_MAX)
        ->values();
    $featuredWorkshop = $allItems->first(fn ($workshop) => filled($workshop->hero?->url))
        ?? $allItems->first();
    $featuredImageUrl = $featuredWorkshop?->hero?->url ? url((string) $featuredWorkshop->hero->url) : null;
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-hero__table newsletter-hero__desktop" style="max-width:1028px; margin:0 auto 28px auto;">
<tr>
<td style="background:#0f172a; border-radius:12px; overflow:hidden; padding:0;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td class="newsletter-hero__cell newsletter-hero__content-cell" style="padding:36px 28px 36px 36px; color:#ffffff; vertical-align:top;">
<a href="{{ url('/') }}" style="display:inline-block; margin-bottom:18px;">
<img src="{{ asset('/logo-dark.svg') }}" alt="STEMMechanics" width="200" height="31" style="display:block; width:200px; height:31px;">
</a>
<div style="font-size:36px; line-height:1.02; font-weight:800; letter-spacing:-0.04em; margin:0 0 14px 0;">{{ $heroHeader ?? 'Fresh workshops are ready to book.' }}</div>
<div style="font-size:16px; line-height:1.6; color:#cbd5e1; max-width:560px;">{{ $heroCta ?? 'Pick your next session, lock in your place, and keep the momentum going with something hands-on.' }}</div>
</td>
@if($featuredImageUrl)
<td width="372" class="newsletter-hero__cell newsletter-hero__media-cell" style="padding:22px 22px 22px 12px; background:#111827; vertical-align:middle;">
<img src="{{ $featuredImageUrl }}?md" alt="{{ $featuredWorkshop->title }}" width="332" height="224" class="newsletter-hero__media-image" style="display:block; width:332px; height:224px; object-fit:cover; border-radius:14px;">
</td>
@endif
</tr>
</table>
</td>
</tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-hero__table newsletter-hero__mobile" style="display:none; max-width:1028px; margin:0 auto 28px auto;">
<tr>
<td style="background:#0f172a; border-radius:12px; overflow:hidden; padding:28px 24px;">
<a href="{{ url('/') }}" style="display:inline-block; margin-bottom:18px;">
<img src="{{ asset('/logo-dark.svg') }}" alt="STEMMechanics" width="200" height="31" style="display:block; width:200px; height:31px;">
</a>
<div style="font-size:32px; line-height:1.05; font-weight:800; letter-spacing:-0.04em; margin:0 0 14px 0; color:#ffffff;">{{ $heroHeader ?? 'Fresh workshops are ready to book.' }}</div>
<div style="font-size:16px; line-height:1.6; color:#cbd5e1; margin:0 0 18px 0;">{{ $heroCta ?? 'Pick your next session, lock in your place, and keep the momentum going with something hands-on.' }}</div>
<a href="https://stemmechanics.com.au/workshops" target="_blank" rel="noopener" class="newsletter-hero-cta" style="display:inline-block; background:#0f172a; color:#ffffff; text-decoration:none; font-size:18px; font-weight:800; padding:18px 34px; border-radius:24px;">{{ $heroButtonLabel ?? 'View All Workshops' }}</a>
</td>
</tr>
</table>

@if($allItems->isNotEmpty())
@foreach($allItems as $workshop)
@php
    $accent = match ((string) $workshop->registration) {
        'classroom' => '#f97316',
        default => $workshop->getLocationName() === 'Online' ? '#16a34a' : '#2563eb',
    };
    $badgeText = $workshop->usesClassroomRegistration()
        ? 'Online Course'
        : $workshop->getLocationName();
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-workshop-card__table" style="max-width:780px; margin:0 auto 14px auto;">
<tr>
<td>
@include('emails.partials.upcoming-workshop-card', [
    'workshop' => $workshop,
    'accent' => $accent,
    'badgeText' => $badgeText,
    'showSummary' => true,
    'showScheduleLines' => $workshop->usesClassroomRegistration(),
    'showImage' => true,
    'compact' => false,
    'showLocationFooter' => $workshop->usesClassroomRegistration(),
])
</td>
</tr>
</table>
@endforeach
@endif

<p class="tall center" style="margin-top: 28px">
    <a href="https://stemmechanics.com.au/workshops" target="_blank" rel="noopener" class="newsletter-hero-cta" style="display:inline-block; background:#0f172a; color:#ffffff; text-decoration:none; font-size:18px; font-weight:800; padding:18px 34px; border-radius:24px;">{{ $heroButtonLabel ?? 'View All Workshops' }}</a>
</p>

@slot('subcopy')
    <h4>Why did I get this email?</h4>
    <p class="sub">You received this email as you are subscribed to our upcoming workshop email list. If you wish no longer receive this email, you can <a href="{{ $unsubscribeLink }}">unsubscribe here</a>.</p>
@endslot
@endcomponent
