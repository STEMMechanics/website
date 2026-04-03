@component('mail::message')
# Site error detected

An exception was reported by the site.

- **Type:** {{ $exceptionClass }}
- **Message:** {{ $exceptionMessage }}
@if(!empty($requestMethod ?? ''))
- **Request:** {{ $requestMethod }} {{ $requestUrl ?? '-' }}
@endif
@if(!empty($requestUserEmail ?? '') || !empty($requestUserId ?? ''))
- **User:** {{ $requestUserEmail ?? 'unknown' }}{{ !empty($requestUserId ?? '') ? ' (ID '.$requestUserId.')' : '' }}
@endif

@component('mail::panel')
{{ $exception->getTraceAsString() }}
@endcomponent

Thanks,
{{ config('app.name') }}

@slot('subcopy')
### Why did I get this email?
This alert was sent because the site reported an exception.
@endslot
@endcomponent
