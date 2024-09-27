@component('mail::message', ['email' => $email])
    <p>Hey there!</p>
    <p>Check out our exciting workshops coming up in the next few weeks:</p>
    <p class="center">
    @php
        $currentLocation = null;
    @endphp
    @foreach($workshops as $workshop)
        @if($workshop->location->name !== $currentLocation)
            <h2 style="margin-top: 32px; margin-bottom: 6px">{{ $workshop->location->name }}</h2>
            @php
                $currentLocation = $workshop->location->name;
            @endphp
        @endif
        <p style="margin-bottom: 6px">{{ $workshop->starts_at->format('D, j M, g:i A') . ' - ' }}<a href="{{ route('workshop.show', $workshop->slug) }}">{{ $workshop->title }}</a> ({{ ($workshop->price && is_numeric($workshop->price) && $workshop->price != '0' ? '$' . number_format((float)$workshop->price, 2) : 'Free') . ( $workshop->status === 'scheduled' ? ' / Opens soon' : '') }})</p>
    @endforeach
    <p class="tall center" style="margin-top: 32px">
        @component('mail::button', ['url' => 'https://stemmechanics.com.au/workshops'])
            View All Workshops
        @endcomponent
    </p>
    <p>We hope to see you at one of our upcoming workshops!</p>
    <p>Warm regards,</p>
    <p>—James 😁</p>
    @slot('subcopy')
        <h4>Why did I get this email?</h4>
        <p class="sub">You received this email as you are subscribed to our upcoming workshop email list. If you wish no longer receive this email, you can <a href="{{ $unsubscribeLink }}">unsubscribe here</a>.</p>
    @endslot
@endcomponent
