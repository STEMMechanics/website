@php
    $locationName = $workshop->getLocationName();
    $scheduleLabel = $workshop->courseScheduleFirstStartLabel();
    $durationLabel = $workshop->workshopDurationLabel();
    $cadenceLabel = $workshop->courseScheduleCadenceLabel();
    $locationSuffix = $cadenceLabel ? ' - '.$cadenceLabel : '';
    $priceAmount = $workshop->currentTicketPriceAmount();
    $priceLabel = $priceAmount > 0.0001
        ? '$'.number_format($priceAmount, 2)
        : 'Free';
    $earlyBirdSuffix = $workshop->earlyBirdSummaryLabel() ? ' / Early bird' : '';
    $statusLabel = $workshop->status === 'scheduled' ? ' / Opens Soon' : '';
@endphp
{{ $scheduleLabel }}@if($durationLabel) ({{ $durationLabel }})@endif - [{{ $workshop->title }}]({{ route('workshop.show', $workshop->slug) }}) ({{ $priceLabel.$statusLabel.$earlyBirdSuffix }})

{{ $locationName }}{{ $locationSuffix }}
