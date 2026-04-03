@php
    $discordUrl = 'https://discord.gg/yNzk4x7mpD';
    $communityImage = asset('/community-discord.webp');
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:1028px; margin:0 auto 24px auto;">
<tr>
<td style="background:#0f172a; border-radius:12px; overflow:hidden;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td style="padding:34px 30px 34px 34px; color:#ffffff; vertical-align:top;">
<div style="display:inline-block; padding:5px 10px; border-radius:999px; background:#0ea5e9; color:#ffffff; font-size:11px; font-weight:800; letter-spacing:0.12em; text-transform:uppercase; margin-bottom:16px;">Community first</div>
<div style="font-size:34px; line-height:1.05; font-weight:800; letter-spacing:-0.04em; margin:0 0 14px 0;">Welcome to STEMMechanics</div>
<div style="font-size:16px; line-height:1.7; color:#cbd5e1; max-width:560px; margin-bottom:18px;">
The quickest way to keep up with what’s happening is our Discord. That’s where the conversations, ideas, questions, and workshop updates all live.
</div>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0;">
<tr>
<td style="padding-right:12px; padding-bottom:12px;">
<a href="{{ $discordUrl }}" target="_blank" rel="noopener" class="newsletter-hero-cta" style="display:inline-block; background:#5865f2; color:#ffffff; text-decoration:none; font-size:16px; font-weight:800; padding:14px 20px; border-radius:12px;">Join Discord</a>
</td>
<td style="padding-bottom:12px;">
<a href="{{ url('/workshops') }}" target="_blank" rel="noopener" class="newsletter-workshop-cta" style="display:inline-block; background:#ffffff; color:#0f172a; text-decoration:none; font-size:16px; font-weight:800; padding:14px 20px; border-radius:12px;">See Workshops</a>
</td>
</tr>
</table>
</td>
<td width="372" style="padding:20px 20px 20px 12px; vertical-align:middle; background:#111827;">
<a href="{{ $discordUrl }}" target="_blank" rel="noopener" style="display:block; text-decoration:none;">
<img src="{{ $communityImage }}" alt="STEMMechanics community and Discord" width="332" height="224" style="display:block; width:332px; height:224px; object-fit:cover; border-radius:14px;">
</a>
</td>
</tr>
</table>
</td>
</tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:780px; margin:0 auto 18px auto;">
<tr>
<td style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:24px 24px 20px 24px;">
<div style="font-size:16px; line-height:1.7; color:#334155;">
<p style="margin:0 0 14px;">You’ll still get the important workshop emails, but Discord is where we keep the energy up between sessions.</p>
<p style="margin:0 0 14px;">Jump in, say hello, and tell us what you or your kids are building. We read and reply there all the time.</p>
</div>

<div style="margin-top:18px; display:flex; flex-wrap:wrap; gap:10px;">
<div style="display:inline-block; padding:10px 14px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:13px; font-weight:700;">Workshop chat</div>
<div style="display:inline-block; padding:10px 14px; border-radius:999px; background:#ecfdf5; color:#047857; font-size:13px; font-weight:700;">Build ideas</div>
<div style="display:inline-block; padding:10px 14px; border-radius:999px; background:#fff7ed; color:#c2410c; font-size:13px; font-weight:700;">Updates & announcements</div>
</div>
</td>
</tr>
</table>

@component('mail::button', ['url' => $discordUrl])
Join the Discord
@endcomponent

@slot('subcopy')
    <h4>Why did I get this email?</h4>
    <p class="sub">You joined our mailing list at STEMMechanics. If you’d rather not receive updates, you can <a href="{{ $unsubscribeLink }}">unsubscribe here</a>.</p>
@endslot
