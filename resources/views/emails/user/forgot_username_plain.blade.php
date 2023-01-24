@if (count($usernames) > 2)
Yo {{ $usernames[0] }}, {{ $usernames[1] }}, or is it {{ $usernames[count($usernames)-1] }}?
@elseif (count($usernames) > 1)
Yo {{ $usernames[0] }} or is it {{ $usernames[1] }}?
@else
Yo {{ $usernames[0] }},
@endif

@if (count($usernames) == 1)
Guess what, your username is {{ $usernames[0] }}.
@else
We have the following usernames registered to this email address:

@foreach($usernames as $username)
- {{ $username }}
@endforeach
@endif

Need help or got feedback? Contact us at https://www.stemmechanics.com.au/contact or touch base on twitter at @stemmechanics

--
Sent by STEMMechanics
https://www.stemmechanics.com.au/
PO Box 36, Edmonton, QLD 4869, Australia