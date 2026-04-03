@php
    $locationName = $workshop->getLocationName();
    $scheduleLabel = $workshop->courseScheduleFirstStartLabel();
    $cadenceLabel = $workshop->courseScheduleCadenceLabel();
    $locationSuffix = $cadenceLabel ? ' - '.$cadenceLabel : '';
    $priceLabel = $workshop->price && is_numeric($workshop->price) && $workshop->price != '0'
        ? '$'.number_format((float) $workshop->price, 2)
        : 'Free';
    $statusLabel = $workshop->status === 'scheduled' ? ' / Opens Soon' : '';
@endphp
{{ $scheduleLabel }} - [{{ $workshop->title }}]({{ route('workshop.show', $workshop->slug) }}) ({{ $priceLabel.$statusLabel }})

{{ $locationName }}{{ $locationSuffix }}

