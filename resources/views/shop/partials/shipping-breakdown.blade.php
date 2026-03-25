@php
    $shipments = collect($shipments ?? [])->filter(fn ($shipment) => is_array($shipment))->values();
@endphp

@if($shipments->isNotEmpty())
    <div class="space-y-3">
        @foreach($shipments as $shipment)
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 text-sm text-gray-700">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $shipments->count() > 1 ? 'Delivery '.$loop->iteration : 'Delivery' }}</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ trim((string) ($shipment['delivery_estimate_label'] ?? '')) ?: trim((string) ($shipment['title_meta'] ?? '')) ?: trim((string) ($shipment['dispatch_label'] ?? '')) ?: 'Timing to be confirmed' }}
                    </div>
                    @if(trim((string) ($shipment['delivery_estimate_label'] ?? '')) !== '' && trim((string) ($shipment['title_meta'] ?? '')) !== '')
                        <div class="mt-1 max-w-sm text-xs text-gray-500">{{ $shipment['title_meta'] }}</div>
                    @elseif(trim((string) ($shipment['delivery_estimate_label'] ?? '')) === '' && trim((string) ($shipment['title_primary'] ?? '')) !== '')
                        <div class="mt-1 max-w-sm text-xs text-gray-500">{{ $shipment['title_primary'] }}</div>
                    @endif
                </div>

                @if(!empty($shipment['items']) && is_array($shipment['items']))
                    <ul class="mt-3 list-disc space-y-2 border-t border-gray-200 pl-5 pt-3">
                        @foreach($shipment['items'] as $shipmentItem)
                            <li class="text-xs text-gray-600">
                                <div>
                                    <div class="font-medium text-gray-800">{{ ($shipmentItem['display_title'] ?? 'Item').' x '.(int) ($shipmentItem['quantity'] ?? 0) }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
@endif
