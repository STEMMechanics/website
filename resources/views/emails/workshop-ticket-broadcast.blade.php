@component('mail::message')
Hi there,

{{ $messageBody }}

Thanks,<br>
{{ !empty($initiatedByName) ? $initiatedByName : config('app.name') }}
@endcomponent
