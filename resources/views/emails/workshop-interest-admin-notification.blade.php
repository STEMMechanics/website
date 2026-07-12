@component('mail::message')
# Workshop Interest Registered

Someone has registered interest in **{{ $workshop->title ?: 'Workshop' }}**.

- **Email:** {{ $interest->email ?: '-' }}
- **Phone:** {{ $interest->phone ?: '-' }}
- **Workshop date:** {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}
- **Location:** {{ $workshop->getLocationName() }}

[Admin Page]({{ $adminUrl }})  |  [Public page]({{ $publicUrl }})

Thanks,<br>
{{ config('app.name') }}
@endcomponent
