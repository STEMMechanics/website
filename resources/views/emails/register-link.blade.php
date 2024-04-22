@component('mail::message', ['username' => $username, 'email' => $email])
    <h2 class="center narrow">Follow this link to register your account.</h2>
    <p class="center narrow">For your security, this link <strong>can only be used once</strong> and <strong>expires after 10 minutes.</strong></p>
    <p class="center narrow">
        @component('mail::button', ['url' => route('login', ['token' => $token])])
            Register
        @endcomponent
    </p>
    <hr />
    <h3>Why did I get this link?</h3>
    <p class="sub">Someone asked to register an account at STEMMechanics with this email.</p>
    <p class="sub">If this wasn't you, you can ignore this email.</p>
@endcomponent
