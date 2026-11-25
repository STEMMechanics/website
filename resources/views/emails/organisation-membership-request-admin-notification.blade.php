@component('mail::message')
# Organisation join request needs review

No organisation managers are currently available to review this request.

**Organisation:** {{ $membershipRequest->organisation?->name ?? 'Organisation' }}  
**Requested by:** {{ $membershipRequest->user?->getName() ?? 'User' }}  
**Email:** {{ $membershipRequest->user?->email ?? '-' }}  
**Requested:** {{ $membershipRequest->created_at?->format('j M Y, g:i a') ?? '-' }}

@component('mail::button', ['url' => $adminUrl])
Review join request
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
