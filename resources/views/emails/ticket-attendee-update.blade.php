@component('mail::message')
Hi {{ $recipientName !== '' ? $recipientName : 'there' }},

@if($mode === 'transferred_away')
Your ticket for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** has been transferred to another attendee.
@elseif($mode === 'details_updated')
You're in! Your ticket details were updated for **{{ (string) ($workshop['title'] ?? 'this workshop') }}**.
@else
You're in! {{ $purchaserName !== '' ? $purchaserName : 'A purchaser' }} has purchased a ticket for you for **{{ (string) ($workshop['title'] ?? 'this workshop') }}**.
@endif

**Workshop:** {{ (string) ($workshop['title'] ?? '-') }}  
**Time:** {{ (string) ($workshop['time'] ?? $workshop['starts_at'] ?? '-') }}  
**Where:** {{ (string) ($workshop['location'] ?? '-') }}

@if($mode !== 'transferred_away')
**Ticket Reference:** {{ (string) ($ticket['reference'] ?? '-') }}  
**Attendee:** {{ (string) ($ticket['name'] ?? '-') }}  
**Email:** {{ (string) ($ticket['email'] ?? '-') }}  
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
