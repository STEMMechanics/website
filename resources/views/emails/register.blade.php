@component('mail::message', ['email' => $email])
<p>Hey there!</p>
<p>We're thrilled to have you join us. To complete your registration and officially become part of the community, just click link below:</p>
<p class="tall center">
@component('mail::button', ['url' => $register_url])
Register
@endcomponent
</p>
<p>Remember, the link expires in 10 minutes and can only be used once, so act fast!</p>
<p>Warm regards,</p>
<p>—James 😁</p>

@slot('subcopy')
<h4>Why did I get this email?</h4>
<p class="sub">Someone asked to register at STEMMechanics with this email address. If this wasn't you, you can ignore this email.</p>
@endslot
@endcomponent
