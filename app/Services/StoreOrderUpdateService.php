<?php

namespace App\Services;

use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemCancellation;
use App\Models\StoreOrderItemTracking;
use App\Models\StoreOrderUpdate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StoreOrderUpdateService
{
    public function recordBackorderAllocation(StoreOrder $order, StoreOrderItem $item, int $allocatedQuantity, int $remainingDelayedQuantity): ?StoreOrderUpdate
    {
        if ($allocatedQuantity <= 0) {
            return null;
        }

        return $this->createUpdate(
            $order,
            $item,
            StoreOrderUpdate::EVENT_BACKORDER_ALLOCATED,
            [
                'item_title' => $item->displayTitle(),
                'allocated_quantity' => $allocatedQuantity,
                'remaining_delayed_quantity' => max(0, $remainingDelayedQuantity),
            ],
        );
    }

    public function recordTrackingAdded(StoreOrder $order, StoreOrderItem $item, StoreOrderItemTracking $tracking): ?StoreOrderUpdate
    {
        return $this->createUpdate(
            $order,
            $item,
            StoreOrderUpdate::EVENT_TRACKING_ADDED,
            [
                'item_title' => $item->displayTitle(),
                'quantity' => max(0, (int) $tracking->quantity),
                'shipment_type' => (string) $tracking->shipment_type,
                'parcel_number' => max(0, (int) ($tracking->parcel_number ?? 0)) ?: null,
                'carrier' => trim((string) ($tracking->carrier ?? '')) ?: null,
                'tracking_number' => trim((string) ($tracking->tracking_number ?? '')) ?: null,
                'tracking_url' => trim((string) ($tracking->tracking_url ?? '')) ?: null,
                'notes' => trim((string) ($tracking->notes ?? '')) ?: null,
                'dispatched_at' => $tracking->dispatched_at?->toIso8601String(),
            ],
        );
    }

    public function recordItemCancellation(StoreOrder $order, StoreOrderItem $item, StoreOrderItemCancellation $cancellation): ?StoreOrderUpdate
    {
        return $this->createUpdate(
            $order,
            $item,
            StoreOrderUpdate::EVENT_ITEM_CANCELLED,
            [
                'item_title' => $item->displayTitle(),
                'available_quantity' => max(0, (int) $cancellation->available_quantity),
                'delayed_quantity' => max(0, (int) $cancellation->delayed_quantity),
                'reason' => trim((string) $cancellation->reason),
            ],
        );
    }

    public function recordStatusChange(StoreOrder $order, string $fromStatus, string $toStatus): ?StoreOrderUpdate
    {
        if ($fromStatus === $toStatus) {
            return null;
        }

        return $this->createUpdate(
            $order,
            null,
            StoreOrderUpdate::EVENT_STATUS_CHANGED,
            [
                'from_status' => $fromStatus,
                'from_status_label' => $this->statusLabel($fromStatus),
                'to_status' => $toStatus,
                'to_status_label' => $this->statusLabel($toStatus),
            ],
        );
    }

    public function recordPublicNoteUpdate(StoreOrder $order, string $publicNote): ?StoreOrderUpdate
    {
        $publicNote = trim($publicNote);
        if ($publicNote === '') {
            return null;
        }

        return $this->createUpdate(
            $order,
            null,
            StoreOrderUpdate::EVENT_PUBLIC_NOTE_UPDATED,
            [
                'public_note' => $publicNote,
            ],
        );
    }

    /**
     * @return array{
     *     event_ids: array<int, int>,
     *     orders: array<int, array{
     *         order_number: string,
     *         status_label: string,
     *         notification_type: string,
     *         order_url?: string,
     *         admin_url?: string,
     *         customer_name?: string,
     *         customer_email?: string,
     *         item_sections?: array<int, array{
     *             heading: string,
     *             detail?: ?string,
     *             items: array<int, array{title: string, quantity: int, detail: ?string}>
     *         }>,
     *         updates: array<int, array{type: string, time: ?string, summary: string, detail: ?string}>
     *     }>
     * }|null
     */
    public function payloadForEvents(iterable $eventIds, bool $forAdmin): ?array
    {
        $ids = collect($eventIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty() || ! Schema::hasTable('store_order_updates')) {
            return null;
        }

        $query = StoreOrderUpdate::query()
            ->with(['order.items.trackingEntries', 'orderItem'])
            ->whereIn('id', $ids)
            ->orderBy('occurred_at')
            ->orderBy('id');

        if (! $forAdmin) {
            $query->where('customer_visible', true);
        }

        $updates = $query->get();
        if ($updates->isEmpty()) {
            return null;
        }

        return [
            'event_ids' => $updates->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'orders' => $this->groupUpdatesByOrder($updates, $forAdmin),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pendingCustomerDigests(): Collection
    {
        $updates = StoreOrderUpdate::query()
            ->with(['order.items.trackingEntries', 'orderItem'])
            ->where('customer_visible', true)
            ->whereNull('customer_digest_queued_at')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->filter(function (StoreOrderUpdate $update): bool {
                $email = strtolower(trim((string) ($update->order->billing_email ?? '')));

                return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
            })
            ->values();

        $payloads = $updates
            ->groupBy(fn (StoreOrderUpdate $update) => strtolower(trim((string) ($update->order->billing_email ?? ''))))
            ->map(function (Collection $recipientUpdates, string $recipientEmail): array {
                /** @var StoreOrder|null $firstOrder */
                $firstOrder = $recipientUpdates->first()?->order;

                return [
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => trim((string) ($firstOrder->billing_name ?? '')) ?: 'there',
                    'event_ids' => $recipientUpdates->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'orders' => $this->groupUpdatesByOrder($recipientUpdates, false),
                ];
            })
            ->filter(fn (array $payload): bool => $payload['orders'] !== [])
            ->values();

        /** @var Collection<int, array<string, mixed>> $payloads */
        return $payloads;
    }

    /**
     * @return array{
     *     event_ids: array<int, int>,
     *     orders: array<int, array{
     *         order_number: string,
     *         status_label: string,
     *         notification_type: string,
     *         admin_url: string,
     *         customer_name: string,
     *         customer_email: string,
     *         item_sections?: array<int, array{
     *             heading: string,
     *             detail?: ?string,
     *             items: array<int, array{title: string, quantity: int, detail: ?string}>
     *         }>,
     *         updates: array<int, array{type: string, time: ?string, summary: string, detail: ?string}>
     *     }>
     * }|null
     */
    public function pendingAdminDigest(): ?array
    {
        $updates = StoreOrderUpdate::query()
            ->with(['order.items.trackingEntries', 'orderItem'])
            ->whereNull('admin_digest_queued_at')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        if ($updates->isEmpty()) {
            return null;
        }

        return [
            'event_ids' => $updates->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'orders' => $this->groupUpdatesByOrder($updates, true),
        ];
    }

    public function markCustomerDigestQueued(iterable $eventIds): void
    {
        $ids = collect($eventIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return;
        }

        StoreOrderUpdate::query()
            ->whereIn('id', $ids)
            ->update(['customer_digest_queued_at' => now()]);
    }

    public function markAdminDigestQueued(iterable $eventIds): void
    {
        $ids = collect($eventIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return;
        }

        StoreOrderUpdate::query()
            ->whereIn('id', $ids)
            ->update(['admin_digest_queued_at' => now()]);
    }

    /**
     * @return array<int, string>
     */
    public function adminRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function createUpdate(
        StoreOrder $order,
        ?StoreOrderItem $item,
        string $eventType,
        array $payload,
        bool $customerVisible = true
    ): ?StoreOrderUpdate {
        if (! Schema::hasTable('store_order_updates')) {
            return null;
        }

        return StoreOrderUpdate::query()->create([
            'store_order_id' => $order->id,
            'store_order_item_id' => $item?->id,
            'event_type' => $eventType,
            'customer_visible' => $customerVisible,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array<int, array{
     *     order_number: string,
     *     status_label: string,
     *     notification_type: string,
     *     order_url?: string,
     *     admin_url?: string,
     *     customer_name?: string,
     *     customer_email?: string,
     *     item_sections?: array<int, array{
     *         heading: string,
     *         items: array<int, array{title: string, quantity: int, detail: ?string}>
     *     }>,
     *     updates: array<int, array{type: string, time: ?string, summary: string, detail: ?string}>
     * }>
     */
    private function groupUpdatesByOrder(Collection $updates, bool $forAdmin): array
    {
        return $updates
            ->groupBy('store_order_id')
            ->map(function (Collection $orderUpdates): ?array {
                /** @var StoreOrder|null $order */
                $order = $orderUpdates->first()?->order;
                if (! $order instanceof StoreOrder) {
                    return null;
                }

                $notificationType = $this->notificationTypeForOrderUpdates($orderUpdates, $order);

                return [
                    'order_number' => (string) $order->order_number,
                    'status_label' => $order->statusLabel(),
                    'notification_type' => $notificationType,
                    'order_url' => route('shop.order.tracking', [
                        'accessToken' => $order->access_token,
                    ]),
                    'admin_url' => route('admin.shop.order.edit', $order),
                    'customer_name' => trim((string) ($order->billing_name ?? '')) ?: 'Guest customer',
                    'customer_email' => trim((string) ($order->billing_email ?? '')) ?: '-',
                    'item_sections' => $this->customerItemSections($order, $orderUpdates, $notificationType),
                    'updates' => $orderUpdates
                        ->map(fn (StoreOrderUpdate $update) => $this->formatUpdate($update))
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->map(function (array $section) use ($forAdmin): array {
                if ($forAdmin) {
                    unset($section['order_url'], $section['item_sections']);

                    return $section;
                }

                unset($section['admin_url'], $section['customer_name'], $section['customer_email']);

                return $section;
            })
            ->all();
    }

    /**
     * @return array{type: string, time: ?string, summary: string, detail: ?string}
     */
    private function formatUpdate(StoreOrderUpdate $update): array
    {
        $payload = is_array($update->payload) ? $update->payload : [];
        $itemTitle = trim((string) ($payload['item_title'] ?? ''));

        return match ((string) $update->event_type) {
            StoreOrderUpdate::EVENT_BACKORDER_ALLOCATED => [
                'type' => StoreOrderUpdate::EVENT_BACKORDER_ALLOCATED,
                'time' => $update->occurredAtLabel(),
                'summary' => $this->quantityLabel((int) ($payload['allocated_quantity'] ?? 0)).' of '
                    .($itemTitle !== '' ? $itemTitle : 'an item')
                    .' was allocated from new stock.',
                'detail' => max(0, (int) ($payload['remaining_delayed_quantity'] ?? 0)) > 0
                    ? max(0, (int) ($payload['remaining_delayed_quantity'] ?? 0)).' unit'.(max(0, (int) ($payload['remaining_delayed_quantity'] ?? 0)) === 1 ? '' : 's').' still waiting on backorder.'
                    : 'This item no longer has any backordered quantity waiting to be allocated.',
            ],
            StoreOrderUpdate::EVENT_TRACKING_ADDED => [
                'type' => StoreOrderUpdate::EVENT_TRACKING_ADDED,
                'time' => $update->occurredAtLabel(),
                'summary' => $this->quantityLabel((int) ($payload['quantity'] ?? 0)).' of '
                    .($itemTitle !== '' ? $itemTitle : 'an item')
                    .' shipped.',
                'detail' => $this->trackingDetail($payload),
            ],
            StoreOrderUpdate::EVENT_ITEM_CANCELLED => [
                'type' => StoreOrderUpdate::EVENT_ITEM_CANCELLED,
                'time' => $update->occurredAtLabel(),
                'summary' => $this->quantityLabel(
                    max(0, (int) ($payload['available_quantity'] ?? 0)) + max(0, (int) ($payload['delayed_quantity'] ?? 0))
                ).' of '.($itemTitle !== '' ? $itemTitle : 'an item').' was cancelled from fulfilment.',
                'detail' => trim((string) ($payload['reason'] ?? '')) ?: null,
            ],
            StoreOrderUpdate::EVENT_STATUS_CHANGED => [
                'type' => StoreOrderUpdate::EVENT_STATUS_CHANGED,
                'time' => $update->occurredAtLabel(),
                'summary' => $this->statusChangeSummary($payload),
                'detail' => $this->statusChangeDetail($payload),
            ],
            StoreOrderUpdate::EVENT_PUBLIC_NOTE_UPDATED => [
                'type' => StoreOrderUpdate::EVENT_PUBLIC_NOTE_UPDATED,
                'time' => $update->occurredAtLabel(),
                'summary' => 'A new public note was added to the order.',
                'detail' => trim((string) ($payload['public_note'] ?? '')) ?: null,
            ],
            default => [
                'type' => (string) $update->event_type,
                'time' => $update->occurredAtLabel(),
                'summary' => 'Order updated.',
                'detail' => null,
            ],
        };
    }

    private function trackingDetail(array $payload): ?string
    {
        $parts = collect();

        $carrier = trim((string) ($payload['carrier'] ?? ''));
        if ($carrier !== '') {
            $parts->push($carrier);
        }

        $trackingNumber = trim((string) ($payload['tracking_number'] ?? ''));
        $trackingUrl = trim((string) ($payload['tracking_url'] ?? ''));
        if ($trackingNumber !== '') {
            $parts->push('Tracking '.$trackingNumber);
        } elseif ($trackingUrl !== '') {
            $parts->push('Tracking link');
        }

        $parcelNumber = max(0, (int) ($payload['parcel_number'] ?? 0));
        if ($parcelNumber > 0 && $trackingNumber === '' && $trackingUrl === '') {
            $parts->push('Parcel #'.$parcelNumber);
        }

        $notes = trim((string) ($payload['notes'] ?? ''));
        if ($notes !== '') {
            $parts->push($notes);
        }

        $detail = $parts->implode(' | ');

        return $detail !== '' ? $detail : null;
    }

    private function statusChangeSummary(array $payload): string
    {
        $toStatus = trim((string) ($payload['to_status'] ?? ''));
        $toStatusLabel = trim((string) ($payload['to_status_label'] ?? $this->statusLabel($toStatus)));

        return match ($toStatus) {
            StoreOrder::STATUS_READY_FOR_PICKUP => 'The order is now ready for pickup.',
            StoreOrder::STATUS_PARTIALLY_SHIPPED => 'Part of the order has now shipped.',
            StoreOrder::STATUS_SHIPPED => 'The order has now shipped.',
            StoreOrder::STATUS_COLLECTED => 'The order has now been collected.',
            StoreOrder::STATUS_FULFILLED => 'The order is now complete.',
            StoreOrder::STATUS_CANCELLED => 'The order has been cancelled.',
            StoreOrder::STATUS_PROCESSING => 'The order is now being prepared.',
            default => 'Order status changed to '.$toStatusLabel.'.',
        };
    }

    private function statusChangeDetail(array $payload): ?string
    {
        $fromStatusLabel = trim((string) ($payload['from_status_label'] ?? ''));

        return $fromStatusLabel !== ''
            ? 'Previously: '.$fromStatusLabel.'.'
            : null;
    }

    private function quantityLabel(int $quantity): string
    {
        $quantity = max(1, $quantity);

        return $quantity.' unit'.($quantity === 1 ? '' : 's');
    }

    private function notificationTypeForOrderUpdates(Collection $updates, StoreOrder $order): string
    {
        $latestStatus = $updates
            ->filter(fn (StoreOrderUpdate $update): bool => (string) $update->event_type === StoreOrderUpdate::EVENT_STATUS_CHANGED)
            ->map(function (StoreOrderUpdate $update): string {
                $payload = is_array($update->payload) ? $update->payload : [];

                return trim((string) ($payload['to_status'] ?? ''));
            })
            ->filter()
            ->last();

        if ($latestStatus !== null) {
            return match ((string) $latestStatus) {
                StoreOrder::STATUS_READY_FOR_PICKUP => 'ready_for_pickup',
                StoreOrder::STATUS_PARTIALLY_SHIPPED => 'partially_shipped',
                StoreOrder::STATUS_SHIPPED => 'shipped',
                StoreOrder::STATUS_COLLECTED => 'collected',
                StoreOrder::STATUS_FULFILLED => 'fulfilled',
                StoreOrder::STATUS_CANCELLED => 'cancelled',
                StoreOrder::STATUS_PROCESSING => 'preparing',
                default => 'updated',
            };
        }

        if ($updates->contains(fn (StoreOrderUpdate $update): bool => (string) $update->event_type === StoreOrderUpdate::EVENT_TRACKING_ADDED)) {
            return match ((string) $order->status) {
                StoreOrder::STATUS_PARTIALLY_SHIPPED => 'partially_shipped',
                StoreOrder::STATUS_SHIPPED => 'shipped',
                default => 'shipped',
            };
        }

        if ($updates->contains(fn (StoreOrderUpdate $update): bool => (string) $update->event_type === StoreOrderUpdate::EVENT_ITEM_CANCELLED)) {
            return (string) $order->status === StoreOrder::STATUS_CANCELLED
                ? 'cancelled'
                : 'items_cancelled';
        }

        return 'updated';
    }

    /**
     * @return array<int, array{
     *     heading: string,
     *     items: array<int, array{title: string, quantity: int, detail: ?string}>
     * }>
     */
    private function customerItemSections(StoreOrder $order, Collection $orderUpdates, string $notificationType): array
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->with('trackingEntries')->get();

        if ($items->isEmpty()) {
            return [];
        }

        $sections = [];
        $hasTrackingUpdates = $orderUpdates->contains(
            fn (StoreOrderUpdate $update): bool => (string) $update->event_type === StoreOrderUpdate::EVENT_TRACKING_ADDED
        );
        $hasCancellationUpdates = $orderUpdates->contains(
            fn (StoreOrderUpdate $update): bool => (string) $update->event_type === StoreOrderUpdate::EVENT_ITEM_CANCELLED
        );

        if ($hasTrackingUpdates) {
            $remainingItems = $this->sectionRowsFromItems(
                $items,
                fn (StoreOrderItem $item): int => $item->remainingFulfillableQuantity(),
                fn (StoreOrderItem $item): ?string => $this->remainingOrderItemDetail($order, $item),
            );
            $sections = array_merge(
                $sections,
                $this->trackingItemSectionsFromUpdates($items, $orderUpdates, $order, $notificationType, $remainingItems === [])
            );

            if ($remainingItems !== []) {
                $sections[] = [
                    'heading' => 'To be shipped',
                    'items' => $remainingItems,
                ];
            }
        } elseif ($notificationType === 'ready_for_pickup') {
            $readyItems = $this->sectionRowsFromItems(
                $items,
                fn (StoreOrderItem $item): int => $item->remainingAvailableQuantity(),
                fn (StoreOrderItem $item): ?string => null,
            );
            if ($readyItems !== []) {
                $sections[] = [
                    'heading' => 'Ready for pickup now',
                    'items' => $readyItems,
                ];
            }

            $expectedItems = $this->sectionRowsFromItems(
                $items,
                fn (StoreOrderItem $item): int => $item->remainingDelayedQuantity(),
                fn (StoreOrderItem $item): ?string => $this->expectedLaterDetail($order, $item),
            );
            if ($expectedItems !== []) {
                $sections[] = [
                    'heading' => 'Still expected later',
                    'items' => $expectedItems,
                ];
            }
        } elseif ($hasCancellationUpdates || $notificationType === 'items_cancelled' || $notificationType === 'cancelled') {
            $cancelledItems = $this->affectedItemRowsFromUpdates(
                $items,
                $orderUpdates,
                StoreOrderUpdate::EVENT_ITEM_CANCELLED,
                fn (array $payload): int => max(0, (int) ($payload['available_quantity'] ?? 0)) + max(0, (int) ($payload['delayed_quantity'] ?? 0)),
                fn (array $payload): ?string => trim((string) ($payload['reason'] ?? '')) ?: null,
            );
            if ($cancelledItems === [] && $notificationType === 'cancelled') {
                $cancelledItems = $this->sectionRowsFromItems(
                    $items,
                    fn (StoreOrderItem $item): int => $item->cancelledQuantity(),
                    fn (StoreOrderItem $item): ?string => null,
                );
            }
            if ($cancelledItems !== []) {
                $sections[] = [
                    'heading' => 'Cancelled from this order',
                    'items' => $cancelledItems,
                ];
            }

            $remainingItems = $this->sectionRowsFromItems(
                $items,
                fn (StoreOrderItem $item): int => $item->remainingFulfillableQuantity(),
                fn (StoreOrderItem $item): ?string => $this->remainingOrderItemDetail($order, $item),
            );
            if ($remainingItems !== []) {
                $sections[] = [
                    'heading' => 'Still active on this order',
                    'items' => $remainingItems,
                ];
            }
        }

        if ($sections === []) {
            $allItems = $this->sectionRowsFromItems(
                $items,
                fn (StoreOrderItem $item): int => max(0, (int) $item->quantity),
                fn (StoreOrderItem $item): ?string => $this->orderItemOverviewDetail($order, $item),
            );
            if ($allItems !== []) {
                $sections[] = [
                    'heading' => 'Items in this order',
                    'items' => $allItems,
                ];
            }
        }

        return $sections;
    }

    /**
     * @param  Collection<int, StoreOrderItem>  $items
     * @param  callable(StoreOrderItem): int  $quantityResolver
     * @param  callable(StoreOrderItem): ?string  $detailResolver
     * @return array<int, array{title: string, quantity: int, detail: ?string}>
     */
    private function sectionRowsFromItems(Collection $items, callable $quantityResolver, callable $detailResolver): array
    {
        return $items
            ->map(function (StoreOrderItem $item) use ($quantityResolver, $detailResolver): ?array {
                $quantity = max(0, (int) $quantityResolver($item));
                if ($quantity <= 0) {
                    return null;
                }

                $detail = trim((string) ($detailResolver($item) ?? ''));

                return [
                    'title' => $item->displayTitle(),
                    'quantity' => $quantity,
                    'detail' => $detail !== '' ? $detail : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StoreOrderItem>  $items
     * @param  callable(array): int  $quantityResolver
     * @param  callable(array): ?string  $detailResolver
     * @return array<int, array{title: string, quantity: int, detail: ?string}>
     */
    private function affectedItemRowsFromUpdates(
        Collection $items,
        Collection $orderUpdates,
        string $eventType,
        callable $quantityResolver,
        callable $detailResolver
    ): array {
        $positionMap = $items
            ->values()
            ->mapWithKeys(fn (StoreOrderItem $item, int $index): array => [$item->id => $index])
            ->all();

        $grouped = $orderUpdates
            ->filter(fn (StoreOrderUpdate $update): bool => (string) $update->event_type === $eventType)
            ->values()
            ->reduce(function (array $carry, StoreOrderUpdate $update) use ($positionMap, $quantityResolver, $detailResolver): array {
                $payload = is_array($update->payload) ? $update->payload : [];
                $item = $update->orderItem;
                $fallbackTitle = trim((string) ($payload['item_title'] ?? '')) ?: 'Item';
                $key = $item instanceof StoreOrderItem ? 'item:'.$item->id : 'title:'.$fallbackTitle;
                $quantity = max(0, (int) $quantityResolver($payload));
                if ($quantity <= 0) {
                    return $carry;
                }

                if (! isset($carry[$key])) {
                    $carry[$key] = [
                        'title' => $item instanceof StoreOrderItem ? $item->displayTitle() : $fallbackTitle,
                        'quantity' => 0,
                        'details' => [],
                        'position' => $item instanceof StoreOrderItem ? ($positionMap[$item->id] ?? PHP_INT_MAX) : PHP_INT_MAX,
                        'fallback_order' => count($carry),
                    ];
                }

                $carry[$key]['quantity'] += $quantity;

                $detail = trim((string) ($detailResolver($payload) ?? ''));
                if ($detail !== '') {
                    $carry[$key]['details'][$detail] = true;
                }

                return $carry;
            }, []);

        uasort($grouped, function (array $left, array $right): int {
            if ((int) $left['position'] === (int) $right['position']) {
                return (int) $left['fallback_order'] <=> (int) $right['fallback_order'];
            }

            return (int) $left['position'] <=> (int) $right['position'];
        });

        return collect($grouped)
            ->map(function (array $row): array {
                $details = array_keys((array) ($row['details'] ?? []));
                $detail = trim(implode(' | ', $details));

                return [
                    'title' => (string) $row['title'],
                    'quantity' => max(0, (int) $row['quantity']),
                    'detail' => $detail !== '' ? $detail : null,
                ];
            })
            ->values()
            ->all();
    }

    private function orderItemOverviewDetail(StoreOrder $order, StoreOrderItem $item): ?string
    {
        $available = $item->remainingAvailableQuantity();
        $delayed = $item->remainingDelayedQuantity();

        if ($item->is_preorder && $delayed > 0) {
            return $this->expectedLaterPhrase($order, $item->preorderShippingEstimateLabel('F jS Y'));
        }

        if ($delayed > 0) {
            $timing = $item->delayedShippingEstimateLabel('F jS Y');

            if ($available > 0) {
                return $available.' ready now, '.$delayed.' still expected'
                    .($timing ? ' ('.$this->expectedLaterPhrase($order, $timing).')' : '');
            }

            return $this->expectedLaterPhrase($order, $timing);
        }

        return null;
    }

    private function expectedLaterDetail(StoreOrder $order, StoreOrderItem $item): ?string
    {
        if ($item->is_preorder) {
            return $this->expectedLaterPhrase($order, $item->preorderShippingEstimateLabel('F jS Y'));
        }

        if ($item->shipsLater()) {
            return $this->expectedLaterPhrase($order, $item->delayedShippingEstimateLabel('F jS Y'));
        }

        return null;
    }

    private function remainingOrderItemDetail(StoreOrder $order, StoreOrderItem $item): ?string
    {
        $available = $item->remainingAvailableQuantity();
        $delayed = $item->remainingDelayedQuantity();

        if ($available > 0 && $delayed > 0) {
            $timing = $item->delayedShippingEstimateLabel('F jS Y');

            return $available.' being prepared, '.$delayed.' expected'
                .($timing ? ' ('.$this->expectedLaterPhrase($order, $timing).')' : '');
        }

        if ($available > 0) {
            return 'Being prepared.';
        }

        if ($delayed > 0) {
            return $this->expectedLaterDetail($order, $item);
        }

        return null;
    }

    /**
     * @param  Collection<int, StoreOrderItem>  $items
     * @return array<int, array{
     *     heading: string,
     *     detail?: ?string,
     *     detail_parts?: array<int, array{prefix:?string, text:string, url:?string}>,
     *     items: array<int, array{title: string, quantity: int, detail: ?string}>
     * }>
     */
    private function trackingItemSectionsFromUpdates(
        Collection $items,
        Collection $orderUpdates,
        StoreOrder $order,
        string $notificationType,
        bool $allRemainingFulfilmentResolved
    ): array {
        $positionMap = $items
            ->values()
            ->mapWithKeys(fn (StoreOrderItem $item, int $index): array => [$item->id => $index])
            ->all();

        $grouped = $orderUpdates
            ->filter(fn (StoreOrderUpdate $update): bool => (string) $update->event_type === StoreOrderUpdate::EVENT_TRACKING_ADDED)
            ->values()
            ->reduce(function (array $carry, StoreOrderUpdate $update) use ($positionMap, $order): array {
                $payload = is_array($update->payload) ? $update->payload : [];
                $item = $update->orderItem;
                $fallbackTitle = trim((string) ($payload['item_title'] ?? '')) ?: 'Item';
                $groupKey = $this->trackingPayloadGroupKey($payload, (int) $update->id);
                $position = $item instanceof StoreOrderItem ? ($positionMap[$item->id] ?? PHP_INT_MAX) : PHP_INT_MAX;
                $sortTimestamp = $this->trackingPayloadSortTimestamp($payload, $update);

                if (! isset($carry[$groupKey])) {
                    $summaryMeta = $this->shipmentSummaryMeta($order, $payload);
                    $carry[$groupKey] = [
                        'group_key' => $groupKey,
                        'sort_timestamp' => $sortTimestamp,
                        'position' => $position,
                        'fallback_order' => count($carry),
                        'detail' => $summaryMeta['detail'],
                        'detail_parts' => $summaryMeta['detail_parts'],
                        'parcel_number' => $summaryMeta['parcel_number'],
                        'items' => [],
                    ];
                } else {
                    $carry[$groupKey]['sort_timestamp'] = min((int) $carry[$groupKey]['sort_timestamp'], $sortTimestamp);
                    $carry[$groupKey]['position'] = min((int) $carry[$groupKey]['position'], $position);
                }

                $itemKey = $item instanceof StoreOrderItem ? 'item:'.$item->id : 'title:'.$fallbackTitle;
                if (! isset($carry[$groupKey]['items'][$itemKey])) {
                    $carry[$groupKey]['items'][$itemKey] = [
                        'title' => $item instanceof StoreOrderItem ? $item->displayTitle() : $fallbackTitle,
                        'quantity' => 0,
                        'detail' => null,
                        'position' => $position,
                        'fallback_order' => count($carry[$groupKey]['items']),
                    ];
                }

                $carry[$groupKey]['items'][$itemKey]['quantity'] += max(0, (int) ($payload['quantity'] ?? 0));

                return $carry;
            }, []);

        uasort($grouped, function (array $left, array $right): int {
            $timestampComparison = (int) $left['sort_timestamp'] <=> (int) $right['sort_timestamp'];
            if ($timestampComparison !== 0) {
                return $timestampComparison;
            }

            $positionComparison = (int) $left['position'] <=> (int) $right['position'];
            if ($positionComparison !== 0) {
                return $positionComparison;
            }

            return (int) $left['fallback_order'] <=> (int) $right['fallback_order'];
        });

        $deliveryNoun = $order->usesPickup() ? 'Collection' : 'Delivery';
        $groupCount = count($grouped);

        return collect($grouped)
            ->values()
            ->map(function (array $group, int $index) use ($deliveryNoun, $notificationType, $allRemainingFulfilmentResolved, $groupCount): array {
                $items = collect((array) ($group['items'] ?? []))
                    ->sort(function (array $left, array $right): int {
                        if ((int) $left['position'] === (int) $right['position']) {
                            return (int) $left['fallback_order'] <=> (int) $right['fallback_order'];
                        }

                        return (int) $left['position'] <=> (int) $right['position'];
                    })
                    ->map(fn (array $row): array => [
                        'title' => (string) $row['title'],
                        'quantity' => max(0, (int) $row['quantity']),
                        'detail' => null,
                    ])
                    ->values()
                    ->all();

                $parcelNumber = max(0, (int) ($group['parcel_number'] ?? 0));
                $isParcelGroup = str_starts_with((string) ($group['group_key'] ?? ''), 'parcel:');
                $heading = $isParcelGroup && $parcelNumber > 0
                    ? 'Parcel #'.$parcelNumber
                    : ($groupCount === 1
                        ? ($notificationType === 'shipped' && $allRemainingFulfilmentResolved ? 'All items shipped' : 'Shipped now')
                        : $deliveryNoun.' '.($index + 1));
                $detail = trim((string) ($group['detail'] ?? ''));

                return [
                    'heading' => $heading,
                    'detail' => $detail !== '' ? $detail : null,
                    'detail_parts' => is_array($group['detail_parts'] ?? null) ? array_values($group['detail_parts']) : [],
                    'items' => $items,
                ];
            })
            ->all();
    }

    private function trackingPayloadGroupKey(array $payload, int $updateId): string
    {
        $carrier = Str::lower(trim((string) ($payload['carrier'] ?? '')));
        $trackingNumber = Str::lower(trim((string) ($payload['tracking_number'] ?? '')));
        if ($trackingNumber !== '') {
            return 'tracking:'.$carrier.'|'.$trackingNumber;
        }

        $trackingUrl = Str::lower(trim((string) ($payload['tracking_url'] ?? '')));
        if ($trackingUrl !== '') {
            return 'url:'.$carrier.'|'.$trackingUrl;
        }

        $parcelNumber = max(0, (int) ($payload['parcel_number'] ?? 0));
        if ($parcelNumber > 0) {
            return 'parcel:'.$parcelNumber;
        }

        return 'manual:'.max(0, $updateId);
    }

    private function trackingPayloadSortTimestamp(array $payload, StoreOrderUpdate $update): int
    {
        $dispatchedAt = trim((string) ($payload['dispatched_at'] ?? ''));
        if ($dispatchedAt !== '') {
            return Carbon::parse($dispatchedAt)->timestamp;
        }

        return $update->occurred_at instanceof Carbon
            ? $update->occurred_at->timestamp
            : 0;
    }

    /**
     * @return array{
     *     detail:?string,
     *     detail_parts: array<int, array{prefix:?string, text:string, url:?string}>,
     *     parcel_number:?int
     * }
     */
    private function shipmentSummaryMeta(StoreOrder $order, array $payload): array
    {
        $parts = collect();
        $detailParts = [];

        $dispatchedLabel = $this->shipmentDispatchedLabel($payload);
        if ($dispatchedLabel !== null) {
            $parts->push($dispatchedLabel);
            $detailParts[] = [
                'prefix' => null,
                'text' => $dispatchedLabel,
                'url' => null,
            ];
        }

        $arrival = trim((string) $this->deliveryEstimateLabel($order));
        if ($arrival !== '' && ! $order->usesPickup()) {
            $label = 'Estimated arrival: '.$arrival;
            $parts->push($label);
            $detailParts[] = [
                'prefix' => null,
                'text' => $label,
                'url' => null,
            ];
        }

        $carrier = trim((string) ($payload['carrier'] ?? ''));
        if ($carrier !== '') {
            $parts->push($carrier);
            $detailParts[] = [
                'prefix' => null,
                'text' => $carrier,
                'url' => null,
            ];
        }

        $trackingNumber = trim((string) ($payload['tracking_number'] ?? ''));
        $trackingUrl = trim((string) ($payload['tracking_url'] ?? ''));
        if ($trackingNumber !== '') {
            $parts->push('Tracking '.$trackingNumber);
            $detailParts[] = [
                'prefix' => 'Tracking ',
                'text' => $trackingNumber,
                'url' => $trackingUrl !== '' ? $trackingUrl : null,
            ];
        } elseif ($trackingUrl !== '') {
            $parts->push('Tracking link');
            $detailParts[] = [
                'prefix' => null,
                'text' => 'Tracking link',
                'url' => $trackingUrl,
            ];
        }

        $parcelNumber = max(0, (int) ($payload['parcel_number'] ?? 0));
        if ($parcelNumber > 0 && $trackingNumber === '' && $trackingUrl === '') {
            $parts->push('Parcel #'.$parcelNumber);
            $detailParts[] = [
                'prefix' => null,
                'text' => 'Parcel #'.$parcelNumber,
                'url' => null,
            ];
        }

        $detail = $parts->implode(' | ');

        return [
            'detail' => $detail !== '' ? $detail : null,
            'detail_parts' => $detailParts,
            'parcel_number' => $parcelNumber > 0 ? $parcelNumber : null,
        ];
    }

    private function shipmentDispatchedLabel(array $payload): ?string
    {
        $dispatchedAt = trim((string) ($payload['dispatched_at'] ?? ''));
        if ($dispatchedAt === '') {
            return null;
        }

        try {
            return 'Shipped '.Carbon::parse($dispatchedAt)->format('F jS Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function expectedLaterPhrase(StoreOrder $order, ?string $dateLabel): string
    {
        $resolvedDate = trim((string) $dateLabel);

        if ($order->usesPickup()) {
            return 'Expected availability '.($resolvedDate !== '' ? $resolvedDate : 'to be confirmed');
        }

        return 'Shipping estimated '.($resolvedDate !== '' ? $resolvedDate : 'to be confirmed');
    }

    private function deliveryEstimateLabel(StoreOrder $order): ?string
    {
        $breakdown = $order->shippingBreakdown();
        $shipments = collect($breakdown['shipments'] ?? [])->filter(fn ($shipment) => is_array($shipment));

        $label = trim((string) ($breakdown['delivery_estimate_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $firstShipment = $shipments->first();
        $label = is_array($firstShipment)
            ? trim((string) ($firstShipment['delivery_estimate_label'] ?? ''))
            : '';

        return $label !== '' ? $label : null;
    }

    private function statusLabel(string $status): string
    {
        return (new StoreOrder([
            'status' => $status,
        ]))->statusLabel();
    }
}
