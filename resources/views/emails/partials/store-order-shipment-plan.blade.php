@php
    $shipments = collect($shipments ?? [])->filter(fn ($shipment) => is_array($shipment))->values();
    $isPickup = (bool) ($isPickup ?? false);
    $sectionTitle = $isPickup ? 'Collection plan' : 'Delivery plan';
    $shipmentNoun = $isPickup ? 'Collection' : 'Delivery';
@endphp

@if($shipments->isNotEmpty())
@foreach($shipments as $shipment)
@php
    $primary = trim((string) ($shipment['title_primary'] ?? $shipment['title'] ?? ''));
    $primary = preg_replace('/^(Shipment|Collection)(?:\s+\d+)?:\s*/i', '', $primary) ?: $primary;
    $dispatchTiming = trim((string) ($shipment['title_meta'] ?? ''));
    $arrivalTiming = trim((string) ($shipment['delivery_estimate_label'] ?? ''));
    $shipmentLabel = $shipments->count() > 1 ? $shipmentNoun.' '.$loop->iteration : $shipmentNoun;
    $dispatchDateLabel = preg_replace('/^Estimated\s+/i', '', $dispatchTiming) ?: $dispatchTiming;
    $summaryParts = [];
    $hasStorePauseTiming = $dispatchTiming !== '' && preg_match('/^(Processing|Available)\s+/i', $dispatchTiming);

    if ($hasStorePauseTiming) {
        if ($primary !== '') {
            $summaryParts[] = $primary;
        }
        $summaryParts[] = $dispatchTiming;
    } elseif ($isPickup) {
        if ($dispatchDateLabel !== '') {
            $summaryParts[] = 'Available '.$dispatchDateLabel;
        } elseif ($primary !== '') {
            $summaryParts[] = $primary;
        }
    } else {
        if ($dispatchDateLabel !== '' && preg_match('/ships?\s+later|single shipment/i', $primary)) {
            $summaryParts[] = 'Shipping estimated '.$dispatchDateLabel;
        } elseif ($primary !== '') {
            $summaryParts[] = $primary;
        } elseif ($dispatchDateLabel !== '') {
            $summaryParts[] = 'Shipping estimated '.$dispatchDateLabel;
        }
    }

    if ($arrivalTiming !== '' && ! $isPickup) {
        $summaryParts[] = 'Estimated arrival: '.$arrivalTiming.($dispatchTiming !== '' ? ' after dispatch' : '');
    }

    $summaryLine = implode(', ', array_filter($summaryParts));
@endphp
**{{ $shipmentLabel }}**  
@if($summaryLine !== '')
{{ $summaryLine }}  
@endif
<ul>
@foreach(($shipment['items'] ?? []) as $shipmentItem)
    <li>{{ ($shipmentItem['display_title'] ?? 'Item').' x '.(int) ($shipmentItem['quantity'] ?? 0) }}</li>
@endforeach
</ul>

@endforeach
@endif
