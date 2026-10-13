<?php

namespace App\Support;

use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderUpdate;
use Illuminate\Support\Collection;

class StoreOrderDeliverySummary
{
    /** Include attached items without recalculating the original shipping quote or charges. */
    public static function build(StoreOrder $order): array
    {
        $breakdown = is_array($order->shipping_breakdown_data) ? $order->shipping_breakdown_data : [];
        if ($order->usesPickup()) {
            return $breakdown;
        }

        $order->loadMissing('items.invoiceLine');
        $additionalItems = $order->items->filter(fn (StoreOrderItem $item): bool => ! $item->isDigital()
            && $item->invoiceLine && (int) $item->invoiceLine->invoice_id !== (int) $order->invoice_id);
        if ($additionalItems->isEmpty()) {
            return $breakdown;
        }

        $shipments = array_values(array_filter($breakdown['shipments'] ?? [], 'is_array'));
        if ($shipments === []) {
            $originalItems = $order->items->filter(fn (StoreOrderItem $item): bool => ! $item->isDigital()
                && ! $additionalItems->contains('id', $item->id));
            if ($originalItems->isNotEmpty()) {
                $shipments[] = self::shipment($originalItems, $breakdown);
            }
        }
        $originalShipmentCount = count($shipments);
        $updates = $order->relationLoaded('updates')
            ? $order->updates->where('event_type', StoreOrderUpdate::EVENT_INVOICE_ATTACHED)
            : $order->updates()->where('event_type', StoreOrderUpdate::EVENT_INVOICE_ATTACHED)->get();
        $plans = $updates->mapWithKeys(fn (StoreOrderUpdate $update): array => [
            (int) ($update->payload['invoice_id'] ?? 0) => $update->payload['shipment_plan'] ?? 'same_shipment',
        ]);

        foreach ($additionalItems->groupBy(fn (StoreOrderItem $item): int => (int) $item->invoiceLine->invoice_id) as $invoiceId => $items) {
            if ($plans->get($invoiceId) === 'separate_shipment' || $originalShipmentCount === 0) {
                $shipments[] = self::shipment($items, $breakdown);

                continue;
            }

            $combined = collect(array_slice($shipments, 0, $originalShipmentCount))->search(
                fn (array $shipment): bool => ($shipment['type'] ?? $shipment['key'] ?? '') === 'combined'
            );
            if ($originalShipmentCount === 1 || $combined !== false) {
                self::appendItems($shipments[$combined === false ? 0 : $combined], $items, 'combined');

                continue;
            }

            foreach (['available', 'delayed'] as $type) {
                $rows = $items->filter(fn (StoreOrderItem $item): bool => ($type === 'available'
                    ? $item->available_now_quantity : $item->delayed_quantity) > 0);
                if ($rows->isEmpty()) {
                    continue;
                }

                $target = 0;
                foreach (array_slice($shipments, 0, $originalShipmentCount, true) as $index => $shipment) {
                    if (in_array($shipment['type'] ?? $shipment['key'] ?? '', ['combined', $type === 'available' ? 'immediate' : 'delayed'], true)) {
                        $target = $index;
                        break;
                    }
                }
                self::appendItems($shipments[$target], $rows, $type);
            }
        }

        foreach ($shipments as $index => &$shipment) {
            $shipment['shipment_number'] = $index + 1;
        }
        unset($shipment);
        $breakdown['shipments'] = $shipments;
        $breakdown['shipment_count'] = count($shipments);
        $breakdown['split_shipments'] = count($shipments) > 1;

        return $breakdown;
    }

    /** @param Collection<int, StoreOrderItem> $items */
    private static function shipment(Collection $items, array $breakdown): array
    {
        $shipment = [
            'key' => 'additional',
            'type' => 'combined',
            'items' => [],
            'delivery_estimate_label' => $breakdown['delivery_estimate_label'] ?? null,
        ];
        self::appendItems($shipment, $items, 'combined');

        return $shipment;
    }

    /** @param Collection<int, StoreOrderItem> $items */
    private static function appendItems(array &$shipment, Collection $items, string $type): void
    {
        foreach ($items as $item) {
            $delayed = $type !== 'available' && $item->delayed_quantity > 0;
            $shipment['items'][] = [
                'display_title' => $item->displayTitle(),
                'quantity' => (int) match ($type) {
                    'available' => $item->available_now_quantity,
                    'delayed' => $item->delayed_quantity,
                    default => $item->quantity,
                },
                'delayed_fulfilment_type' => $delayed ? $item->delayed_fulfilment_type : null,
            ];
            $shipment['contains_preorder'] = ($shipment['contains_preorder'] ?? false) || ($delayed && $item->is_preorder);
            $shipment['contains_backorder'] = ($shipment['contains_backorder'] ?? false) || ($delayed && $item->delayed_fulfilment_type === 'backorder');
            if ($delayed && $item->delayed_shipping_estimate
                && $item->delayed_shipping_estimate->toDateString() > ($shipment['dispatch_date'] ?? '')) {
                $shipment['dispatch_date'] = $item->delayed_shipping_estimate->toDateString();
                $shipment['title_meta'] = 'Estimated '.$item->delayed_shipping_estimate->format('F jS Y');
                $shipment['dispatch_label'] = 'Expected to ship from '.$item->delayed_shipping_estimate->format('F jS Y').'.';
            } elseif ($delayed && ! $item->delayed_shipping_estimate) {
                $shipment['title_meta'] = 'Waiting for '.($item->delayed_fulfilment_type === 'preorder' ? 'preorder items' : 'backordered items');
            }
        }
        $shipment['item_count'] = array_sum(array_column($shipment['items'], 'quantity'));
        $shipment['line_count'] = count($shipment['items']);
    }
}
