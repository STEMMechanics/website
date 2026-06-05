@component('mail::message')
Hi {{ $recipientName !== '' ? $recipientName : 'there' }},

@php($isClassroomAccess = (string) ($workshop['registration'] ?? '') === 'classroom')
@if($mode === 'transferred_away')
Your {{ $isClassroomAccess ? 'course registration' : 'ticket' }} for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** has been transferred to another attendee.
@elseif($mode === 'details_updated')
You're in! Your {{ $isClassroomAccess ? 'course registration' : 'ticket details' }} were updated for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** workshop.
@elseif($mode === 'cancelled')
Your {{ $isClassroomAccess ? 'course registration' : 'ticket' }} for **{{ (string) ($workshop['title'] ?? 'this workshop') }}** has been cancelled.
@else
You're in! {{ $purchaserName !== '' ? $purchaserName : 'A purchaser' }} has purchased {{ $isClassroomAccess ? 'course registration' : 'a ticket' }} for you for **{{ (string) ($workshop['title'] ?? 'this workshop') }}**.
@endif

**Workshop:** {{ (string) ($workshop['title'] ?? '-') }}<br>
**Time:** {{ (string) ($workshop['time'] ?? $workshop['starts_at'] ?? '-') }}<br>
**Where:** {{ (string) ($workshop['location'] ?? '-') }}<br>

@if($mode !== 'transferred_away' && $mode !== 'cancelled')
@if($isClassroomAccess)
**Access Holder:** {{ (string) ($ticket['name'] ?? '-') }}<br>
**Email:** {{ (string) ($ticket['email'] ?? '-') }}<br>
**Phone:** {{ (string) ($ticket['phone'] ?? '-') }}
@else
**Ticket Reference:** {{ (string) ($ticket['reference'] ?? '-') }}<br>
@if(!empty($ticket['earlyBird'] ?? false))
**Ticket Type:** Early bird ticket<br>
@endif
**Attendee:** {{ (string) ($ticket['name'] ?? '-') }}<br>
**Email:** {{ (string) ($ticket['email'] ?? '-') }}<br>
**Phone:** {{ (string) ($ticket['phone'] ?? '-') }}
@endif

@if($isClassroomAccess)
Your course registration details are included when relevant, or available from your account dashboard when you sign in.
@else
@if($mode === 'details_updated')
Your updated ticket PDF is attached.
@else
Your ticket PDF is attached.
@endif
@endif

@if($isClassroomAccess)
You can also access your courses from the [Courses]({{ url('/account/courses') }}) page when you are signed in.
@else
You can also retrieve your ticket using the [My Tickets]({{ url('/tickets') }}) link.
@endif
@endif

Thanks,  
{{ config('app.name') }}
@endcomponent
