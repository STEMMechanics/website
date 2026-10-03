@component('mail::message', ['email' => $email])
# Confirm your STEMMechanics sponsorship

You requested a {{ $frequency === 'monthly' ? 'monthly' : 'one-time' }} STEMMechanics sponsorship for **{{ $organisationName }}** in the amount of **{{ $amount }}**.

Please review and confirm this sponsorship using the button below. After confirmation, we’ll create the invoice and send it to this email address with payment details. For monthly sponsorships, we’ll send a new invoice each month until the sponsorship is cancelled.

@component('mail::button', ['url' => $confirmUrl])
Review and confirm sponsorship
@endcomponent

If you didn’t request this sponsorship, you can ignore this email. No sponsorship or invoice will be created unless you confirm. This link expires in 30 minutes and can only be used once.

Thanks,  
{{ config('app.name') }}
@endcomponent
