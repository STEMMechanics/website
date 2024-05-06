@component('mail::message', ['email' => $email])
    <p>Hey there!</p>
    <p>You requested a link to log in to STEMMechanics, and here it is!</p>
    <p class="tall center">
        @component('mail::button', ['url' => $login_url])
            Log in
        @endcomponent
    </p>
    <p>Remember, the link expires in 10 minutes and can only be used once.</p>
    <p>Warm regards,</p>
    <p>—James 😁</p>
    @slot('subcopy')
        <h4>Why did I get this email?</h4>
        <p class="sub">Someone asked for a link to log in to STEMMechanics with this email address. If this wasn't you, you can ignore this email.</p>
    @endslot
@endcomponent
