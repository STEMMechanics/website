@php
    $locationName = $workshop->getLocationName();
    $scheduleLabel = $workshop->courseScheduleFirstStartLabel();
    $durationLabel = $workshop->workshopDurationLabel();
    $cadenceLabel = $workshop->courseScheduleCadenceLabel();
    $locationSuffix = $cadenceLabel ? ' - '.$cadenceLabel : '';
    $showPrice = ! $workshop->isPriceHiddenFromPublic();
    $priceAmount = $workshop->currentTicketPriceAmount();
    $priceLabel = $priceAmount > 0.0001
        ? '$'.number_format($priceAmount, 2)
        : 'Free';
    $earlyBirdSuffix = $workshop->earlyBirdSummaryLabel() ? ' / Early bird' : '';
    $statusLabel = $workshop->status === 'scheduled' ? ' / Opens Soon' : '';
    $detailLabel = implode(' / ', array_filter([
        $showPrice ? $priceLabel : null,
        $statusLabel !== '' ? ltrim($statusLabel, ' /') : null,
        $showPrice && $earlyBirdSuffix !== '' ? ltrim($earlyBirdSuffix, ' /') : null,
    ]));
@endphp
{{ $scheduleLabel }}@if($durationLabel) ({{ $durationLabel }})@endif - [{{ $workshop->title }}]({{ route('workshop.show', $workshop->slug) }})@if($detailLabel !== '') ({{ $detailLabel }})@endif

{{ $locationName }}{{ $locationSuffix }}
