@component('mail::message', ['email' => $email])
# Manage your sponsorship

Use the secure link below to view or update your STEMMechanics sponsorship.

@component('mail::button', ['url' => $manageUrl])
Manage sponsorship
@endcomponent

This link expires in 30 minutes and can only be used once. If you did not request it, you can ignore this email.

Thanks,  
{{ config('app.name') }}
@endcomponent
