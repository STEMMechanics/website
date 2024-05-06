@component('mail::message', ['email' => $email, 'unsubscribe' => $unsubscribe])
    @include('emails.welcome')
    <hr />
    <h3>Why did I get this email?</h3>
    <p>Someone asked to subscribe to our mailing list at STEMMechanics with this email address.</p>
    <p>If this wasn't you, you can <a href="{{$unsubscribe}}">unsubscribe</a>.</p>
@endcomponent
