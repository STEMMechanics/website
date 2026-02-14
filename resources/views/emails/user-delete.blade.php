@component('mail::message', ['email' => $email])
<p>Your account has been successfully deleted. We're sorry to see you go.</p>
<p>If you have any feedback or there’s anything we could have done better, we’d love to hear from you.</p>
<p>Your email has been removed from our mailing list, so you will no longer receive emails from us. If you change your mind in the future, you’re always welcome to create a new account and join us again.</p>
<p>Any workshop tickets you’ve already purchased will remain valid.</p>
<p>Warm regards,</p>
<p>—James 😁</p>
@slot('subcopy')
<h4>Why did I get this email?</h4>
<p class="sub">You requested to delete your account at STEMMechanics with this email address.</p>
@endslot
@endcomponent
