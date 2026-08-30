@php
    $forwardPromotion = config('newsletter.forward_promotion', []);
    $subscribeUrl = route('index', [
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'forward_to_friend',
    ]).'#subscribe';
@endphp

@if((bool) ($forwardPromotion['enabled'] ?? true))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:780px; margin:32px auto 24px auto;">
<tr>
<td style="padding:28px 24px; border-radius:16px; background:#e0f2fe; border:1px solid #bae6fd; text-align:center;">
<img src="{{ url('/newsletter-share.png') }}" alt="Share the STEMMechanics newsletter with a friend" width="220" style="display:block; width:220px; max-width:100%; height:auto; margin:0 auto 20px auto;">
<div style="font-size:24px; line-height:1.2; font-weight:800; color:#0f172a; margin:0 0 10px 0;">{{ $forwardPromotion['heading'] ?? "Know someone who'd love this?" }}</div>
<div style="max-width:600px; margin:0 auto 20px auto; font-size:15px; line-height:1.6; color:#334155;">{{ $forwardPromotion['message'] ?? 'Forward this email to someone who would enjoy it. They can subscribe for future updates.' }}</div>
<a href="{{ $subscribeUrl }}" target="_blank" rel="noopener" class="newsletter-hero-cta" style="display:inline-block; background:#0369a1; color:#ffffff; text-decoration:none; font-size:16px; font-weight:800; padding:14px 24px; border-radius:24px;">{{ $forwardPromotion['button_label'] ?? 'Join the newsletter' }}</a>
</td>
</tr>
</table>
@endif
