@component('mail::message')
# Sponsor recognition needs approval

Public recognition details have been submitted for **{{ $sponsor->publicLabel() ?: 'a sponsor' }}**.

Please review the display name, website and any supplied logo before publishing them on the STEMMechanics website.

**Sponsor type:** {{ ucfirst($sponsor->sponsor_type) }}  
**Website:** {{ $sponsor->website_url ?: 'Not supplied' }}  
**Logo supplied:** {{ $sponsor->recognition_logo_path ? 'Yes' : 'No' }}

@component('mail::button', ['url' => $adminUrl])
Review sponsor recognition
@endcomponent

Thanks,  
{{ config('app.name') }}
@endcomponent
