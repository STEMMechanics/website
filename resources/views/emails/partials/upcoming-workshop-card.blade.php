@php
    $accent = $accent ?? '#2563eb';
    $badgeText = trim((string) ($badgeText ?? $workshop->getLocationName()));
    $compact = (bool) ($compact ?? false);
    $showSummary = (bool) ($showSummary ?? true);
    $showScheduleLines = (bool) ($showScheduleLines ?? false);
    $showImage = (bool) ($showImage ?? true);
    $showLocationFooter = (bool) ($showLocationFooter ?? true);
    $heroUrl = $showImage && $workshop->hero?->url ? url((string) $workshop->hero->url) : null;
    $locationName = $workshop->getLocationName();
    $scheduleLabel = $workshop->courseScheduleFirstStartLabel();
    $cadenceLabel = $workshop->courseScheduleCadenceLabel();
    $description = $workshop->newsletterSummary($compact ? 120 : 180);
    $scheduleLines = collect($workshop->usesClassroomRegistration() ? $workshop->courseScheduleDisplayLines() : [])
        ->filter(fn ($line) => trim((string) $line) !== '')
        ->take($compact ? 2 : 3)
        ->values();
    $priceLabel = $workshop->price && is_numeric($workshop->price) && $workshop->price != '0'
        ? '$'.number_format((float) $workshop->price, 2)
        : 'Free';
    $statusLabel = $workshop->status === 'scheduled' ? 'Opens Soon' : null;
    $ctaUrl = route('workshop.show', $workshop);
    $ctaLabel = 'View Details';
    $ctaTarget = null;

    if ($workshop->registration === 'tickets') {
//        $ctaUrl = route('workshop.ticket.flow.start', $workshop);
        $ctaLabel = 'Get Tickets';
    } elseif ($workshop->registration === 'classroom') {
//        $ctaUrl = route('workshop.ticket.flow.start', $workshop);
        $ctaLabel = 'Enrol Now';
    } elseif ($workshop->registration === 'link' && filter_var(trim((string) ($workshop->registration_data ?? '')), FILTER_VALIDATE_URL)) {
//        $ctaUrl = trim((string) $workshop->registration_data);
        $ctaLabel = 'Register Now';
        $ctaTarget = '_blank';
    } elseif ($workshop->registration === 'email' && filter_var(trim((string) ($workshop->registration_data ?? '')), FILTER_VALIDATE_EMAIL)) {
        $ctaUrl = 'mailto:'.trim((string) $workshop->registration_data);
        $ctaLabel = 'Email to Register';
    }

    if (filter_var($ctaUrl, FILTER_VALIDATE_URL) && str_starts_with($ctaUrl, 'https://') && $ctaTarget === null && $workshop->registration === 'link') {
        $ctaTarget = '_blank';
    }
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-workshop-card__table newsletter-workshop-card__desktop" style="max-width:780px; margin:0 auto 14px auto;">
<tr>
<td style="border:1px solid #e2e8f0; border-left:8px solid {{ $accent }}; border-radius: 8px; overflow:hidden; background:#ffffff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tr>
@if($heroUrl)
<td width="256" valign="top" class="newsletter-workshop-card__image-cell" style="padding:0; background:#f8fafc;">
<img src="{{ $heroUrl }}?md" alt="{{ $workshop->title }}" width="256" height="256" class="newsletter-workshop-card__image" style="display:block; width:256px; height:256px; object-fit:cover;">
</td>
@endif
<td valign="top" class="newsletter-workshop-card__body-cell" style="padding:18px 18px 18px 18px;">
<div style="font-size:18px; line-height:1.22; font-weight:800; color:#0f172a; margin:0 0 8px 0;"><a href="{{ route('workshop.show', $workshop->slug) }}" style="color:#0f172a; text-decoration:none;">{{ $workshop->title }}</a></div>
<div style="display:inline-block; padding:5px 10px; border-radius:999px; background:{{ $accent }}; color:#ffffff; font-size:11px; font-weight:800; letter-spacing:0.12em; text-transform:uppercase; margin-bottom:10px;">{{ $badgeText !== '' ? $badgeText : $locationName }}</div>
<div style="font-size:14px; line-height:1.45; color:#475569; margin-bottom:10px;">{{ $scheduleLabel }}@if($cadenceLabel) - {{ $cadenceLabel }}@endif</div>
@if($showSummary && $description !== '')
<div style="font-size:14px; line-height:1.5; color:#334155; margin-bottom:8px;">{{ $description }}</div>
@endif
@if($showScheduleLines && $scheduleLines->isNotEmpty())
<div style="font-size:13px; line-height:1.5; color:#334155; margin-bottom:8px;">
@foreach($scheduleLines as $line)
<div style="margin-bottom:3px;">• {{ $line }}</div>
@endforeach
</div>
@endif
</td>
<td width="190" valign="top" class="newsletter-workshop-card__meta-cell" style="padding:18px 18px 18px 0; text-align:right;">
<div class="newsletter-workshop-card__price" style="font-size:26px; line-height:1; font-weight:900; color:#0f172a; letter-spacing:-0.04em; margin-bottom:12px;">{{ $priceLabel }}</div>
@php
    $footerParts = [];

    if ($showLocationFooter) {
        $footerParts[] = $locationName . ($cadenceLabel ? ' - '.$cadenceLabel : '');
    } elseif ($cadenceLabel) {
        $footerParts[] = $cadenceLabel;
    }

    if ($statusLabel) {
        $footerParts[] = $statusLabel;
    }
@endphp
@if($footerParts !== [])
<div style="font-size:12px; line-height:1.5; color:#64748b; margin-bottom:12px;">
{{ implode(' · ', $footerParts) }}
</div>
@endif
<a href="{{ $ctaUrl }}" @if($ctaTarget) target="{{ $ctaTarget }}" rel="noopener" @endif class="newsletter-workshop-cta" style="display:inline-block; padding:13px 16px; border-radius:12px; background:{{ $accent }}; color:#ffffff; font-size:15px; font-weight:800; text-decoration:none;">{{ $ctaLabel }}</a>
</td>
</tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-workshop-card__table newsletter-workshop-card__mobile" style="display:none; max-width:780px; margin:0 auto 14px auto;">
<tr>
<td style="border:1px solid #e2e8f0; border-left:8px solid {{ $accent }}; border-radius:8px; overflow:hidden; background:#ffffff; padding:18px;">
<div style="font-size:18px; line-height:1.22; font-weight:800; color:#0f172a; margin:0 0 8px 0;"><a href="{{ route('workshop.show', $workshop->slug) }}" style="color:#0f172a; text-decoration:none;">{{ $workshop->title }}</a></div>
<div style="display:inline-block; padding:5px 10px; border-radius:999px; background:{{ $accent }}; color:#ffffff; font-size:11px; font-weight:800; letter-spacing:0.12em; text-transform:uppercase; margin-bottom:10px;">{{ $badgeText !== '' ? $badgeText : $locationName }}</div>
<div style="font-size:14px; line-height:1.45; color:#475569; margin-bottom:10px;">{{ $scheduleLabel }}@if($cadenceLabel) - {{ $cadenceLabel }}@endif</div>
@if($showSummary && $description !== '')
<div style="font-size:14px; line-height:1.5; color:#334155; margin-bottom:10px;">{{ $description }}</div>
@endif
@if($showScheduleLines && $scheduleLines->isNotEmpty())
<div style="font-size:13px; line-height:1.5; color:#334155; margin-bottom:10px;">
@foreach($scheduleLines as $line)
<div style="margin-bottom:3px;">• {{ $line }}</div>
@endforeach
</div>
@endif
<div class="newsletter-workshop-card__price newsletter-workshop-card__price--mobile" style="font-size:19px; line-height:1; font-weight:900; color:#0f172a; letter-spacing:-0.04em; margin-bottom:12px;">{{ $priceLabel }}</div>
@php
    $mobileFooterParts = [];

    if ($showLocationFooter) {
        $mobileFooterParts[] = $locationName . ($cadenceLabel ? ' - '.$cadenceLabel : '');
    } elseif ($cadenceLabel) {
        $mobileFooterParts[] = $cadenceLabel;
    }

    if ($statusLabel) {
        $mobileFooterParts[] = $statusLabel;
    }
@endphp
@if($mobileFooterParts !== [])
<div style="font-size:12px; line-height:1.5; color:#64748b; margin-bottom:12px;">
{{ implode(' · ', $mobileFooterParts) }}
</div>
@endif
<a href="{{ $ctaUrl }}" @if($ctaTarget) target="{{ $ctaTarget }}" rel="noopener" @endif class="newsletter-workshop-cta" style="display:block; padding:13px 16px; border-radius:12px; background:{{ $accent }}; color:#ffffff; font-size:15px; font-weight:800; text-decoration:none; text-align:center;">{{ $ctaLabel }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
