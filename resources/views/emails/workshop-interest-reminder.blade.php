@php
    $firstName = trim((string) strtok(trim((string) $interest->name), ' '));
    $timing = $type === 'two_days' ? 'in two days' : 'in about two hours';
@endphp
@component('mail::message')
# {{ $workshop->title }} is coming up

Hi {{ $firstName !== '' ? $firstName : 'there' }},

@if ($isSample)
This is a sample of the {{ $type === 'two_days' ? 'two-day' : 'two-hour' }} workshop-interest reminder. The workshop details below are current, and this sample does not mean the workshop is that close.
@else
You let us know you’re interested in this workshop, so here’s a reminder that it starts {{ $timing }}.
@endif

<p><strong>When:</strong> {{ $workshop->starts_at?->format('l j F Y, g:ia') ?? 'Time to be confirmed' }}<br />
<strong>Where:</strong> {{ $workshop->getLocationName() ?: 'Location to be confirmed' }}</p>

@component('mail::button', ['url' => route('workshop.show', $workshop)])
View workshop details
@endcomponent

@if ($unsubscribeUrl)
If your plans have changed, you can ignore this email. If you’d rather not receive more reminders about this workshop, [click here to stop them]({{ $unsubscribeUrl }}).
@else
If your plans have changed, you can ignore this email.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
