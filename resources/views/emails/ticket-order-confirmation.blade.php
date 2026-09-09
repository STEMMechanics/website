<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking confirmed</title>
<style>
@media only screen and (max-width: 640px) {
    .booking-shell { width:100% !important; }
    .booking-content { padding:24px 20px !important; }
    .booking-title { font-size:25px !important; }
    .booking-actions td { display:block !important; padding:0 0 12px !important; }
}
</style>
</head>
<body style="margin:0; padding:0; background:#f3f5f7; color:#202b36; font-family:Arial,Helvetica,sans-serif; -webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f7;">
<tr><td align="center" style="padding:24px 0;">
<!--[if mso]><table role="presentation" width="640" align="center"><tr><td><![endif]-->
<table role="presentation" class="booking-shell" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; background:#ffffff; border:1px solid #e2e7eb; border-radius:4px;">
<tr><td class="booking-content" style="padding:32px 36px 26px;">
<a href="{{ route('index') }}"><img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" width="210" style="display:block; width:210px; max-width:100%; height:auto; border:0;"></a>
<h1 class="booking-title" style="font-size:28px; line-height:1.2; letter-spacing:-0.6px; margin:30px 0 20px; color:#202b36;">Booking confirmed</h1>
<p style="font-size:14px; line-height:1.6; margin:0 0 12px;">Hi {{ $confirmation['firstName'] }},</p>
<p style="font-size:14px; line-height:1.6; margin:0 0 24px;">Thank you for your booking.@if($confirmation['attachments'] !== '') {{ $confirmation['attachments'] }}@endif</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fa; border-left:3px solid #167eb5; margin-bottom:24px;">
<tr><td style="padding:20px;">
<div style="color:#5f6c78; font-size:11px; letter-spacing:1.1px; text-transform:uppercase;">Your workshop</div>
<h2 style="font-size:21px; line-height:1.3; margin:6px 0 12px; color:#202b36;">{{ $workshop['title'] ?? '-' }}</h2>
@if(!empty($workshop['schedule']))
@foreach($workshop['schedule'] as $session)
<p style="font-size:14px; line-height:1.6; margin:0;">{{ $session }}</p>
@endforeach
@else
<p style="font-size:14px; line-height:1.6; margin:0;">{{ $workshop['time'] ?? $workshop['starts_at'] ?? '-' }}</p>
@endif
<p style="font-size:14px; line-height:1.6; margin:12px 0 0; color:#5f6c78;">{{ $workshop['location'] ?? '-' }}</p>
</td></tr>
</table>

@if(count($tickets) > 0)
<h3 style="font-size:15px; line-height:1.4; margin:0 0 10px;">{{ $confirmation['ticketHeading'] }}</h3>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
@foreach($tickets as $ticket)
<tr>
<td style="padding:5px 16px 5px 0; font-size:14px; line-height:1.5; vertical-align:top; overflow-wrap:anywhere;">
{{ $ticket['name'] ?? '-' }}
@if(!empty($ticket['earlyBird']))<span style="color:#5f6c78; font-size:12px;"> · Early bird</span>@endif
</td>
<td align="right" style="padding:5px 0; font-size:14px; line-height:1.5; color:#5f6c78; vertical-align:top;">Ticket {{ $ticket['reference'] ?? '-' }}</td>
</tr>
@endforeach
</table>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e7eb; border-bottom:1px solid #e2e7eb; margin-bottom:24px;">
<tr><td colspan="2" style="height:16px;"></td></tr>
@if($invoice)
<tr><td style="padding:3px 16px 3px 0; font-size:14px; line-height:1.6;">Invoice</td><td align="right" style="font-size:14px; line-height:1.6;">#{{ $invoice['number'] ?? '-' }} · {{ ucfirst($invoice['status'] ?? '-') }}</td></tr>
@endif
@if(!$confirmation['free'])
<tr><td style="padding:3px 16px 3px 0; font-size:14px; line-height:1.6;">Payment method</td><td align="right" style="font-size:14px; line-height:1.6;">{{ $paymentMethodLabel }}</td></tr>
@if($creditAppliedAmount > 0.0001)
<tr><td style="padding:3px 16px 3px 0; font-size:14px; line-height:1.6;">Account credit applied</td><td align="right" style="font-size:14px; line-height:1.6;">${{ number_format($creditAppliedAmount, 2) }}</td></tr>
@if($paymentAmount > 0.0001)
<tr><td style="padding:3px 16px 3px 0; font-size:14px; line-height:1.6;">Payment received</td><td align="right" style="font-size:14px; line-height:1.6;">${{ number_format($paymentAmount, 2) }}</td></tr>
@endif
@endif
@endif
<tr>
<td style="padding:14px 16px 20px 0; font-size:14px; font-weight:bold; vertical-align:baseline;">{{ $confirmation['free'] ? 'No payment required' : ($confirmation['settled'] ? 'Total paid' : 'Amount due') }}</td>
<td align="right" style="padding:14px 0 20px; font-size:24px; font-weight:bold; vertical-align:baseline;">${{ number_format($confirmation['free'] ? 0 : ($confirmation['settled'] ? $amount : $confirmation['amountDue']), 2) }}</td>
</tr>
</table>

@if($equipmentOrder)
<h3 style="font-size:15px; line-height:1.4; margin:0 0 8px;">Store Order #{{ $equipmentOrder['number'] }}</h3>
<p style="font-size:14px; line-height:1.6; color:#5f6c78; margin:0 0 6px;">{{ $equipmentOrder['pickup'] ? 'Collection' : $equipmentOrder['delivery'] }}</p>
@foreach($confirmation['delivery'] as $deliveryLine)
<p style="font-size:14px; line-height:1.6; color:#5f6c78; margin:0 0 6px;">{{ $deliveryLine }}</p>
@endforeach
@if($equipmentOrder['pickup'])
<p style="font-size:14px; line-height:1.6; color:#5f6c78; margin:0;">We’ll let you know when your order is ready to collect.</p>
@endif
@endif

@if(trim((string) ($workshop['participantInformation'] ?? '')) !== '')
<h3 style="font-size:15px; margin:24px 0 10px;">Before your workshop</h3>
<div style="font-size:14px; line-height:1.6;">{!! $workshop['participantInformation'] !!}</div>
@endif

<table role="presentation" cellpadding="0" cellspacing="0" class="booking-actions" style="margin:24px 0 26px;">
<tr><td style="padding-right:22px;">
<a href="{{ url('/tickets') }}" style="display:inline-block; background:#167eb5; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; line-height:20px; padding:13px 20px; border-radius:4px;">Manage {{ $ticketCount === 1 ? 'ticket' : 'tickets' }}</a>
</td>
@if($equipmentOrder)
<td><a href="{{ $equipmentOrder['url'] }}" style="display:inline-block; color:#167eb5; font-size:14px; font-weight:bold; line-height:20px; padding:10px 0;">View Store Order</a></td>
@endif
</tr></table>
<p style="font-size:14px; line-height:1.6; margin:0;">We look forward to seeing you.<br><strong>The STEMMechanics team</strong></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px; border-top:1px solid #e2e7eb;"><tr><td style="padding-top:17px; font-size:12px; line-height:1.6; color:#5f6c78;">
<a href="{{ route('index') }}" style="color:#5f6c78; text-decoration:none;">STEMMechanics</a> · 63 Dalton Street, Westcourt QLD 4870<br>
<a href="{{ route('privacy') }}" style="color:#5f6c78;">Privacy</a> · <a href="{{ route('terms-conditions') }}" style="color:#5f6c78;">Terms</a>
</td></tr></table>
</td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
