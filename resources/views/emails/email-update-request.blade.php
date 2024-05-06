@component('mail::message', ['email' => $email])
    <p>Hey there!</p>
    <p>You requested to update your email address at STEMMechanics to {{ $newEmail }}. Click the link below to confirm this change:</p>
    <p class="tall center">
        @component('mail::button', ['url' => $update_url])
            Update Email
        @endcomponent
    </p>
    <p>Remember, the link expires in 30 minutes.</p>
    <p>Warm regards,</p>
    <p>—James 😁</p>
    @slot('subcopy')
        <h4>Why did I get this email?</h4>
        <p class="sub">Someone asked to change the email address associated with an account at STEMMechanics with this email. If this wasn't you, you can ignore this email.</p>
    @endslot
@endcomponent
