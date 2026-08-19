@component('mail::message', ['email' => $email, 'hideHeader' => true])
@php
    $workshops = collect($workshops ?? []);
    $onlineWorkshops = collect($onlineWorkshops ?? []);
    $allItems = $workshops
        ->merge($onlineWorkshops)
        ->sortBy(fn ($workshop) => $workshop->starts_at?->timestamp ?? PHP_INT_MAX)
        ->values();
    $storePromotion = $storePromotion ?? ['sections' => collect()];
    $storeSections = collect($storePromotion['sections'] ?? []);
    $contentOrder = ($contentOrder ?? 'workshops') === 'store' ? 'store' : 'workshops';
    $storeProducts = $storeSections->flatMap(fn ($section) => collect($section['products'] ?? []))->values();
    $heroImageCandidates = $allItems->filter(fn ($workshop) => filled($workshop->hero?->url))->values();
    $featuredWorkshop = $heroImageCandidates->isNotEmpty() ? \Illuminate\Support\Arr::random($heroImageCandidates->all()) : $allItems->first();
    $featuredProduct = $storeProducts->isNotEmpty() ? \Illuminate\Support\Arr::random($storeProducts->all()) : null;
    $featuredImageUrl = $contentOrder === 'store' && $featuredProduct
        ? url($featuredProduct->primaryImageUrl())
        : ($featuredWorkshop?->hero?->url ? url((string) $featuredWorkshop->hero->url) : null);
    $featuredImageAlt = $contentOrder === 'store' && $featuredProduct ? $featuredProduct->title : $featuredWorkshop?->title;
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-hero__table mobile-hide" style="display:table; max-width:1028px; margin:0 auto 28px auto;">
<tr>
<td style="background:#0f172a; border-radius:12px; overflow:hidden; padding:0;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td class="newsletter-hero__cell newsletter-hero__content-cell" style="padding:36px 28px 36px 36px; color:#ffffff; vertical-align:top;">
<a href="{{ url('/') }}" style="display:inline-block; margin-bottom:18px;">
<img src="{{ asset('/logo-dark.png') }}" alt="STEMMechanics" width="200" height="36" style="display:block; width:200px; height:36px;">
</a>
<div style="font-size:36px; line-height:1.02; font-weight:800; letter-spacing:-0.04em; margin:0 0 14px 0;">{{ $heroHeader ?? 'Fresh workshops are ready to book.' }}</div>
<div style="font-size:16px; line-height:1.6; color:#cbd5e1; max-width:560px;">{{ $heroCta ?? 'Pick your next session, lock in your place, and keep the momentum going with something hands-on.' }}</div>
</td>
@if($featuredImageUrl)
<td width="372" class="newsletter-hero__cell newsletter-hero__media-cell" style="padding:22px 22px 22px 12px; background:#111827; vertical-align:middle;">
<img src="{{ $featuredImageUrl }}{{ $contentOrder === 'store' ? '' : '?md' }}" alt="{{ $featuredImageAlt }}" width="332" height="224" class="newsletter-hero__media-image" style="display:block; width:332px; height:224px; object-fit:cover; border-radius:16px;">
</td>
@endif
</tr>
</table>
</td>
</tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-hero__table desktop-hide" style="display:none; max-width:1028px; margin:0 auto 28px auto;">
<tr>
<td style="background:#0f172a; border-radius:12px; overflow:hidden; padding:28px 24px;">
<a href="{{ url('/') }}" style="display:inline-block; margin-bottom:18px;">
<img src="{{ asset('/logo-dark.png') }}" alt="STEMMechanics" width="200" height="36" style="display:block; width:200px; height:36px;">
</a>
<div style="font-size:32px; line-height:1.05; font-weight:800; letter-spacing:-0.04em; margin:0 0 14px 0; color:#ffffff;">{{ $heroHeader ?? 'Fresh workshops are ready to book.' }}</div>
<div style="font-size:16px; line-height:1.6; color:#cbd5e1; margin:0 0 18px 0;">{{ $heroCta ?? 'Pick your next session, lock in your place, and keep the momentum going with something hands-on.' }}</div>
</td>
</tr>
</table>

@if($contentOrder === 'store')
@include('emails.partials.newsletter-store-section', ['hideFirstStoreHeading' => true])
@include('emails.partials.newsletter-workshop-section', ['showWorkshopHeading' => true])
@else
@include('emails.partials.newsletter-workshop-section', ['showWorkshopHeading' => false])
@include('emails.partials.newsletter-store-section', ['hideFirstStoreHeading' => false])
@endif

@slot('subcopy')
    <h4>Why did I get this email?</h4>
    <p class="sub">You received this email as you are subscribed to our upcoming workshop email list. If you wish no longer receive this email, you can <a href="{{ $unsubscribeLink }}">unsubscribe here</a>.</p>
@endslot
@endcomponent
