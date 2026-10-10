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
    $dispatchDate = trim((string) ($shipment['dispatch_date'] ?? ''));
    $shipmentLabel = $shipments->count() > 1 ? $shipmentNoun.' '.$loop->iteration : $shipmentNoun;
    $dispatchDateLabel = preg_replace('/^Estimated\s+/i', '', $dispatchTiming) ?: $dispatchTiming;
    $summaryParts = [];
    $hasStorePauseTiming = $dispatchTiming !== '' && preg_match('/^(Processing|Available)\s+/i', $dispatchTiming);
    $hasExpiredBackorderEstimate = (bool) ($shipment['contains_backorder'] ?? false)
        && ! (bool) ($shipment['contains_preorder'] ?? false)
        && $dispatchDate !== '';

    if ($hasExpiredBackorderEstimate) {
        try {
            $hasExpiredBackorderEstimate = \Illuminate\Support\Carbon::parse($dispatchDate)->lt(\Illuminate\Support\Carbon::today());
        } catch (\Throwable) {
            $hasExpiredBackorderEstimate = false;
        }
    }

    if ($hasExpiredBackorderEstimate) {
        $summaryParts[] = \App\Models\StoreOrderItem::expiredBackorderTimingMessage($isPickup);
    } elseif ($hasStorePauseTiming) {
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
