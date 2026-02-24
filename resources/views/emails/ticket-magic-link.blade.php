@component('mail::message', ['email' => $email])
<p>Hey there!</p>
<p>Use the link below to view your tickets.</p>

<p class="tall center">
    @component('mail::button', ['url' => $magicUrl])
    View My Tickets
    @endcomponent
</p>

<p>This link expires in 30 minutes and can only be used once.</p>

<p>Warm regards,</p>
<p>—James 😁</p>
@endcomponent
