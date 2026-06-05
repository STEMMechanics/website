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
    $durationLabel = $workshop->workshopDurationLabel();
    $cadenceLabel = $workshop->courseScheduleCadenceLabel();
    $description = $workshop->newsletterSummary($compact ? 120 : 180);
    $scheduleLines = collect($workshop->usesClassroomRegistration() ? $workshop->courseScheduleDisplayLines() : [])
        ->filter(fn ($line) => trim((string) $line) !== '')
        ->take($compact ? 2 : 3)
        ->values();
    $priceAmount = $workshop->currentTicketPriceAmount();
    $priceLabel = $priceAmount > 0.0001
        ? '$'.number_format($priceAmount, 2)
        : 'Free';
    $earlyBirdSummary = $workshop->earlyBirdSummaryLabel();
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
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-workshop-card__table" style="max-width:780px; margin:0 auto 24px auto; background:#ffffff; border:1px solid #e2e8f0; border-left:8px solid {{ $accent }}; border-radius:8px; overflow:hidden;">
<tr>
<td style="padding:18px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tr style="vertical-align:top;">
@if($heroUrl)
<td class="newsletter-workshop-card__media-cell mobile-hide" width="220" valign="stretch" style="padding:0 16px 0 0; vertical-align:top;">
<table role="presentation" width="100%" height="100%" cellspacing="0" cellpadding="0" class="newsletter-workshop-card__image-shell mobile-hide" style="display:table; margin:0; height:100%; border-radius:14px; overflow:hidden;">
<tr>
<td style="height:100%;">
<img src="{{ $heroUrl }}?md" alt="{{ $workshop->title }}" width="220" height="260" class="newsletter-workshop-card__image" style="display:block; width:100%; height:260px; object-fit:cover; border-radius:14px;">
</td>
</tr>
</table>
</td>
@endif
<td valign="top" class="newsletter-workshop-card__content-cell" style="{{ $heroUrl ? 'padding:0 0 0 0;' : 'padding:0;' }}">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0;">
<tr>
<td style="padding:0 0 8px 0; font-size:18px; line-height:1.22; font-weight:800; color:#0f172a;">
<a href="{{ route('workshop.show', $workshop->slug) }}" style="color:#0f172a; text-decoration:none;">{{ $workshop->title }}</a>
</td>
</tr>
<tr>
<td style="padding:0 0 10px 0;">
<span style="display:inline-block; padding:5px 10px; border-radius:999px; background:#ffffff; border:1px solid {{ $accent }}; color:{{ $accent }}; font-size:11px; font-weight:800; letter-spacing:0.12em; text-transform:uppercase;">{{ $badgeText !== '' ? $badgeText : $locationName }}</span>
</td>
</tr>
<tr>
<td style="padding:0 0 10px 0; font-size:14px; line-height:1.45; color:#475569;">{{ $scheduleLabel }}@if($durationLabel) ({{ $durationLabel }})@endif@if($cadenceLabel) - {{ $cadenceLabel }}@endif</td>
</tr>
</table>

@if($showSummary && $description !== '')
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0;">
<tr>
<td style="padding:0 0 10px 0; font-size:14px; line-height:1.5; color:#334155;">{{ $description }}</td>
</tr>
</table>
@endif

@if($showScheduleLines && $scheduleLines->isNotEmpty())
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 10px 0;">
<tr>
<td style="font-size:13px; line-height:1.5; color:#334155; padding:0;">
@foreach($scheduleLines as $line)
<div style="margin-bottom:3px;">• {{ $line }}</div>
@endforeach
</td>
</tr>
</table>
@endif

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
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 14px 0;">
<tr>
<td style="padding:0; font-size:12px; line-height:1.5; color:#64748b;">{{ implode(' · ', $footerParts) }}</td>
</tr>
</table>
@endif

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0 0 0; border-top:1px solid #e2e8f0;">
<tr>
<td valign="middle" style="padding:14px 0 0 0; width:60%;">
<span class="newsletter-workshop-card__price" style="font-size:20px; line-height:1; font-weight:900; color:#0f172a; letter-spacing:-0.04em;">{{ $priceLabel }}</span>
@if($earlyBirdSummary)
<div style="margin-top:5px; font-size:12px; line-height:1.4; color:#b45309; font-weight:700;">{{ $earlyBirdSummary }}</div>
@endif
</td>
<td valign="middle" align="right" style="padding:14px 0 0 0; width:40%;">
<a href="{{ $ctaUrl }}" @if($ctaTarget) target="{{ $ctaTarget }}" rel="noopener" @endif class="newsletter-workshop-cta" style="display:inline-block; padding:11px 16px; border-radius:10px; background:#f8fafc; border:1px solid {{ $accent }}; color:{{ $accent }}; font-size:14px; font-weight:800; text-decoration:none; text-align:center; white-space:nowrap;">{{ $ctaLabel }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
