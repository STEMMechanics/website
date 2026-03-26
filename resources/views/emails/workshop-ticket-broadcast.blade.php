@component('mail::message')
Hi there,

{{ $messageBody }}

Thanks,<br>
{{ \App\Support\EmailSignatureFormatter::resolve($initiatedByName ?? null) }}
@endcomponent
