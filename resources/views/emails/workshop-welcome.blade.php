@component('mail::message')
# {{ $workshop->title }}

{{ $messageBody }}

**Your schedule** ({{ config('app.timezone') }})
@foreach($workshop->courseScheduleDisplayLines() as $line)
- {{ $line }}
@endforeach

**Location:** {{ $workshop->getLocationName() }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
