@component('mail::message')
@php
    $interestUser = $interest->user;
    $parentUser = $interestUser?->parent;
    $isChildAccount = (bool) ($interestUser?->isChildAccount() ?? false);
@endphp

# Workshop Interest Registered

Someone has registered interest in **{{ $workshop->title ?: 'Workshop' }}**.

- **Name:** {{ $interest->name ?: '-' }}{{ $isChildAccount ? ' (Child Account)' : '' }}
@if(!$isChildAccount)
- **Email:** {{ $interest->email ?: '-' }}
- **Phone:** {{ $interest->phone ?: '-' }}
@else
- **Parent name:** {{ $parentUser?->getName() ?: '-' }}
- **Parent email:** {{ $parentUser?->email ?: '-' }}
- **Parent phone:** {{ $parentUser?->phone ?: '-' }}
@endif
- **Workshop date:** {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}
- **Location:** {{ $workshop->getLocationName() }}

[Admin Page]({{ $adminUrl }})  |  [Public page]({{ $publicUrl }})

Thanks,<br>
{{ config('app.name') }}
@endcomponent
