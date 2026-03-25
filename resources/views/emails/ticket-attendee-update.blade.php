@component('mail::message')
Hi {{ $recipientName !== '' ? $recipientName : 'there' }},

@if($mode === 'transferred_away')
Your ticket for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** has been transferred to another attendee.
@elseif($mode === 'details_updated')
You're in! Your ticket details were updated for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** workshop.
@elseif($mode === 'cancelled')
Your ticket for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** has been cancelled.
@else
You're in! {{ $purchaserName !== '' ? $purchaserName : 'A purchaser' }} has purchased a ticket for you for **{{ (string) ($workshop['title'] ?? 'this workshop') }}**.
@endif

**Workshop:** {{ (string) ($workshop['title'] ?? '-') }}<br>
**Time:** {{ (string) ($workshop['time'] ?? $workshop['starts_at'] ?? '-') }}<br>
**Where:** {{ (string) ($workshop['location'] ?? '-') }}<br>

@if($mode !== 'transferred_away' && $mode !== 'cancelled')
**Ticket Reference:** {{ (string) ($ticket['reference'] ?? '-') }}<br>
**Attendee:** {{ (string) ($ticket['name'] ?? '-') }}<br>
**Email:** {{ (string) ($ticket['email'] ?? '-') }}<br>
**Phone:** {{ (string) ($ticket['phone'] ?? '-') }}

@if($mode === 'details_updated')
Your updated ticket PDF is attached.
@else
Your ticket PDF is attached.
@endif

You can also retrieve your ticket using the [My Tickets]({{ url('/tickets') }}) link.
@endif

Thanks,  
{{ config('app.name') }}
@endcomponent
