<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Expense;
use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\StockItemComponent;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptLine;
use App\Models\StockReservation;
use App\Models\StoreOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockInventoryService
{
    public function __construct(private readonly WorkshopPickListService $pickLists) {}

    public function reservedQuantityForStockItem(StockItem $stockItem): float
    {
        return $this->reservedQuantitiesForStockItems(collect([$stockItem]))[(int) $stockItem->id] ?? 0;
    }

    /** @param Collection<int, StockItem> $stockItems
     *  @return array<int, float>
     */
    public function reservedQuantitiesForStockItems(Collection $stockItems): array
    {
        $stockItems = $stockItems
            ->filter(fn ($item): bool => $item instanceof StockItem)
            ->values();
        if ($stockItems->isEmpty()) {
            return [];
        }

        $reservations = StockReservation::query()
            ->whereIn('stock_item_id', $stockItems->map(fn (StockItem $item): int => (int) $item->id)->all())
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->get(['stock_item_id', 'source_type', 'source_id', 'remaining_quantity', 'reserved_at']);

        return $this->effectiveReservedQuantities($stockItems, $reservations);
    }

    /** @param list<int> $stockItemIds
     *  @return Collection<int, StockItem>
     */
    public function reservationShortages(array $stockItemIds): Collection
    {
        $stockItemIds = collect($stockItemIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($stockItemIds->isEmpty()) {
            return collect();
        }

        $stockItems = StockItem::query()->whereIn('id', $stockItemIds)->get();
        $reservedQuantities = $this->reservedQuantitiesForStockItems($stockItems);

        return $stockItems
            ->filter(function (StockItem $stockItem) use ($reservedQuantities): bool {
                $reserved = (float) ($reservedQuantities[(int) $stockItem->id] ?? 0);
                $shortage = $reserved - (float) $stockItem->on_hand_quantity;
                if ($shortage <= 0.0005) {
                    return false;
                }

                $stockItem->setAttribute('reservation_shortage', round($shortage, 3));

                return true;
            })
            ->values();
    }

    /**
     * Return shortages attributable to a workshop's place in the current
     * reservation forecast, keyed by stock item ID.
     *
     * @return array<int, float>
     */
    public function workshopReservationShortages(Workshop $workshop): array
    {
        return $this->workshopReservationShortagesByWorkshop()[(string) $workshop->getKey()] ?? [];
    }

    /**
     * Return all workshop-specific shortages in one pass, keyed by workshop
     * ID and then stock item ID.
     *
     * @return array<string, array<int, float>>
     */
    public function workshopReservationShortagesByWorkshop(): array
    {
        return app(\App\Support\RequestMemo::class)->remember('workshop-reservation-shortages-by-workshop', function (): array {
            $workshopRows = StockReservation::query()
                ->where('source_type', Workshop::class)
                ->whereIn('status', StockReservation::ACTIVE_STATUSES)
                ->where('remaining_quantity', '>', 0.0005)
                ->get(['stock_item_id', 'source_type', 'source_id', 'remaining_quantity', 'reserved_at']);
            $stockItemIds = $workshopRows->pluck('stock_item_id')->map(fn ($id): int => (int) $id)->unique()->values();
            if ($stockItemIds->isEmpty()) {
                return [];
            }

            $stockItems = StockItem::query()->whereIn('id', $stockItemIds)->get()->keyBy(fn (StockItem $item): int => (int) $item->id);
            $reservationRows = StockReservation::query()
                ->whereIn('stock_item_id', $stockItemIds)
                ->whereIn('status', StockReservation::ACTIVE_STATUSES)
                ->where('remaining_quantity', '>', 0.0005)
                ->get(['stock_item_id', 'source_type', 'source_id', 'remaining_quantity', 'reserved_at']);
            $workshopIds = $workshopRows
                ->pluck('source_id')
                ->map(fn ($id): string => (string) $id)
                ->filter(fn (string $id): bool => $id !== '')
                ->unique()
                ->values();
            $startTimes = Workshop::query()->whereIn('id', $workshopIds)->get(['id', 'starts_at'])
                ->mapWithKeys(fn (Workshop $row): array => [(string) $row->getKey() => $row->starts_at]);

            $shortages = [];
            foreach ($stockItems as $stockItemId => $stockItem) {
                $rows = $reservationRows->where('stock_item_id', (int) $stockItemId);
                $hardReserved = (float) $rows
                    ->where('source_type', '!=', Workshop::class)
                    ->sum('remaining_quantity');
                $events = $rows
                    ->where('source_type', Workshop::class)
                    ->groupBy(fn (StockReservation $reservation): string => (string) $reservation->source_id)
                    ->map(function (Collection $eventRows, string $sourceId) use ($startTimes): array {
                        $startsAt = $startTimes->get($sourceId)
                            ?? $eventRows->first()?->reserved_at;

                        return [
                            'source_id' => $sourceId,
                            'quantity' => (float) $eventRows->sum('remaining_quantity'),
                            'sort_key' => $this->reservationDateSortKey($startsAt) ?? '9999-12-31 23:59:59',
                        ];
                    })
                    ->values()
                    ->all();
                usort($events, fn (array $left, array $right): int => [$left['sort_key'], $left['source_id']] <=> [$right['sort_key'], $right['source_id']]);

                $runningWorkshopReserve = 0.0;
                foreach ($events as $event) {
                    if ($stockItem->shared_workshop_supply) {
                        $bufferedReserve = $runningWorkshopReserve > 0.0005
                            ? $stockItem->roundStockIssueQuantity(
                                $runningWorkshopReserve * (1 + StockItem::SHARED_WORKSHOP_RESERVATION_BUFFER_PERCENT),
                            )
                            : 0;
                        $runningWorkshopReserve = max((float) $event['quantity'], $bufferedReserve);
                    } else {
                        $runningWorkshopReserve += (float) $event['quantity'];
                    }

                    $shortage = $hardReserved + $runningWorkshopReserve - (float) $stockItem->on_hand_quantity;
                    if ($shortage > 0.0005) {
                        $shortages[(string) $event['source_id']][(int) $stockItemId] = round($shortage, 3);
                    }
                }
            }

            return $shortages;
        });
    }

    public function stockItemFor(Product $product, ?ProductVariant $variant = null): ?StockItem
    {
        return $product->linkedStockItem($variant);
    }

    /** @return Collection<int, StockItemComponent> */
    public function componentsFor(StockItem $kit): Collection
    {
        return $kit->relationLoaded('kitComponents')
            ? $kit->kitComponents
            : $kit->kitComponents()->with('component')->get();
    }

    /** @return Collection<int, StockItem> */
    public function stockItemsFor(Product $product, ?ProductVariant $variant = null): Collection
    {
        $item = $this->stockItemFor($product, $variant);
        if (! $item instanceof StockItem) {
            return collect();
        }

        $state = $this->inventoryState();
        $items = collect();
        $this->appendStockItemTree($item, $items, $state, []);

        return $items->unique('id')->values();
    }

    public function requiresStockItem(Product $product, ?ProductVariant $variant, int $stockItemId): bool
    {
        return $this->stockItemsFor($product, $variant)
            ->contains(fn (StockItem $stockItem): bool => (int) $stockItem->id === $stockItemId);
    }

    public function isStockManaged(Product $product, ?ProductVariant $variant = null): bool
    {
        return $this->stockItemFor($product, $variant) instanceof StockItem;
    }

    /** @param array<int, float> $allocatedStock */
    public function availableProductQuantity(Product $product, ?ProductVariant $variant = null, array $allocatedStock = []): ?int
    {
        $item = $this->stockItemFor($product, $variant);
        if (! $item instanceof StockItem) {
            return null;
        }
        if (! $item->tracksInventory()) {
            return 0;
        }

        $quantityPerSale = ($variant instanceof ProductVariant ? $variant->stock_quantity_per_sale : null)
            ?? $product->stock_quantity_per_sale
            ?? 1;
        $state = $this->inventoryState();

        return $this->maximumAvailableUnits($item, max(0.001, (float) $quantityPerSale), $state, $allocatedStock);
    }

    /** @param array<int, float> $allocatedStock */
    public function planProductStockUsage(Product $product, ?ProductVariant $variant, int $quantity, array &$allocatedStock): bool
    {
        if ($quantity <= 0) {
            return true;
        }
        $item = $this->stockItemFor($product, $variant);
        if (! $item instanceof StockItem || ! $item->tracksInventory()) {
            return true;
        }

        $quantityPerSale = ($variant instanceof ProductVariant ? $variant->stock_quantity_per_sale : null)
            ?? $product->stock_quantity_per_sale
            ?? 1;
        $state = $this->inventoryState();
        $planned = $allocatedStock;
        if (! $this->planStockItemUsage($item, $quantity * max(0.001, (float) $quantityPerSale), $planned, $state)) {
            return false;
        }
        $allocatedStock = $planned;

        return true;
    }

    public function availableStockItemQuantity(StockItem $stockItem): float
    {
        $state = $this->inventoryState();
        $resolved = $state['items']->get((int) $stockItem->id);
        if (! $resolved instanceof StockItem || ! $resolved->tracksInventory()) {
            return 0;
        }
        if (! $resolved->is_kit) {
            return $this->availableStockInState($resolved, [], $state);
        }

        return (float) $this->maximumAvailableUnits($resolved, 1, $state, []);
    }

    public function replacementCostForStockItem(StockItem $stockItem): ?float
    {
        return $this->replacementCostInTree($stockItem, []);
    }

    public function replacementCostFor(Product $product, ?ProductVariant $variant = null): ?float
    {
        $requirements = $this->requirementsForSale($product, $variant, 1);
        if ($requirements === []) {
            return null;
        }

        $item = $requirements[0]['stock_item'] ?? null;
        if (! $item instanceof StockItem) {
            return null;
        }
        $unitCost = $this->replacementCostForStockItem($item);
        if ($unitCost === null) {
            return null;
        }

        return round($unitCost * (float) $requirements[0]['quantity'], 4);
    }

    public function reserveForStoreOrderItem(StoreOrderItem $item, int $quantity): int
    {
        return $this->reserveForSource(
            $item,
            $item->product()->firstOrFail(),
            $item->product_variant_id ? $item->variant()->first() : null,
            $quantity,
            'store_sale',
            'cart',
        );
    }

    public function reserveForInvoiceLine(InvoiceLine $line, Product $product, ?ProductVariant $variant, int $quantity): int
    {
        return $this->reserveForSource($line, $product, $variant, $quantity, 'invoice_sale', 'line_items', [
            'invoice_id' => (string) $line->invoice_id,
        ]);
    }

    public function releaseForStoreOrderItem(StoreOrderItem $item, int $quantity): void
    {
        $product = $item->relationLoaded('product') ? $item->product : $item->product()->first();
        if (! $product instanceof Product) {
            return;
        }

        $variant = $item->product_variant_id
            ? ($item->relationLoaded('variant') ? $item->variant : $item->variant()->first())
            : null;
        $this->releaseForSource($item, $product, $variant, $quantity);
    }

    public function consumeForStoreOrderItem(StoreOrderItem $item, int $quantity): void
    {
        $product = $item->relationLoaded('product') ? $item->product : $item->product()->first();
        if (! $product instanceof Product) {
            return;
        }

        $variant = $item->product_variant_id
            ? ($item->relationLoaded('variant') ? $item->variant : $item->variant()->first())
            : null;
        $this->consumeForSource($item, $product, $variant, $quantity, StockMovement::TYPE_SALE);
    }

    public function syncInvoiceReservations(Invoice $invoice, bool $release = false): void
    {
        $this->releaseInvoiceReservations($invoice);
        if ($release || $invoice->status === Invoice::STATUS_CANCELLED) {
            return;
        }

        foreach ($invoice->lines()->where('kind', 'product')->get() as $line) {
            if ($line->source_type !== Product::class) {
                continue;
            }

            $product = Product::query()->find($line->source_id);
            if (! $product instanceof Product || ! $product->isPhysical()) {
                continue;
            }

            $variantId = data_get($line->details_json, 'variant_id') ?: data_get($line->details_json, 'store_context.variant_id');
            $variant = $variantId ? $product->variants()->find($variantId) : null;
            if (! $this->isStockManaged($product, $variant)) {
                continue;
            }

            $quantity = (float) $line->quantity;
            if ($quantity < 0 || floor($quantity) !== $quantity) {
                throw ValidationException::withMessages(['line_items' => 'Stock-managed products require a whole, non-negative number of packs.']);
            }
            if ($quantity > 0) {
                $this->reserveForInvoiceLine($line, $product, $variant, (int) $quantity);
            }
        }
    }

    public function syncWorkshopReservations(Workshop $workshop, bool $release = false, bool $refreshAfterStart = false): void
    {
        if ($release
            || in_array((string) $workshop->status, ['draft', 'cancelled'], true)
            || $workshop->stock_reconciled_at !== null
            || $workshop->starts_at === null) {
            $this->releaseAllForSource(Workshop::class, (string) $workshop->getKey());

            return;
        }

        // Keep existing reservations after a workshop starts unless an admin
        // explicitly saves its workshop plan or blueprint. Reconciliation uses
        // the latest saved plan until stock use has been recorded.
        if (! $refreshAfterStart && $workshop->starts_at->isPast()) {
            return;
        }

        $this->releaseAllForSource(Workshop::class, (string) $workshop->getKey());

        $workshop = $workshop->fresh(['pickListTemplate.items']);
        $participants = $this->pickLists->reservationParticipants($workshop);
        $summary = $this->pickLists->build($workshop, $participants);
        if ($workshop->registration !== 'tickets'
            && $workshop->max_attendance === null
            && $workshop->pick_list_participants === null
            && $summary['resolvedItems']->contains(fn (array $item): bool =>
                (int) ($item['stock_item_id'] ?? 0) > 0
                    && (string) ($item['quantity_type'] ?? '') === PickListTemplateItem::TYPE_PER_PARTICIPANT
            )) {
            throw ValidationException::withMessages([
                'max_attendance' => 'Enter maximum attendance to reserve per-participant workshop stock.',
            ]);
        }
        $state = $this->inventoryState();
        $itemsToReserve = collect($summary['calculatedItems'])
            ->map(fn (array $item): int => (int) ($item['stock_item_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->map(fn (int $id) => $state['items']->get($id))
            ->filter(fn ($item): bool => $item instanceof StockItem)
            ->values();
        $state = $this->lockInventoryTree($itemsToReserve);
        $requirements = [];
        $materialBatches = [];
        $allocatedKits = [];

        foreach ($summary['calculatedItems'] as $item) {
            $stockItemId = (int) ($item['stock_item_id'] ?? 0);
            if ($stockItemId <= 0) {
                continue;
            }

            $stockItem = $state['items']->get($stockItemId);
            $quantity = max(0, (float) ($item['stock_quantity_total'] ?? $item['quantity'] ?? 0));
            if (! $stockItem instanceof StockItem || ! $stockItem->tracksInventory() || $quantity <= 0.0005) {
                continue;
            }

            if ($stockItem->is_kit) {
                $quantity = $stockItem->roundStockIssueQuantity($quantity);
                if (! $this->planAssemblyKitComponent($stockItem, $quantity, $state, $requirements, $materialBatches, $allocatedKits, [])) {
                    throw ValidationException::withMessages([
                        'pick_list_custom_items' => 'A kit in this workshop plan is inactive, has no recipe, or contains a circular recipe.',
                    ]);
                }

                continue;
            }

            $units = max(0, (float) ($item['quantity'] ?? 0));
            $quantityPerUnit = $units > 0.0005 ? $quantity / $units : $quantity;
            if (! $this->addAssemblyRawRequirement($stockItem, $units > 0.0005 ? $units : 1, $quantityPerUnit, $requirements, $materialBatches)) {
                throw ValidationException::withMessages([
                    'pick_list_custom_items' => 'A stock item in this workshop plan is no longer active.',
                ]);
            }
        }

        // Reserve stock increments after applying each recipe batch's estimated
        // cutting loss. The recipe's fractional quantity remains unchanged for cost.
        foreach ($requirements as $stockItemId => $recipeQuantity) {
            $stockItem = $state['items']->get((int) $stockItemId);
            if (! $stockItem instanceof StockItem) {
                continue;
            }

            $estimatedUsage = (float) $recipeQuantity;
            if (! $stockItem->is_kit && isset($materialBatches[$stockItemId])) {
                $estimatedUsage = 0;
                foreach ($materialBatches[$stockItemId] as $batch) {
                    $estimatedUsage += StockItem::estimateMaterialUse(
                        (float) $batch['units'],
                        (float) $batch['quantity_per_unit'],
                        $stockItem->stockIssueIncrement(),
                    );
                }
            }

            $reservedQuantity = $stockItem->roundStockIssueQuantity($estimatedUsage);
            $projectedReservedQuantity = $stockItem->shared_workshop_supply
                ? $this->effectiveReservedQuantities(
                    collect([$stockItem]),
                    $state['reservations'],
                    [(int) $stockItemId => [[
                        'stock_item_id' => (int) $stockItemId,
                        'source_type' => Workshop::class,
                        'source_id' => (string) $workshop->getKey(),
                        'remaining_quantity' => $reservedQuantity,
                        'reserved_at' => now(),
                        'starts_at' => $workshop->starts_at,
                    ]]],
                )[(int) $stockItemId] ?? 0
                : (float) ($state['reserved'][$stockItemId] ?? 0) + $reservedQuantity;
            if ($projectedReservedQuantity > (float) $stockItem->on_hand_quantity + 0.0005) {
                throw ValidationException::withMessages([
                    'pick_list_custom_items' => $stockItem->shared_workshop_supply
                        ? 'There is not enough stock available for this workshop after cutting allowance, whole-unit picking, and the shared workshop forecast are applied. Receive or adjust stock before reserving it.'
                        : 'There is not enough stock available for this workshop after cutting allowance and whole-unit picking are applied. Receive or adjust stock before reserving it.',
                ]);
            }

            $requirements[$stockItemId] = $reservedQuantity;
        }

        if ($requirements === []) {
            return;
        }

        $stockItems = StockItem::query()->whereIn('id', array_keys($requirements))->get()->keyBy('id');

        foreach ($requirements as $stockItemId => $quantity) {
            $stockItem = $stockItems->get($stockItemId);
            if (! $stockItem instanceof StockItem || ! $stockItem->tracksInventory()) {
                continue;
            }
            StockReservation::query()->create([
                'stock_item_id' => $stockItem->id,
                'source_type' => Workshop::class,
                'source_id' => (string) $workshop->getKey(),
                'purpose' => 'workshop',
                'status' => StockReservation::STATUS_ACTIVE,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost_snapshot' => $stockItem->replacementCost(),
                'metadata' => ['workshop_id' => (string) $workshop->getKey(), 'kit_capacity_reservation' => true],
                'reserved_at' => now(),
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Consume workshop reservations according to the quantities actually used,
     * release the unused balance, and allow extra use from unreserved stock.
     *
     * @param  array<int|string, int|float|string>  $actualUsed
     * @return list<int> Stock item IDs whose availability may have changed.
     */
    public function reconcileWorkshopStock(Workshop $workshop, array $actualUsed, ?User $user = null): array
    {
        return DB::transaction(function () use ($workshop, $actualUsed, $user): array {
            $lockedWorkshop = Workshop::query()->whereKey($workshop->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedWorkshop->stock_reconciled_at !== null) {
                throw ValidationException::withMessages(['stock' => 'Stock for this workshop has already been reconciled.']);
            }
            if (in_array((string) $lockedWorkshop->status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['stock' => 'Draft or cancelled workshops do not need stock reconciliation.']);
            }

            $reconciliationTime = $lockedWorkshop->effectiveEndsAt() ?? $lockedWorkshop->starts_at;
            if ($reconciliationTime === null || $reconciliationTime->isFuture()) {
                throw ValidationException::withMessages(['stock' => 'Workshop stock can be reconciled after the workshop has ended.']);
            }

            $normalizedUsage = [];
            foreach ($actualUsed as $stockItemId => $quantity) {
                if (! preg_match('/^[1-9][0-9]*$/D', (string) $stockItemId)
                    || ! is_numeric($quantity)
                    || ! is_finite((float) $quantity)
                    || (float) $quantity < 0
                    || (float) $quantity > 100000) {
                    throw ValidationException::withMessages(['actual_used' => 'Enter a valid quantity used for each stock item.']);
                }

                $id = (int) $stockItemId;
                if (array_key_exists($id, $normalizedUsage)) {
                    throw ValidationException::withMessages(['actual_used' => 'Each stock item can only be listed once.']);
                }

                $normalizedUsage[$id] = round((float) $quantity, 3);
            }

            $reservations = StockReservation::query()
                ->where('source_type', Workshop::class)
                ->where('source_id', (string) $lockedWorkshop->getKey())
                ->whereIn('status', StockReservation::ACTIVE_STATUSES)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $reservedStockItemIds = $reservations->pluck('stock_item_id')->map(fn ($id): int => (int) $id)->unique();
            $workshopPickList = $this->pickLists->build($lockedWorkshop);
            $plannedKitIds = collect(array_keys($workshopPickList['kitContentsByStockItem']))
                ->map(fn ($id): int => (int) $id)
                ->values();
            $plannedKitComponentIds = collect($workshopPickList['kitContentsByStockItem'])
                ->flatten(1)
                ->pluck('stock_item_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            foreach ($reservedStockItemIds as $reservedStockItemId) {
                if (! array_key_exists($reservedStockItemId, $normalizedUsage)) {
                    $isKitComponentReleasedByKitUse = $plannedKitComponentIds->contains($reservedStockItemId)
                        && $plannedKitIds->contains(fn (int $kitStockItemId): bool => array_key_exists($kitStockItemId, $normalizedUsage));
                    if ($isKitComponentReleasedByKitUse) {
                        continue;
                    }

                    throw ValidationException::withMessages(['actual_used' => 'Enter the actual quantity used for every reserved stock item.']);
                }
            }

            $stockItemIds = $reservedStockItemIds->merge(array_keys($normalizedUsage))->unique()->sort()->values();
            $stockItems = StockItem::query()
                ->whereIn('id', $stockItemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($normalizedUsage as $stockItemId => $quantity) {
                $stockItem = $stockItems->get($stockItemId);
                if (! $stockItem instanceof StockItem) {
                    throw ValidationException::withMessages(['actual_used' => 'A selected stock item could not be found.']);
                }
                if ($stockItem->is_kit && abs($quantity - round($quantity)) > 0.0005) {
                    throw ValidationException::withMessages(['actual_used' => 'Kit quantities must be whole numbers.']);
                }
                $increment = $stockItem->stockIssueIncrement();
                if (abs($quantity - round($quantity / $increment) * $increment) > 0.0005) {
                    throw ValidationException::withMessages([
                        'actual_used' => $stockItem->linkLabel().' quantities must be entered in increments of '.$stockItem->formatQuantity($increment).'.',
                    ]);
                }

                $hasWorkshopReservation = $reservations->contains(fn (StockReservation $reservation): bool => (int) $reservation->stock_item_id === $stockItemId);
                if (! $hasWorkshopReservation && $quantity > 0 && ! $stockItem->tracksInventory()) {
                    throw ValidationException::withMessages(['actual_used' => 'Only active stock items can be added as extra workshop use.']);
                }
            }

            $affectedStockItemIds = $reservedStockItemIds->all();
            foreach ($normalizedUsage as $stockItemId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $remainingToConsume = $quantity;
                foreach ($reservations->where('stock_item_id', $stockItemId) as $reservation) {
                    if ($remainingToConsume <= 0.0005) {
                        break;
                    }

                    $consume = min($remainingToConsume, (float) $reservation->remaining_quantity);
                    if ($consume <= 0.0005) {
                        continue;
                    }

                    $this->consumeReservation(
                        $reservation,
                        $consume,
                        StockMovement::TYPE_WORKSHOP,
                        $lockedWorkshop,
                        $user,
                        'Workshop stock used',
                    );
                    $remainingToConsume -= $consume;
                }

                if ($remainingToConsume > 0.0005) {
                    $stockItem = $stockItems->get($stockItemId);
                    if (! $stockItem instanceof StockItem) {
                        throw ValidationException::withMessages(['actual_used' => 'A selected stock item could not be found.']);
                    }

                    $this->consumeUnreservedStockItem($stockItem, $remainingToConsume, $lockedWorkshop, $user);
                }

                $affectedStockItemIds[] = $stockItemId;
            }

            $this->releaseAllForSource(Workshop::class, (string) $lockedWorkshop->getKey());
            $lockedWorkshop->stock_reconciled_at = now();
            $lockedWorkshop->stock_reconciled_by = $user?->id;
            $lockedWorkshop->save();

            return array_values(array_unique(array_map('intval', $affectedStockItemIds)));
        }, 3);
    }

    public function syncWorkshopReservationsForTemplate(PickListTemplate $template): int
    {
        $workshops = Workshop::query()
            ->where('pick_list_template_id', $template->id)
            ->where('pick_list_is_customized', false)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('stock_reconciled_at')
            ->get();

        foreach ($workshops as $workshop) {
            $this->syncWorkshopReservations($workshop, refreshAfterStart: true);
        }

        return $workshops->count();
    }

    public function syncWorkshopReservationsUsingStockItem(StockItem $stockItem): int
    {
        $relatedStockItemIds = collect([(int) $stockItem->id]);
        $frontier = $relatedStockItemIds;
        while ($frontier->isNotEmpty()) {
            $parents = StockItemComponent::query()
                ->whereIn('component_stock_item_id', $frontier->all())
                ->pluck('kit_stock_item_id')
                ->map(fn ($id): int => (int) $id)
                ->diff($relatedStockItemIds)
                ->values();
            $relatedStockItemIds = $relatedStockItemIds->merge($parents)->unique()->values();
            $frontier = $parents;
        }

        $relatedIds = $relatedStockItemIds->all();
        $workshops = Workshop::query()
            ->with('pickListTemplate.items')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('starts_at', '>', now())
            ->where(function ($query) use ($relatedIds): void {
                $query->whereHas('pickListTemplate.items', fn ($items) => $items->whereIn('stock_item_id', $relatedIds))
                    ->orWhere('pick_list_is_customized', true);
            })
            ->get();

        $updated = 0;
        foreach ($workshops as $workshop) {
            $resolvedItems = $this->pickLists->build($workshop)['resolvedItems'];
            $usesRelatedStock = $resolvedItems->contains(fn (array $item): bool => in_array((int) ($item['stock_item_id'] ?? 0), $relatedIds, true));
            if (! $usesRelatedStock) {
                continue;
            }

            $this->syncWorkshopReservations($workshop);
            $updated++;
        }

        return $updated;
    }

    public function releaseExpiredWorkshopReservations(): int
    {
        return DB::transaction(function (): int {
            $reservedWorkshopIds = StockReservation::query()
                ->where('source_type', Workshop::class)
                ->whereIn('status', StockReservation::ACTIVE_STATUSES)
                ->distinct()
                ->pluck('source_id')
                ->map(fn ($id): string => (string) $id)
                ->all();

            if ($reservedWorkshopIds === []) {
                return 0;
            }

            $workshops = Workshop::query()
                ->whereIn('id', $reservedWorkshopIds)
                ->get()
                ->keyBy(fn (Workshop $workshop): string => (string) $workshop->getKey());

            $releasedCount = 0;
            foreach ($reservedWorkshopIds as $workshopId) {
                $workshop = $workshops->get($workshopId);
                if ($workshop instanceof Workshop
                    && ! in_array((string) $workshop->status, ['draft', 'cancelled'], true)
                    && $workshop->stock_reconciled_at === null
                    && $workshop->starts_at !== null) {
                    continue;
                }

                $releasedCount += StockReservation::query()
                    ->where('source_type', Workshop::class)
                    ->where('source_id', $workshopId)
                    ->whereIn('status', StockReservation::ACTIVE_STATUSES)
                    ->count();
                $this->releaseAllForSource(Workshop::class, $workshopId);
            }

            return $releasedCount;
        });
    }

    public function workshopCost(Workshop $workshop): ?float
    {
        $freshWorkshop = $workshop->fresh(['pickListTemplate.items']) ?? $workshop;
        $summary = $this->pickLists->build($freshWorkshop, $this->pickLists->reservationParticipants($freshWorkshop));
        $total = 0.0;
        $hasLinkedItems = false;

        foreach ($summary['calculatedItems'] as $item) {
            $stockItemId = (int) ($item['stock_item_id'] ?? 0);
            if ($stockItemId <= 0) {
                continue;
            }
            $hasLinkedItems = true;
            $cost = StockItem::query()->find($stockItemId)?->replacementCost();
            if ($cost === null) {
                return null;
            }
            $total += $cost * (float) ($item['stock_quantity_total'] ?? $item['quantity'] ?? 0);
        }

        return $hasLinkedItems ? round($total, 4) : 0.0;
    }

    /**
     * @param array<int, array{stock_receipt_line_id?: int|string|null, stock_item_id: int|string, quantity: int|float|string, total_cost_ex_tax: int|float|string}> $items
     * @return array<int, int> IDs of stock items whose receipts were synchronized.
     */
    public function syncExpenseStockItems(Expense $expense, array $items, ?User $user = null): array
    {
        try {
            return DB::transaction(function () use ($expense, $items, $user): array {
                $lockedExpense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
                $existingLines = StockReceiptLine::query()
                    ->with(['receipt', 'stockItem'])
                    ->whereHas('receipt', fn ($query) => $query->where('expense_id', $lockedExpense->id))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $submittedLineIds = [];
                $stockItemIds = [];

                foreach ($items as $item) {
                    $lineId = (int) ($item['stock_receipt_line_id'] ?? 0);
                    $existingLine = null;
                    if ($lineId > 0) {
                        if (isset($submittedLineIds[$lineId])) {
                            throw ValidationException::withMessages(['stock_items' => 'A stock receipt line was included more than once.']);
                        }
                        $existingLine = $existingLines->get($lineId);
                        if (! $existingLine instanceof StockReceiptLine) {
                            throw ValidationException::withMessages(['stock_items' => 'A linked stock receipt no longer belongs to this expense. Reload the page and try again.']);
                        }
                        $submittedLineIds[$lineId] = true;
                    }

                    $stockItem = StockItem::query()->whereKey((int) $item['stock_item_id'])->lockForUpdate()->firstOrFail();
                    if ($stockItem->is_kit) {
                        throw ValidationException::withMessages(['stock_items' => 'Choose an individual stock item rather than a kit.']);
                    }
                    $sameExistingItem = $existingLine instanceof StockReceiptLine
                        && (int) $existingLine->stock_item_id === (int) $stockItem->id;
                    if ($stockItem->status !== StockItem::STATUS_ACTIVE && ! $sameExistingItem) {
                        throw ValidationException::withMessages(['stock_items' => 'Archived stock items can only remain on their existing receipt.']);
                    }

                    $receivedAt = $lockedExpense->paid_on?->toDateString()
                        ?? $existingLine?->receipt?->received_at?->toDateString()
                        ?? now()->toDateString();
                    $attributes = [
                        'expense_id' => $lockedExpense->id,
                        'supplier' => $lockedExpense->supplier,
                        'reference' => $lockedExpense->invoice_id,
                        'received_at' => $receivedAt,
                        'notes' => $existingLine?->receipt?->notes,
                    ];

                    if ($sameExistingItem) {
                        $this->updateReceiptLine(
                            $stockItem,
                            $existingLine,
                            (float) $item['quantity'],
                            (float) $item['total_cost_ex_tax'],
                            $attributes,
                        );
                    } else {
                        if ($existingLine instanceof StockReceiptLine) {
                            $oldStockItem = $existingLine->stockItem;
                            if (! $oldStockItem instanceof StockItem) {
                                throw ValidationException::withMessages(['stock_items' => 'A linked stock item could not be found. Reload the page and try again.']);
                            }
                            $this->deleteReceiptLine($oldStockItem, $existingLine);
                            $stockItemIds[(int) $oldStockItem->id] = true;
                        }

                        $this->receive(
                            $stockItem,
                            (float) $item['quantity'],
                            (float) $item['total_cost_ex_tax'],
                            $attributes,
                            $user,
                        );
                    }

                    $stockItemIds[(int) $stockItem->id] = true;
                }

                foreach ($existingLines as $lineId => $existingLine) {
                    if (isset($submittedLineIds[(int) $lineId])) {
                        continue;
                    }

                    $stockItem = $existingLine->stockItem;
                    if (! $stockItem instanceof StockItem) {
                        throw ValidationException::withMessages(['stock_items' => 'A linked stock item could not be found. Reload the page and try again.']);
                    }
                    $this->deleteReceiptLine($stockItem, $existingLine);
                    $stockItemIds[(int) $stockItem->id] = true;
                }

                return array_map('intval', array_keys($stockItemIds));
            });
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())->flatten()->filter()->unique()->values()->all();
            throw ValidationException::withMessages([
                'stock_items' => [
                    'Stock receipts could not be updated, so the expense was not saved. '
                        .($messages !== [] ? implode(' ', $messages) : 'Review the stock item details and try again.')
                        .' Reload this page to restore the saved stock rows if needed.',
                ],
            ]);
        }
    }

    public function receive(
        StockItem $stockItem,
        float $quantity,
        float $totalCostExTax,
        array $attributes = [],
        ?User $user = null,
    ): StockReceiptLine {
        if ($quantity <= 0 || $totalCostExTax < 0) {
            throw ValidationException::withMessages(['total_cost_ex_tax' => 'Enter a positive quantity and a non-negative total cost.']);
        }

        $quantity = round($quantity, 3);
        $totalCostExTax = round($totalCostExTax, 4);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity of at least 0.001.']);
        }
        $unitCost = round($totalCostExTax / $quantity, 4);

        return DB::transaction(function () use ($stockItem, $quantity, $unitCost, $totalCostExTax, $attributes, $user): StockReceiptLine {
            $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            if ($lockedItem->is_kit) {
                throw ValidationException::withMessages(['quantity' => 'Use the kit assembly action to add ready-made kits.']);
            }
            $expense = ! empty($attributes['expense_id'])
                ? Expense::query()->findOrFail((int) $attributes['expense_id'])
                : null;
            $supplierName = trim((string) ($expense->supplier ?? $attributes['supplier'] ?? ''));
            $supplierId = $expense?->supplier_id;

            if ($supplierId === null && $supplierName !== '') {
                $supplierId = Supplier::forName($supplierName)->id;
            }

            $effectiveUnitCost = round($unitCost, 4);

            $receipt = StockReceipt::query()->create([
                'supplier_id' => $supplierId,
                'expense_id' => $expense?->id,
                'created_by' => $user?->id,
                'received_at' => $attributes['received_at'] ?? $expense->paid_on ?? now(),
                'currency' => 'AUD',
                'exchange_rate' => 1,
                'freight_ex_tax' => 0,
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
            ]);

            $line = $receipt->lines()->create([
                'stock_item_id' => $lockedItem->id,
                'quantity' => $quantity,
                'unit_cost_ex_tax' => $unitCost,
                'total_cost_ex_tax' => $totalCostExTax,
            ]);

            $lockedItem->on_hand_quantity = (float) $lockedItem->on_hand_quantity + $quantity;
            $lockedItem->replacement_unit_cost_ex_tax = $effectiveUnitCost;
            $lockedItem->replacement_cost_currency = 'AUD';
            $lockedItem->replacement_cost_source = 'latest_receipt';
            $lockedItem->replacement_cost_updated_at = now();
            $lockedItem->save();

            $line->stockItem()->associate($lockedItem);
            StockMovement::query()->create([
                'stock_item_id' => $lockedItem->id,
                'stock_receipt_line_id' => $line->id,
                'movement_type' => StockMovement::TYPE_RECEIPT,
                'quantity' => $quantity,
                'unit_cost_ex_tax' => $effectiveUnitCost,
                'source_type' => StockReceipt::class,
                'source_id' => (string) $receipt->id,
                'created_by' => $user?->id,
                'occurred_at' => $receipt->received_at,
                'notes' => $receipt->notes,
            ]);

            return $line->fresh('receipt');
        });
    }

    public function updateReceiptLine(
        StockItem $stockItem,
        StockReceiptLine $receiptLine,
        float $quantity,
        float $totalCostExTax,
        array $attributes = [],
    ): StockReceiptLine {
        $quantity = round($quantity, 3);
        $totalCostExTax = round($totalCostExTax, 4);
        if ($quantity <= 0 || $totalCostExTax < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Enter a positive quantity and a non-negative total cost.',
            ]);
        }

        $unitCost = round($totalCostExTax / $quantity, 4);

        return DB::transaction(function () use ($stockItem, $receiptLine, $quantity, $totalCostExTax, $unitCost, $attributes): StockReceiptLine {
            $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            if ($lockedItem->is_kit) {
                throw ValidationException::withMessages(['quantity' => 'Kit receipts cannot be edited here.']);
            }

            $lockedLine = StockReceiptLine::query()
                ->whereKey($receiptLine->id)
                ->where('stock_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->firstOrFail();
            $receipt = StockReceipt::query()->whereKey($lockedLine->stock_receipt_id)->lockForUpdate()->firstOrFail();

            $newOnHand = round((float) $lockedItem->on_hand_quantity + $quantity - (float) $lockedLine->quantity, 3);
            $reserved = $lockedItem->activeReservedQuantity();
            if ($newOnHand < -0.0005) {
                throw ValidationException::withMessages([
                    'quantity' => 'This receipt cannot be reduced because some of its stock has already been used.',
                ]);
            }
            if ($newOnHand + 0.0005 < $reserved) {
                throw ValidationException::withMessages([
                    'quantity' => 'This change would leave less stock than is currently reserved.',
                ]);
            }

            $expense = ! empty($attributes['expense_id'])
                ? Expense::query()->findOrFail((int) $attributes['expense_id'])
                : null;
            $supplierName = trim((string) ($expense->supplier ?? $attributes['supplier'] ?? ''));
            $supplierId = $expense?->supplier_id;
            if ($supplierId === null && $supplierName !== '') {
                $supplierId = Supplier::forName($supplierName)->id;
            }

            $receipt->update([
                'supplier_id' => $supplierId,
                'expense_id' => $expense?->id,
                'received_at' => $attributes['received_at'] ?? $expense->paid_on ?? $receipt->received_at,
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
            ]);

            $lockedLine->update([
                'quantity' => $quantity,
                'unit_cost_ex_tax' => $unitCost,
                'total_cost_ex_tax' => $totalCostExTax,
            ]);

            $lockedItem->on_hand_quantity = max(0, $newOnHand);
            if ($lockedItem->replacement_cost_source === 'latest_receipt') {
                $latestReceiptLineId = StockReceiptLine::query()
                    ->where('stock_item_id', $lockedItem->id)
                    ->orderByDesc('id')
                    ->value('id');
                if ((int) $latestReceiptLineId === (int) $lockedLine->id) {
                    $lockedItem->replacement_unit_cost_ex_tax = $unitCost;
                    $lockedItem->replacement_cost_currency = 'AUD';
                    $lockedItem->replacement_cost_updated_at = now();
                }
            }
            $lockedItem->save();

            $movementAttributes = [
                'stock_item_id' => $lockedItem->id,
                'movement_type' => StockMovement::TYPE_RECEIPT,
                'quantity' => $quantity,
                'unit_cost_ex_tax' => $unitCost,
                'source_type' => StockReceipt::class,
                'source_id' => (string) $receipt->id,
                'occurred_at' => $receipt->received_at,
                'notes' => $receipt->notes,
            ];
            $movement = StockMovement::query()
                ->where('stock_receipt_line_id', $lockedLine->id)
                ->lockForUpdate()
                ->first();
            if ($movement) {
                $movement->update($movementAttributes);
            } else {
                $movementAttributes['stock_receipt_line_id'] = $lockedLine->id;
                $movementAttributes['created_by'] = $receipt->created_by;
                StockMovement::query()->create($movementAttributes);
            }

            return $lockedLine->fresh('receipt');
        });
    }

    public function deleteReceiptLine(StockItem $stockItem, StockReceiptLine $receiptLine): void
    {
        DB::transaction(function () use ($stockItem, $receiptLine): void {
            $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            if ($lockedItem->is_kit) {
                throw ValidationException::withMessages(['receipt' => 'Kit receipts cannot be deleted here.']);
            }

            $lockedLine = StockReceiptLine::query()
                ->whereKey($receiptLine->id)
                ->where('stock_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->firstOrFail();
            $receipt = StockReceipt::query()->whereKey($lockedLine->stock_receipt_id)->lockForUpdate()->firstOrFail();

            $newOnHand = round((float) $lockedItem->on_hand_quantity - (float) $lockedLine->quantity, 3);
            $reserved = $lockedItem->activeReservedQuantity();
            if ($newOnHand < -0.0005) {
                throw ValidationException::withMessages([
                    'receipt' => 'This receipt cannot be deleted because some of its stock has already been used.',
                ]);
            }
            if ($newOnHand + 0.0005 < $reserved) {
                throw ValidationException::withMessages([
                    'receipt' => 'This receipt cannot be deleted because its stock is currently reserved.',
                ]);
            }

            StockMovement::query()->where('stock_receipt_line_id', $lockedLine->id)->delete();
            $lockedLine->delete();
            if (! $receipt->lines()->exists()) {
                $receipt->delete();
            }

            $lockedItem->on_hand_quantity = max(0, $newOnHand);
            if ($lockedItem->replacement_cost_source === 'latest_receipt') {
                $latestReceiptLine = StockReceiptLine::query()
                    ->where('stock_item_id', $lockedItem->id)
                    ->orderByDesc('id')
                    ->first();
                $lockedItem->replacement_unit_cost_ex_tax = $latestReceiptLine?->unit_cost_ex_tax;
                $lockedItem->replacement_cost_currency = 'AUD';
                $lockedItem->replacement_cost_source = $latestReceiptLine ? 'latest_receipt' : null;
                $lockedItem->replacement_cost_updated_at = $latestReceiptLine ? now() : null;
            }
            $lockedItem->save();
        });
    }

    public function adjust(StockItem $stockItem, float $quantity, ?float $unitCost = null, ?string $notes = null, ?User $user = null, ?string $date = null): StockMovement
    {
        if (abs($quantity) < 0.0005) {
            throw ValidationException::withMessages(['quantity' => 'Enter a non-zero adjustment quantity.']);
        }

        return DB::transaction(function () use ($stockItem, $quantity, $unitCost, $notes, $user, $date): StockMovement {
            $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            if ($lockedItem->is_kit && $quantity > 0) {
                throw ValidationException::withMessages(['quantity' => 'Use the kit assembly action to add ready-made kits.']);
            }
            $newQuantity = (float) $lockedItem->on_hand_quantity + $quantity;
            if ($newQuantity < -0.0005) {
                throw ValidationException::withMessages(['quantity' => 'The adjustment cannot reduce on-hand stock below zero.']);
            }

            if ($unitCost !== null && $unitCost >= 0) {
                $lockedItem->replacement_unit_cost_ex_tax = $unitCost;
                $lockedItem->replacement_cost_currency = 'AUD';
                $lockedItem->replacement_cost_source = 'manual_adjustment';
                $lockedItem->replacement_cost_updated_at = now();
            }
            $lockedItem->on_hand_quantity = max(0, $newQuantity);
            $lockedItem->save();

            return StockMovement::query()->create([
                'stock_item_id' => $lockedItem->id,
                'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => $quantity,
                'unit_cost_ex_tax' => $unitCost,
                'created_by' => $user?->id,
                'occurred_at' => $date ? Carbon::parse($date)->startOfDay() : now(),
                'notes' => trim((string) $notes) ?: null,
            ]);
        });
    }

    public function updateAdjustment(StockItem $stockItem, StockMovement $movement, float $quantity, ?string $notes = null): StockMovement
    {
        $quantity = round($quantity, 3);
        if (abs($quantity) < 0.0005) {
            throw ValidationException::withMessages(['quantity' => 'Enter a non-zero adjustment quantity.']);
        }

        return DB::transaction(function () use ($stockItem, $movement, $quantity, $notes): StockMovement {
            $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            $lockedMovement = StockMovement::query()
                ->whereKey($movement->id)
                ->where('stock_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedItem->is_kit
                || $lockedMovement->movement_type !== StockMovement::TYPE_ADJUSTMENT
                || $lockedMovement->stock_receipt_line_id !== null
                || $lockedMovement->source_type !== null
                || $lockedMovement->source_id !== null
            ) {
                throw ValidationException::withMessages(['adjustment' => 'Only manual stock adjustments can be edited.']);
            }

            $newOnHand = round((float) $lockedItem->on_hand_quantity + $quantity - (float) $lockedMovement->quantity, 3);
            if ($newOnHand < -0.0005) {
                throw ValidationException::withMessages([
                    'quantity' => 'This change cannot reduce on-hand stock below zero.',
                ]);
            }

            $lockedItem->on_hand_quantity = max(0, $newOnHand);
            $lockedItem->save();

            $lockedMovement->quantity = $quantity;
            $lockedMovement->notes = trim((string) $notes) ?: null;
            $lockedMovement->save();

            return $lockedMovement;
        }, 3);
    }

    /**
     * @return array{kit: StockItem, quantity: int, rows: array<int, array{stock_item: StockItem, recipe_quantity: float, estimated_usage: float, cut_loss: float, suggested_deduction: float, available_quantity: float, increment: float}>}
     */
    public function assemblyPlan(StockItem $kit, int $quantity, ?Workshop $workshop = null): array
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive number of kits to assemble.']);
        }

        $this->assertWorkshopAssemblyTarget($workshop, $kit);
        $excludedReservationSource = $workshop instanceof Workshop
            ? ['type' => Workshop::class, 'id' => (string) $workshop->getKey()]
            : null;
        $state = $this->inventoryState(excludedReservationSource: $excludedReservationSource);
        $root = $state['items']->get((int) $kit->id);
        if (! $root instanceof StockItem || ! $root->is_kit || ! $root->tracksInventory()) {
            throw ValidationException::withMessages(['quantity' => 'This kit is not available for assembly.']);
        }

        $state = $this->lockInventoryTree(collect([$root]), $excludedReservationSource);
        $root = $state['items']->get((int) $kit->id);
        if (! $root instanceof StockItem) {
            throw ValidationException::withMessages(['quantity' => 'This kit is no longer available.']);
        }

        return $this->buildAssemblyPlan($root, $quantity, $state);
    }

    /**
     * Record actual stock increments used and the number of finished kits produced.
     * Offcuts are not carried in inventory, so the confirmed quantities can be lower
     * than the suggested deduction when saved material is reused.
     *
     * @param array<int|string, int|float|string> $actualUsage
     */
    public function assemble(
        StockItem $kit,
        int $plannedQuantity,
        int $completedQuantity,
        array $actualUsage,
        ?User $user = null,
        ?string $notes = null,
        ?Workshop $workshop = null,
    ): void {
        if ($plannedQuantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive number of kits to assemble.']);
        }
        if ($completedQuantity < 0 || $completedQuantity > $plannedQuantity) {
            throw ValidationException::withMessages(['completed_quantity' => 'Enter a completed quantity from zero up to the planned quantity.']);
        }

        DB::transaction(function () use ($kit, $plannedQuantity, $completedQuantity, $actualUsage, $user, $notes, $workshop): void {
            $lockedWorkshop = $workshop instanceof Workshop
                ? Workshop::query()->whereKey($workshop->getKey())->lockForUpdate()->firstOrFail()
                : null;
            $this->assertWorkshopAssemblyTarget($lockedWorkshop, $kit);
            $excludedReservationSource = $lockedWorkshop instanceof Workshop
                ? ['type' => Workshop::class, 'id' => (string) $lockedWorkshop->getKey()]
                : null;
            $state = $this->inventoryState(excludedReservationSource: $excludedReservationSource);
            $root = $state['items']->get((int) $kit->id);
            if (! $root instanceof StockItem || ! $root->is_kit || ! $root->tracksInventory()) {
                throw ValidationException::withMessages(['quantity' => 'This kit is no longer available.']);
            }

            $state = $this->lockInventoryTree(collect([$root]), $excludedReservationSource);
            $root = $state['items']->get((int) $kit->id);
            if (! $root instanceof StockItem) {
                throw ValidationException::withMessages(['quantity' => 'This kit is no longer available.']);
            }

            $plan = $this->buildAssemblyPlan($root, $plannedQuantity, $state);
            $rows = $plan['rows'];
            foreach (array_keys($actualUsage) as $stockItemId) {
                if (! isset($rows[(int) $stockItemId])) {
                    throw ValidationException::withMessages(['actual_usage' => 'The material list changed. Review the assembly again.']);
                }
            }

            $deductions = [];
            foreach ($rows as $stockItemId => $row) {
                $stockItem = $state['items']->get((int) $stockItemId);
                if (! $stockItem instanceof StockItem) {
                    throw ValidationException::withMessages(['actual_usage' => 'A stock item in this recipe is no longer available.']);
                }

                $entered = $actualUsage[$stockItemId] ?? $actualUsage[(string) $stockItemId] ?? $row['suggested_deduction'];
                if (! is_numeric($entered) || ! is_finite((float) $entered) || (float) $entered < 0) {
                    throw ValidationException::withMessages([
                        'actual_usage.'.$stockItemId => 'Enter a valid non-negative stock quantity.',
                    ]);
                }

                $usedQuantity = round((float) $entered, 3);
                $increment = $stockItem->stockIssueIncrement();
                $incrementCount = $usedQuantity / $increment;
                if (abs($incrementCount - round($incrementCount)) > 0.0005) {
                    throw ValidationException::withMessages([
                        'actual_usage.'.$stockItemId => 'Enter a quantity in increments of '.$stockItem->formatQuantity($increment).' '.$stockItem->unit.'.',
                    ]);
                }

                $availableQuantity = max(
                    0,
                    (float) $stockItem->on_hand_quantity - (float) ($state['reserved'][$stockItemId] ?? 0),
                );
                if ($usedQuantity > $availableQuantity + 0.0005) {
                    throw ValidationException::withMessages([
                        'actual_usage.'.$stockItemId => 'There is not enough unreserved '.$stockItem->name.' to deduct that quantity.',
                    ]);
                }

                $deductions[$stockItemId] = [
                    'stock_item' => $stockItem,
                    'quantity' => $usedQuantity,
                    'row' => $row,
                ];
            }

            if ($completedQuantity === 0 && collect($deductions)->every(fn (array $deduction): bool => $deduction['quantity'] <= 0)) {
                throw ValidationException::withMessages(['completed_quantity' => 'Record a finished kit or a material quantity used or written off.']);
            }

            $notes = trim((string) $notes);
            foreach ($deductions as $stockItemId => $deduction) {
                /** @var StockItem $stockItem */
                $stockItem = $deduction['stock_item'];
                $usedQuantity = (float) $deduction['quantity'];
                if ($usedQuantity <= 0) {
                    continue;
                }

                $stockItem->on_hand_quantity = max(0, (float) $stockItem->on_hand_quantity - $usedQuantity);
                $stockItem->save();

                $row = $deduction['row'];
                $movementNote = 'Assembly plan for '.$plannedQuantity.' '.$root->name.'; '.$completedQuantity.' completed. Recipe need '.$stockItem->formatQuantity($row['recipe_quantity']).' '.$stockItem->unit.'; estimated use including cut loss '.$stockItem->formatQuantity($row['estimated_usage']).' '.$stockItem->unit.'; suggested deduction '.$stockItem->formatQuantity($row['suggested_deduction']).'.';
                if (abs($usedQuantity - (float) $row['suggested_deduction']) > 0.0005) {
                    $movementNote .= ' Actual deduction adjusted.';
                }
                if ($notes !== '') {
                    $movementNote .= ' '.$notes;
                }

                StockMovement::query()->create([
                    'stock_item_id' => $stockItem->id,
                    'movement_type' => StockMovement::TYPE_ASSEMBLY,
                    'quantity' => -$usedQuantity,
                    'unit_cost_ex_tax' => $this->replacementCostForStockItem($stockItem),
                    'source_type' => StockItem::class,
                    'source_id' => (string) $root->id,
                    'created_by' => $user?->id,
                    'occurred_at' => now(),
                    'notes' => $movementNote,
                ]);
            }

            if ($completedQuantity > 0) {
                $root->on_hand_quantity = (float) $root->on_hand_quantity + $completedQuantity;
                $root->save();

                StockMovement::query()->create([
                    'stock_item_id' => $root->id,
                    'movement_type' => StockMovement::TYPE_ASSEMBLY,
                    'quantity' => $completedQuantity,
                    'unit_cost_ex_tax' => $this->replacementCostForStockItem($root),
                    'source_type' => StockItem::class,
                    'source_id' => (string) $root->id,
                    'created_by' => $user?->id,
                    'occurred_at' => now(),
                    'notes' => 'Assembly plan for '.$plannedQuantity.' '.$root->name.'; '.$completedQuantity.' completed.'.($notes !== '' ? ' '.$notes : ''),
                ]);
            }

            if ($lockedWorkshop instanceof Workshop) {
                $this->syncWorkshopReservations($lockedWorkshop->fresh(), refreshAfterStart: true);
            }
        }, 3);
    }

    private function assertWorkshopAssemblyTarget(?Workshop $workshop, StockItem $kit): void
    {
        if (! $workshop instanceof Workshop) {
            return;
        }
        if (in_array((string) $workshop->status, ['draft', 'cancelled'], true)
            || $workshop->stock_reconciled_at !== null) {
            throw ValidationException::withMessages([
                'workshop_id' => 'This workshop is not available for kit preparation.',
            ]);
        }

        $plannedKit = $this->pickLists->build($workshop)['calculatedItems']
            ->contains(fn (array $item): bool => (int) ($item['stock_item_id'] ?? 0) === (int) $kit->id);
        if (! $plannedKit) {
            throw ValidationException::withMessages([
                'workshop_id' => 'This workshop does not include the selected kit in its pick list.',
            ]);
        }
    }

    /**
     * @param array{items: Collection<int, StockItem>, reserved: array<int, float>} $state
     * @return array{kit: StockItem, quantity: int, rows: array<int, array{stock_item: StockItem, recipe_quantity: float, estimated_usage: float, cut_loss: float, suggested_deduction: float, available_quantity: float, increment: float}>}
     */
    private function buildAssemblyPlan(StockItem $kit, int $quantity, array $state): array
    {
        if (! $kit->is_kit || ! $kit->tracksInventory() || $this->componentsFor($kit)->isEmpty()) {
            throw ValidationException::withMessages(['quantity' => 'Add active stock items to this kit before assembling it.']);
        }

        $requirements = [];
        $batches = [];
        $allocated = [];
        $stack = [(int) $kit->id => true];
        foreach ($this->componentsFor($kit) as $component) {
            $stockItem = $state['items']->get((int) $component->component_stock_item_id);
            $quantityPerKit = (float) $component->quantity;
            if (! $stockItem instanceof StockItem || $quantityPerKit <= 0) {
                throw ValidationException::withMessages(['quantity' => 'The saved kit recipe contains an invalid stock item.']);
            }

            if ($stockItem->is_kit) {
                if (! $this->planAssemblyKitComponent($stockItem, $quantity * $quantityPerKit, $state, $requirements, $batches, $allocated, $stack)) {
                    throw ValidationException::withMessages(['quantity' => 'A kit in this recipe is inactive, has no recipe, or contains a circular recipe. Update the recipe before assembling.']);
                }
            } elseif (! $this->addAssemblyRawRequirement($stockItem, $quantity, $quantityPerKit, $requirements, $batches)) {
                throw ValidationException::withMessages(['quantity' => 'The saved kit recipe contains an inactive stock item.']);
            }
        }

        $rows = [];
        foreach ($requirements as $stockItemId => $recipeQuantity) {
            $stockItem = $state['items']->get((int) $stockItemId);
            if (! $stockItem instanceof StockItem) {
                continue;
            }

            $estimatedUsage = (float) $recipeQuantity;
            if (! $stockItem->is_kit && isset($batches[$stockItemId])) {
                $estimatedUsage = 0;
                foreach ($batches[$stockItemId] as $batch) {
                    $estimatedUsage += StockItem::estimateMaterialUse(
                        (float) $batch['units'],
                        (float) $batch['quantity_per_unit'],
                        $stockItem->stockIssueIncrement(),
                    );
                }
            }

            $increment = $stockItem->stockIssueIncrement();
            $rows[(int) $stockItemId] = [
                'stock_item' => $stockItem,
                'recipe_quantity' => (float) $recipeQuantity,
                'estimated_usage' => round($estimatedUsage, 3),
                'cut_loss' => round(max(0, $estimatedUsage - (float) $recipeQuantity), 3),
                'suggested_deduction' => $stockItem->roundStockIssueQuantity($estimatedUsage),
                'available_quantity' => max(0, (float) $stockItem->on_hand_quantity - (float) ($state['reserved'][$stockItemId] ?? 0)),
                'increment' => $increment,
            ];
        }

        return ['kit' => $kit, 'quantity' => $quantity, 'rows' => $rows];
    }

    /** @param array<int, float> $requirements
     *  @param array<int, array<int, array{units: float, quantity_per_unit: float}>> $batches
     */
    private function addAssemblyRawRequirement(StockItem $stockItem, float $units, float $quantityPerUnit, array &$requirements, array &$batches): bool
    {
        if (! $stockItem->tracksInventory()) {
            return false;
        }

        $required = max(0, $units * $quantityPerUnit);
        if ($required <= 0.0005) {
            return true;
        }

        $stockItemId = (int) $stockItem->id;
        $requirements[$stockItemId] = (float) ($requirements[$stockItemId] ?? 0) + $required;
        $batches[$stockItemId][] = ['units' => $units, 'quantity_per_unit' => $quantityPerUnit];

        return true;
    }

    /** @param array{items: Collection<int, StockItem>, reserved: array<int, float>} $state
     *  @param array<int, float> $requirements
     *  @param array<int, array<int, array{units: float, quantity_per_unit: float}>> $batches
     *  @param array<int, float> $allocated
     *  @param array<int, bool> $stack
     */
    private function planAssemblyKitComponent(StockItem $kit, float $quantity, array $state, array &$requirements, array &$batches, array &$allocated, array $stack): bool
    {
        if ($quantity <= 0.0005) {
            return true;
        }

        $kit = $state['items']->get((int) $kit->id) ?? $kit;
        if (! $kit->is_kit || ! $kit->tracksInventory() || isset($stack[$kit->id])) {
            return false;
        }

        $readyQuantity = min($quantity, $this->availableStockInState($kit, $allocated, $state));
        if ($readyQuantity > 0.0005) {
            $kitId = (int) $kit->id;
            $requirements[$kitId] = (float) ($requirements[$kitId] ?? 0) + $readyQuantity;
            $allocated[$kitId] = (float) ($allocated[$kitId] ?? 0) + $readyQuantity;
        }

        $remaining = $quantity - $readyQuantity;
        if ($remaining <= 0.0005) {
            return true;
        }

        $components = $this->componentsFor($kit);
        if ($components->isEmpty()) {
            return false;
        }

        $stack[$kit->id] = true;
        foreach ($components as $component) {
            $stockItem = $state['items']->get((int) $component->component_stock_item_id);
            $quantityPerKit = (float) $component->quantity;
            if (! $stockItem instanceof StockItem || $quantityPerKit <= 0) {
                return false;
            }

            if ($stockItem->is_kit) {
                if (! $this->planAssemblyKitComponent($stockItem, $remaining * $quantityPerKit, $state, $requirements, $batches, $allocated, $stack)) {
                    return false;
                }
            } elseif (! $this->addAssemblyRawRequirement($stockItem, $remaining, $quantityPerKit, $requirements, $batches)) {
                return false;
            }
        }

        return true;
    }

    public function addExistingKits(StockItem $kit, int $quantity, ?User $user = null, ?string $notes = null): StockMovement
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['adjustment_quantity' => 'Enter a positive quantity to add existing kits.']);
        }

        return DB::transaction(function () use ($kit, $quantity, $user, $notes): StockMovement {
            $lockedKit = StockItem::query()->whereKey($kit->id)->lockForUpdate()->firstOrFail();
            if (! $lockedKit->is_kit || ! $lockedKit->tracksInventory()) {
                throw ValidationException::withMessages(['adjustment_quantity' => 'Choose an active kit to add to ready-made stock.']);
            }

            $lockedKit->on_hand_quantity = (float) $lockedKit->on_hand_quantity + $quantity;
            $lockedKit->save();

            return StockMovement::query()->create([
                'stock_item_id' => $lockedKit->id,
                'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => $quantity,
                'unit_cost_ex_tax' => $this->replacementCostForStockItem($lockedKit),
                'source_type' => StockItem::class,
                'source_id' => (string) $lockedKit->id,
                'created_by' => $user?->id,
                'occurred_at' => now(),
                'notes' => trim((string) $notes) ?: 'Existing ready-made kits added',
            ]);
        }, 3);
    }

    /** @return array<int, array{stock_item: StockItem, quantity: float}> */
    private function requirementsForSale(Product $product, ?ProductVariant $variant, int|float $saleQuantity): array
    {
        $saleQuantity = max(0, (float) $saleQuantity);
        $direct = $this->stockItemFor($product, $variant);
        if (! $direct instanceof StockItem) {
            return [];
        }

        $quantityPerSale = ($variant instanceof ProductVariant ? $variant->stock_quantity_per_sale : null)
            ?? $product->stock_quantity_per_sale
            ?? 1;

        return [[
            'stock_item' => $direct,
            'quantity' => $saleQuantity * max(0.001, (float) $quantityPerSale),
        ]];
    }

    private function reserveForSource(
        Model $source,
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        string $purpose,
        string $errorKey,
        array $metadata = [],
    ): int {
        $quantity = max(0, $quantity);
        if ($quantity <= 0) {
            return 0;
        }

        $requirements = $this->requirementsForSale($product, $variant, 1);
        $root = $requirements[0]['stock_item'] ?? null;
        $quantityPerSale = (float) ($requirements[0]['quantity'] ?? 0);
        if (! $root instanceof StockItem || ! $root->tracksInventory() || $quantityPerSale <= 0) {
            return 0;
        }

        $sourceType = get_class($source);
        $sourceId = (string) $source->getKey();
        return DB::transaction(function () use ($root, $quantityPerSale, $quantity, $sourceType, $sourceId, $purpose, $errorKey, $metadata, $product, $variant): int {
            $state = $this->lockInventoryTree(collect([$root]));
            $segments = $this->planStockItemSegments($root, $quantityPerSale, $quantity, $state);
            if ($segments === null) {
                throw ValidationException::withMessages([$errorKey => 'Not enough '.$root->name.' or its components remain in stock.']);
            }

            $stockItemIds = collect($segments)
                ->flatMap(fn (array $segment): array => array_keys($segment['requirements']))
                ->unique()
                ->all();
            $stockItems = StockItem::query()->whereIn('id', $stockItemIds)->get()->keyBy('id');
            foreach ($segments as $segment) {
                $reservationBatch = (string) Str::uuid();
                foreach ($segment['requirements'] as $stockItemId => $required) {
                    $stockItem = $stockItems->get($stockItemId);
                    if (! $stockItem instanceof StockItem) {
                        throw ValidationException::withMessages([$errorKey => 'A linked stock item could not be found.']);
                    }
                    StockReservation::query()->create([
                        'stock_item_id' => $stockItem->id,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'purpose' => $purpose,
                        'status' => StockReservation::STATUS_ACTIVE,
                        'quantity' => $required,
                        'remaining_quantity' => $required,
                        'unit_cost_snapshot' => $this->replacementCostForStockItem($stockItem),
                        'metadata' => array_merge($metadata, [
                            'product_id' => (int) $product->id,
                            'variant_id' => $variant?->id,
                            'sale_quantity' => $segment['sale_quantity'],
                            'reservation_batch_id' => $reservationBatch,
                            'reservation_units_per_sale' => $required / $segment['sale_quantity'],
                        ]),
                        'reserved_at' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            return $quantity;
        });
    }

    private function releaseForSource(Model $source, Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $this->changeReservationsForSaleQuantity($source, max(0, $quantity), null);
    }

    private function consumeForSource(Model $source, Product $product, ?ProductVariant $variant, int $quantity, string $movementType): void
    {
        $this->changeReservationsForSaleQuantity($source, max(0, $quantity), $movementType);
    }

    private function releaseInvoiceReservations(Invoice $invoice): void
    {
        $lineIds = $invoice->lines()->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $reservations = StockReservation::query()
            ->where('source_type', InvoiceLine::class)
            ->where(function ($query) use ($lineIds, $invoice): void {
                $query->whereIn('source_id', $lineIds)
                    ->orWhere(function ($metadataQuery) use ($invoice): void {
                        $metadataQuery->where('metadata->invoice_id', (string) $invoice->id);
                    });
            })
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $this->releaseReservation($reservation, (float) $reservation->remaining_quantity);
        }
    }

    private function releaseAllForSource(string $sourceType, string $sourceId): void
    {
        $reservations = StockReservation::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $this->releaseReservation($reservation, (float) $reservation->remaining_quantity);
        }
    }

    private function changeReservationsForSaleQuantity(Model $source, int $saleQuantity, ?string $movementType): void
    {
        if ($saleQuantity <= 0) {
            return;
        }

        $sourceType = get_class($source);
        $sourceId = (string) $source->getKey();
        $reservations = StockReservation::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $batches = $reservations->groupBy(function (StockReservation $reservation): string {
            $batchId = trim((string) data_get($reservation->metadata, 'reservation_batch_id'));
            if ($batchId !== '') {
                return $batchId;
            }

            return 'legacy:'.$reservation->reserved_at?->toIso8601String().':'.data_get($reservation->metadata, 'sale_quantity', $reservation->id);
        });

        foreach ($batches as $batch) {
            if ($saleQuantity <= 0) {
                break;
            }

            $rates = $batch->mapWithKeys(function (StockReservation $reservation): array {
                $saleBatchQuantity = max(0, (float) data_get($reservation->metadata, 'sale_quantity', 0));
                $rate = (float) data_get(
                    $reservation->metadata,
                    'reservation_units_per_sale',
                    $saleBatchQuantity > 0 ? (float) $reservation->quantity / $saleBatchQuantity : 1,
                );

                return [$reservation->id => $rate];
            });
            $saleCapacity = $batch->map(function (StockReservation $reservation) use ($rates): float {
                $rate = (float) $rates->get($reservation->id, 0);

                return $rate > 0 ? (float) $reservation->remaining_quantity / $rate : 0;
            })->min();
            if ($saleCapacity === null || $saleCapacity <= 0) {
                continue;
            }

            $changedSales = min($saleQuantity, (int) floor($saleCapacity + 0.0005));
            foreach ($batch as $reservation) {
                $change = min(
                    (float) $reservation->remaining_quantity,
                    $changedSales * (float) $rates->get($reservation->id, 0),
                );
                if ($movementType === null) {
                    $this->releaseReservation($reservation, $change);
                } else {
                    $this->consumeReservation($reservation, $change, $movementType, $source);
                }
            }
            $saleQuantity -= $changedSales;
        }
    }

    private function consumeReservation(
        StockReservation $reservation,
        float $quantity,
        string $movementType,
        Model $source,
        ?User $user = null,
        ?string $notes = null,
    ): void
    {
        if ($quantity <= 0) {
            return;
        }

        $stockItem = StockItem::query()->whereKey($reservation->stock_item_id)->lockForUpdate()->firstOrFail();
        $consume = min($quantity, (float) $reservation->remaining_quantity);
        if ((float) $stockItem->on_hand_quantity + 0.0005 < $consume) {
            throw ValidationException::withMessages(['stock' => 'Stock on hand is lower than the reserved quantity for '.$stockItem->name.'.']);
        }
        $stockItem->on_hand_quantity = (float) $stockItem->on_hand_quantity - $consume;
        $stockItem->save();
        $reservation->remaining_quantity = max(0, (float) $reservation->remaining_quantity - $consume);
        $reservation->status = (float) $reservation->remaining_quantity <= 0.0005
            ? StockReservation::STATUS_CONSUMED
            : StockReservation::STATUS_PARTIALLY_CONSUMED;
        $reservation->consumed_at = (float) $reservation->remaining_quantity <= 0.0005 ? now() : null;
        $reservation->save();
        StockMovement::query()->create([
            'stock_item_id' => $stockItem->id,
            'movement_type' => $movementType,
            'quantity' => -$consume,
            'unit_cost_ex_tax' => $reservation->unit_cost_snapshot,
            'source_type' => get_class($source),
            'source_id' => (string) $source->getKey(),
            'created_by' => $user->id ?? auth()->id(),
            'occurred_at' => now(),
            'notes' => $notes ?? (get_class($source) === StoreOrderItem::class ? 'Store order fulfilment' : 'Stock consumption'),
        ]);
    }

    private function consumeUnreservedStockItem(StockItem $stockItem, float $quantity, Workshop $workshop, ?User $user): void
    {
        if ($quantity <= 0.0005) {
            return;
        }

        $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
        if (! $lockedItem->tracksInventory()) {
            throw ValidationException::withMessages(['actual_used' => $lockedItem->name.' is no longer active.']);
        }

        $reservedQuantity = $lockedItem->shared_workshop_supply
            ? $this->hardReservedQuantityForStockItem($lockedItem)
            : $lockedItem->activeReservedQuantity();
        $unreservedQuantity = max(0, (float) $lockedItem->on_hand_quantity - $reservedQuantity);
        if ($unreservedQuantity + 0.0005 < $quantity) {
            throw ValidationException::withMessages([
                'actual_used' => 'There is not enough unreserved '.$lockedItem->name.' to record that quantity. Another order or workshop may already have it reserved.',
            ]);
        }

        $unitCost = $this->replacementCostForStockItem($lockedItem);
        $lockedItem->on_hand_quantity = max(0, (float) $lockedItem->on_hand_quantity - $quantity);
        $lockedItem->save();

        StockMovement::query()->create([
            'stock_item_id' => $lockedItem->id,
            'movement_type' => StockMovement::TYPE_WORKSHOP,
            'quantity' => -$quantity,
            'unit_cost_ex_tax' => $unitCost,
            'source_type' => Workshop::class,
            'source_id' => (string) $workshop->getKey(),
            'created_by' => $user?->id,
            'occurred_at' => now(),
            'notes' => 'Additional stock used by workshop',
        ]);
    }

    private function releaseReservation(StockReservation $reservation, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }
        $reservation->remaining_quantity = max(0, (float) $reservation->remaining_quantity - $quantity);
        if ((float) $reservation->remaining_quantity <= 0.0005) {
            $reservation->remaining_quantity = 0;
            $reservation->status = StockReservation::STATUS_RELEASED;
            $reservation->released_at = now();
        }
        $reservation->save();
    }

    private function hardReservedQuantityForStockItem(StockItem $stockItem): float
    {
        return (float) $stockItem->activeReservations()
            ->where(function ($query): void {
                $query->whereNull('source_type')
                    ->orWhere('source_type', '!=', Workshop::class);
            })
            ->sum('remaining_quantity');
    }

    /**
     * Shared workshop supplies keep each workshop's full pack quantity visible,
     * while the stock pool carries only the largest compounded forecast.
     * The 10% buffer compounds once for each additional active workshop.
     *
     * @param  Collection<int, StockItem>  $stockItems
     * @param  Collection<int, StockReservation>  $reservations
     * @param  array<int, list<array<string, mixed>>>  $additionalWorkshopReservations
     * @return array<int, float>
     */
    private function effectiveReservedQuantities(
        Collection $stockItems,
        Collection $reservations,
        array $additionalWorkshopReservations = [],
    ): array {
        $itemsById = $stockItems
            ->filter(fn ($item): bool => $item instanceof StockItem)
            ->keyBy(fn (StockItem $item): int => (int) $item->id);
        $rowsByItem = $reservations->groupBy(fn (StockReservation $reservation): int => (int) $reservation->stock_item_id);
        $sharedItemIds = $itemsById
            ->filter(fn (StockItem $item): bool => (bool) $item->shared_workshop_supply)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->all();
        $sharedItemIdLookup = array_fill_keys($sharedItemIds, true);

        $workshopIds = $reservations
            ->filter(fn (StockReservation $reservation): bool =>
                isset($sharedItemIdLookup[(int) $reservation->stock_item_id])
                    && $reservation->source_type === Workshop::class
            )
            ->pluck('source_id')
            ->merge(collect($additionalWorkshopReservations)
                ->flatMap(fn (array $rows): array => array_map(
                    fn (array $row): string => (string) ($row['source_id'] ?? ''),
                    $rows,
                )))
            ->map(fn ($id): string => (string) $id)
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values();
        $workshopStartTimes = $workshopIds->isNotEmpty()
            ? Workshop::query()
                ->whereIn('id', $workshopIds)
                ->get(['id', 'starts_at'])
                ->mapWithKeys(fn (Workshop $workshop): array => [(string) $workshop->getKey() => $workshop->starts_at])
            : collect();

        foreach ($additionalWorkshopReservations as $rows) {
            foreach ($rows as $row) {
                $sourceId = (string) ($row['source_id'] ?? '');
                if ($sourceId !== '' && ! $workshopStartTimes->has($sourceId) && isset($row['starts_at'])) {
                    $workshopStartTimes->put($sourceId, $row['starts_at']);
                }
            }
        }

        $reserved = [];
        foreach ($itemsById as $stockItemId => $stockItem) {
            $rows = $rowsByItem->get((int) $stockItemId, collect());
            $extraRows = collect($additionalWorkshopReservations[(int) $stockItemId] ?? []);
            if (! $stockItem->shared_workshop_supply) {
                $reserved[(int) $stockItemId] = (float) $rows->sum('remaining_quantity')
                    + (float) $extraRows->sum('remaining_quantity');

                continue;
            }

            $hardReservedQuantity = (float) $rows
                ->filter(fn (StockReservation $reservation): bool => $reservation->source_type !== Workshop::class)
                ->sum('remaining_quantity');
            $workshopRows = $rows
                ->filter(fn (StockReservation $reservation): bool => $reservation->source_type === Workshop::class)
                ->concat($extraRows)
                ->filter(fn ($reservation): bool => (float) data_get($reservation, 'remaining_quantity', 0) > 0.0005)
                ->groupBy(fn ($reservation): string => (string) data_get($reservation, 'source_id', ''));
            $events = $workshopRows
                ->map(function (Collection $eventRows, string $sourceId) use ($workshopStartTimes): array {
                    $startsAt = $workshopStartTimes->get($sourceId)
                        ?? data_get($eventRows->first(), 'starts_at')
                        ?? data_get($eventRows->first(), 'reserved_at');

                    return [
                        'source_id' => $sourceId,
                        'quantity' => (float) $eventRows->sum(fn ($reservation): float => (float) data_get($reservation, 'remaining_quantity', 0)),
                        'sort_key' => $this->reservationDateSortKey($startsAt) ?? '9999-12-31 23:59:59',
                    ];
                })
                ->values()
                ->all();
            usort($events, fn (array $left, array $right): int => [$left['sort_key'], $left['source_id']] <=> [$right['sort_key'], $right['source_id']]);

            $sharedWorkshopReserve = 0.0;
            foreach ($events as $event) {
                $bufferedReserve = $sharedWorkshopReserve > 0.0005
                    ? $stockItem->roundStockIssueQuantity(
                        $sharedWorkshopReserve * (1 + StockItem::SHARED_WORKSHOP_RESERVATION_BUFFER_PERCENT),
                    )
                    : 0;
                $sharedWorkshopReserve = max((float) $event['quantity'], $bufferedReserve);
            }

            $reserved[(int) $stockItemId] = $hardReservedQuantity + $sharedWorkshopReserve;
        }

        return $reserved;
    }

    private function reservationDateSortKey(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u');
        }
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    /**
     * @return array{items: Collection<int, StockItem>, reserved: array<int, float>, reservations: Collection<int, StockReservation>}
     */
    private function inventoryState(
        ?array $stockItemIds = null,
        bool $lockForUpdate = false,
        ?array $excludedReservationSource = null,
    ): array
    {
        $itemsQuery = StockItem::query()
            ->when(! $lockForUpdate, fn ($query) => $query->with(['kitComponents.component']))
            ->when($stockItemIds !== null, fn ($query) => $query->whereIn('id', $stockItemIds));
        if ($lockForUpdate) {
            $itemsQuery->orderBy('id')->lockForUpdate();
        }
        $items = $itemsQuery->get()->keyBy('id');
        $reservedRows = $items->isEmpty()
            ? collect()
            : StockReservation::query()
                ->whereIn('stock_item_id', $items->keys())
                ->whereIn('status', StockReservation::ACTIVE_STATUSES)
                ->when($excludedReservationSource !== null, fn ($query) => $query->where(function ($sourceQuery) use ($excludedReservationSource): void {
                    $sourceQuery
                        ->where('source_type', '!=', (string) ($excludedReservationSource['type'] ?? ''))
                        ->orWhere('source_id', '!=', (string) ($excludedReservationSource['id'] ?? ''));
                }))
                ->when($lockForUpdate, fn ($query) => $query->orderBy('id')->lockForUpdate())
                ->get(['stock_item_id', 'source_type', 'source_id', 'remaining_quantity', 'reserved_at']);
        $reserved = $this->effectiveReservedQuantities($items, $reservedRows);

        return ['items' => $items, 'reserved' => $reserved, 'reservations' => $reservedRows];
    }

    /** @param Collection<int, StockItem> $roots
     *  @return array{items: Collection<int, StockItem>, reserved: array<int, float>, reservations: Collection<int, StockReservation>}
     */
    private function lockInventoryTree(Collection $roots, ?array $excludedReservationSource = null): array
    {
        $stockItemIds = $roots
            ->filter(fn ($item): bool => $item instanceof StockItem)
            ->map(fn (StockItem $item): int => (int) $item->id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        while ($stockItemIds !== []) {
            $state = $this->inventoryState($stockItemIds, true, $excludedReservationSource);
            $components = StockItemComponent::query()
                ->whereIn('kit_stock_item_id', $stockItemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $childIds = $components
                ->pluck('component_stock_item_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $newIds = array_values(array_diff($childIds, $stockItemIds));
            if ($newIds !== []) {
                $stockItemIds = array_values(array_unique([...$stockItemIds, ...$newIds]));
                sort($stockItemIds);

                continue;
            }

            $componentsByKit = $components->groupBy('kit_stock_item_id');
            foreach ($state['items'] as $stockItem) {
                $kitComponents = $componentsByKit->get($stockItem->id, collect());
                foreach ($kitComponents as $component) {
                    $component->setRelation('component', $state['items']->get((int) $component->component_stock_item_id));
                }
                $stockItem->setRelation('kitComponents', $kitComponents);
            }

            return $state;
        }

        return ['items' => collect(), 'reserved' => [], 'reservations' => collect()];
    }

    /** @param array<int, float> $allocated */
    private function availableStockInState(StockItem $stockItem, array $allocated, array $state): float
    {
        if (! $stockItem->tracksInventory()) {
            return 0;
        }

        return max(
            0,
            (float) $stockItem->on_hand_quantity
                - (float) ($state['reserved'][$stockItem->id] ?? 0)
                - (float) ($allocated[$stockItem->id] ?? 0),
        );
    }

    /**
     * Consumes ready-made stock first, then plans the components needed to build the remainder.
     * The shared $allocated map lets cart lines and workshop pick-list rows use one inventory pool.
     *
     * @param array<int, float> $allocated
     * @param array<int, bool> $stack
     */
    private function planStockItemUsage(StockItem $stockItem, float $quantity, array &$allocated, array $state, array $stack = []): bool
    {
        $quantity = max(0, $quantity);
        if ($quantity <= 0.0005) {
            return true;
        }

        $stockItemId = (int) $stockItem->id;
        $stockItem = $state['items']->get($stockItemId) ?? $stockItem;
        if (! $stockItem->tracksInventory() || isset($stack[$stockItemId])) {
            return false;
        }

        $before = $allocated;
        $readyQuantity = min($quantity, $this->availableStockInState($stockItem, $allocated, $state));
        if ($readyQuantity > 0.0005) {
            $allocated[$stockItemId] = (float) ($allocated[$stockItemId] ?? 0) + $readyQuantity;
        }
        $remaining = $quantity - $readyQuantity;
        if ($remaining <= 0.0005) {
            return true;
        }

        $components = $stockItem->is_kit ? $this->componentsFor($stockItem) : collect();
        if ($components->isEmpty()) {
            $allocated = $before;

            return false;
        }

        $stack[$stockItemId] = true;
        foreach ($components as $component) {
            $componentItem = $state['items']->get((int) $component->component_stock_item_id);
            if (! $componentItem instanceof StockItem
                || ! $this->planStockItemUsage(
                    $componentItem,
                    $remaining * (float) $component->quantity,
                    $allocated,
                    $state,
                    $stack,
                )) {
                $allocated = $before;

                return false;
            }
        }

        return true;
    }

    /** @param array<int, float> $allocatedStock */
    private function maximumAvailableUnits(StockItem $stockItem, float $quantityPerUnit, array $state, array $allocatedStock): int
    {
        $quantityPerUnit = max(0.001, $quantityPerUnit);
        $canPlan = function (int $units) use ($stockItem, $quantityPerUnit, $state, $allocatedStock): bool {
            $used = $allocatedStock;

            return $this->planStockItemUsage($stockItem, $units * $quantityPerUnit, $used, $state);
        };

        $low = 0;
        $high = 1;
        while ($high < 1_000_000 && $canPlan($high)) {
            $low = $high;
            $high = min(1_000_000, $high * 2);
        }

        while ($high - $low > 1) {
            $middle = intdiv($low + $high, 2);
            if ($canPlan($middle)) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * @return array<int, array{sale_quantity: int, requirements: array<int, float>}>|null
     */
    private function planStockItemSegments(StockItem $stockItem, float $quantityPerSale, int $saleQuantity, array $state): ?array
    {
        $used = [];
        $segments = [];
        $lastKey = null;

        for ($sale = 0; $sale < $saleQuantity; $sale++) {
            $before = $used;
            if (! $this->planStockItemUsage($stockItem, $quantityPerSale, $used, $state)) {
                return null;
            }

            $requirements = [];
            foreach ($used as $stockItemId => $total) {
                $delta = round((float) $total - (float) ($before[$stockItemId] ?? 0), 6);
                if ($delta > 0.0005) {
                    $requirements[(int) $stockItemId] = $delta;
                }
            }
            ksort($requirements);
            $segmentKey = serialize($requirements);
            if ($segments !== [] && $segmentKey === $lastKey) {
                $lastIndex = array_key_last($segments);
                $segments[$lastIndex]['sale_quantity']++;
                foreach ($requirements as $id => $required) {
                    $segments[$lastIndex]['requirements'][$id] += $required;
                }
            } else {
                $segments[] = ['sale_quantity' => 1, 'requirements' => $requirements];
                $lastKey = $segmentKey;
            }
        }

        return $segments;
    }

    /** @param array<int, bool> $seen */
    private function replacementCostInTree(StockItem $stockItem, array $seen): ?float
    {
        if (isset($seen[$stockItem->id])) {
            return null;
        }
        if (! $stockItem->is_kit) {
            return $stockItem->replacement_unit_cost_ex_tax === null
                ? null
                : max(0, (float) $stockItem->replacement_unit_cost_ex_tax);
        }

        $components = $this->componentsFor($stockItem);
        if ($components->isEmpty()) {
            return null;
        }

        $seen[$stockItem->id] = true;
        $total = 0.0;
        foreach ($components as $component) {
            $componentItem = $component->component;
            if (! $componentItem instanceof StockItem) {
                return null;
            }
            $cost = $this->replacementCostInTree($componentItem, $seen);
            if ($cost === null) {
                return null;
            }
            $total += $cost * (float) $component->quantity;
        }

        return round($total, 4);
    }

    /** @param Collection<int, StockItem> $items @param array<int, bool> $seen */
    private function appendStockItemTree(StockItem $stockItem, Collection $items, array $state, array $seen): void
    {
        if (isset($seen[$stockItem->id])) {
            return;
        }
        $stockItem = $state['items']->get((int) $stockItem->id) ?? $stockItem;
        $items->push($stockItem);
        if (! $stockItem->is_kit) {
            return;
        }

        $seen[$stockItem->id] = true;
        foreach ($this->componentsFor($stockItem) as $component) {
            if ($component->component instanceof StockItem) {
                $this->appendStockItemTree($component->component, $items, $state, $seen);
            }
        }
    }

}
