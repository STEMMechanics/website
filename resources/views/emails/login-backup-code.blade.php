@component('mail::message', ['email' => $email])
    <p>Hey there!</p>
    <p>We just wanted to let you know that someone just logged in using a backup code.</p>
    <p>If this was you, then it is all good!</p>
    <p>If it's not, we recommend you let us know by replying to this email and resetting your backup codes by:</p>
    <ul>
        <li>Logging into your account on STEMMechanics</li>
        <li>Visit your account page</li>
        <li>Under <strong>Two Factor Authentication</strong> - Click <i>Reset Backup Codes</i></li>
    </ul>
    <p>Warm regards,</p>
    <p>—James 😁</p>
@endcomponent
