@component('mail::message')
# New Contact Message

**From:** {{ $senderName }}  
**Email:** {{ $senderEmail }}  
**Subject:** {{ $subjectLine }}

@if(!empty($senderUserId))
**User ID:** {{ $senderUserId }}  
@endif
@if(!empty($senderIp))
**IP Address:** {{ $senderIp }}  
@endif
@if(!empty($userAgent))
**Browser:** {{ $userAgent }}
@endif

## Message

{{ $messageBody }}
@endcomponent
