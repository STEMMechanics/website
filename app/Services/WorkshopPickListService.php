<?php

namespace App\Services;

use App\Models\PickListTemplateItem;
use App\Models\StockItem;
use App\Models\StockItemComponent;
use App\Models\StockReservation;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * @phpstan-type KitRecipePart array{stock_item_id: int, stock_item_name: string, stock_unit: string, quantity_per_kit: float, depth: int, parent_name: string, note: ?string, is_kit: bool}
 * @phpstan-type CalculatedPickItem array{item_id: int, item_name: string, stock_item_id: ?int, stock_quantity_total: float, quantity: int, quantity_text: string, type_note: string, kit_contents: list<array{stock_item_id: int, stock_item_name: string, stock_unit: string, quantity: float, depth: int, parent_name: string, note: ?string, is_kit: bool}>}
 * @phpstan-type WorkshopKitSummary array{key: string, item_id: int, stock_item_id: int, item_name: string, admin_url: ?string, required: float, ready_made: float, to_assemble: float, no_recipe: bool, is_kit: bool, contents: list<array<string, mixed>>}
 * @phpstan-type ShelfPickRow array{key: string, stock_item_id: ?int, item_name: string, admin_url: ?string, unit: ?string, quantity: float, kind: string, sources: list<array{item_id: int, label: string, quantity: float}>}
 */
class WorkshopPickListService
{
    /**
     * @return array{
     *     participants: int,
     *     resolvedItems: Collection<int, array{id: int, item_name: string, stock_item_id: ?int, stock_quantity: ?float, quantity_type: string, quantity_value: int, sort_order: int}>,
     *     calculatedItems: Collection<int, CalculatedPickItem>,
     *     kitContentsByStockItem: array<int, list<KitRecipePart>>,
     *     pickListNotes: string
     * }
     */
    public function build(Workshop $workshop, ?int $participantsOverride = null): array
    {
        $participants = $participantsOverride !== null
            ? max(1, $participantsOverride)
            : $this->resolvedParticipants($workshop);
        $resolvedItems = $this->resolvedPickListItems($workshop);
        $kitContentsByStockItem = $this->kitContentsForItems($resolvedItems);

        return [
            'participants' => $participants,
            'resolvedItems' => $resolvedItems,
            'calculatedItems' => $this->buildCalculatedItems($resolvedItems, $participants, $kitContentsByStockItem),
            'kitContentsByStockItem' => $kitContentsByStockItem,
            'pickListNotes' => $this->pickListNotes($workshop),
        ];
    }

    /**
     * Estimate the raw stock represented by the pick list when no reservation
     * was captured for the workshop. Kits with recipes are expanded to their
     * component stock; kits without recipes remain a stock item themselves.
     *
     * @return Collection<int, array{stock_item: StockItem, planned_quantity: float, sources: list<string>}>
     */
    public function plannedStockForReconciliation(Workshop $workshop): Collection
    {
        $pickList = $this->build($workshop);
        $calculatedItems = $pickList['calculatedItems'];
        $parts = collect($pickList['kitContentsByStockItem'])->flatten(1);
        $stockItemIds = $calculatedItems->pluck('stock_item_id')
            ->merge($parts->pluck('stock_item_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($stockItemIds->isEmpty()) {
            return collect();
        }

        $stockItems = StockItem::query()
            ->with('group')
            ->whereIn('id', $stockItemIds->all())
            ->where('status', StockItem::STATUS_ACTIVE)
            ->get()
            ->keyBy(fn (StockItem $item): int => (int) $item->id);
        $kitsWithRecipes = StockItemComponent::query()
            ->whereIn('kit_stock_item_id', $stockItemIds->all())
            ->distinct()
            ->pluck('kit_stock_item_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true]);
        $quantities = [];
        $sourcesByStockItem = [];
        $materialBatches = [];

        $addQuantity = function (
            int $stockItemId,
            float $quantity,
            string $source,
            ?float $units = null,
            ?float $quantityPerUnit = null,
        ) use (&$quantities, &$sourcesByStockItem, &$materialBatches): void {
            if ($stockItemId > 0 && $quantity > 0.0005) {
                $quantities[$stockItemId] = (float) ($quantities[$stockItemId] ?? 0) + $quantity;
                $sourcesByStockItem[$stockItemId][] = $source;
                if ($units !== null && $quantityPerUnit !== null && $units > 0 && $quantityPerUnit > 0) {
                    $materialBatches[$stockItemId][] = [
                        'units' => $units,
                        'quantity_per_unit' => $quantityPerUnit,
                    ];
                }
            }
        };

        foreach ($calculatedItems as $item) {
            $stockItemId = (int) ($item['stock_item_id'] ?? 0);
            $stockItem = $stockItems->get($stockItemId);
            $quantity = max(0.0, (float) ($item['stock_quantity_total'] ?? 0));
            if (! $stockItem instanceof StockItem || $quantity <= 0.0005) {
                continue;
            }

            if (! $stockItem->is_kit) {
                $units = max(0, (float) ($item['quantity'] ?? 0));
                $quantityPerUnit = $units > 0.0005 ? $quantity / $units : $quantity;
                $addQuantity($stockItemId, $quantity, (string) $item['item_name'], $units > 0.0005 ? $units : 1, $quantityPerUnit);

                continue;
            }

            $kitParts = collect($pickList['kitContentsByStockItem'][$stockItemId] ?? []);
            $partsForKit = $kitParts
                ->filter(fn (array $part): bool => ! (bool) ($part['is_kit'] ?? false)
                    || ! $kitsWithRecipes->has((int) ($part['stock_item_id'] ?? 0)));

            if ($partsForKit->isEmpty()) {
                $addQuantity($stockItemId, $quantity, (string) $item['item_name']);

                continue;
            }

            foreach ($partsForKit as $part) {
                $rootLabel = $stockItem->formatQuantity($quantity).' '.trim((string) $item['item_name']);
                $parentName = trim((string) ($part['parent_name'] ?? ''));
                $parentRecipe = $parentName !== ''
                    ? $kitParts->first(fn (array $candidate): bool => (bool) ($candidate['is_kit'] ?? false)
                        && (int) ($candidate['depth'] ?? -1) === max(0, (int) ($part['depth'] ?? 0) - 1)
                        && strcasecmp((string) ($candidate['stock_item_name'] ?? ''), $parentName) === 0)
                    : null;
                $parentStockItem = $parentRecipe
                    ? $stockItems->get((int) ($parentRecipe['stock_item_id'] ?? 0))
                    : null;
                $parentLabel = $parentRecipe && $parentStockItem instanceof StockItem
                    ? $parentStockItem->formatQuantity($quantity * (float) ($parentRecipe['quantity_per_kit'] ?? 0)).' '.$parentName
                    : $parentName;
                $source = $parentLabel !== '' && strcasecmp($parentLabel, $rootLabel) !== 0
                    ? $parentLabel.' › '.$rootLabel
                    : $rootLabel;
                $quantityPerKit = max(0, (float) ($part['quantity_per_kit'] ?? 0));
                $addQuantity(
                    (int) ($part['stock_item_id'] ?? 0),
                    $quantity * $quantityPerKit,
                    $source,
                    $quantity,
                    $quantityPerKit,
                );
            }
        }

        return collect($quantities)
            ->map(function (float $quantity, int|string $stockItemId) use ($stockItems, $sourcesByStockItem, $materialBatches): ?array {
                $stockItem = $stockItems->get((int) $stockItemId);
                if (! $stockItem instanceof StockItem) {
                    return null;
                }

                $estimatedUsage = $quantity;
                if (! $stockItem->is_kit && isset($materialBatches[(int) $stockItemId])) {
                    $estimatedUsage = 0;
                    foreach ($materialBatches[(int) $stockItemId] as $batch) {
                        $estimatedUsage += StockItem::estimateMaterialUse(
                            (float) $batch['units'],
                            (float) $batch['quantity_per_unit'],
                            $stockItem->stockIssueIncrement(),
                        );
                    }
                }

                $plannedQuantity = $stockItem->roundStockIssueQuantity($estimatedUsage);
                if ($plannedQuantity <= 0.0005) {
                    return null;
                }

                return [
                    'stock_item' => $stockItem,
                    'planned_quantity' => $plannedQuantity,
                    'sources' => collect($sourcesByStockItem[(int) $stockItemId] ?? [])
                        ->unique()
                        ->reject(fn (string $source): bool => strcasecmp($source, $stockItem->linkLabel()) === 0)
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row): string => $row['stock_item']->linkLabel(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Build one hierarchical checklist for workshop kits and standalone materials.
     *
     * @return array{
     *     rows: list<array{key: string, stock_item_id: ?int, item_name: string, unit: ?string, quantity: float, kind: string, sources: list<array{item_id: int, label: string, quantity: float}>}>,
     *     kit_summaries: list<WorkshopKitSummary>
     * }
     */
    public function buildShelfPickList(Workshop $workshop, ?int $participantsOverride = null): array
    {
        $calculatedItems = $this->build($workshop, $participantsOverride)['calculatedItems'];
        $components = StockItemComponent::query()
            ->with('component.group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $componentsByKit = $components->groupBy('kit_stock_item_id');
        $stockItemIds = $calculatedItems->pluck('stock_item_id')
            ->merge($components->flatMap(fn (StockItemComponent $component): array => [
                (int) $component->kit_stock_item_id,
                (int) $component->component_stock_item_id,
            ]))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $stockItems = StockItem::query()
            ->with('group')
            ->whereIn('id', $stockItemIds->all())
            ->get()
            ->keyBy(fn (StockItem $item): int => (int) $item->id);
        $reservedByStockItem = StockReservation::query()
            ->where('source_type', Workshop::class)
            ->where('source_id', (string) $workshop->getKey())
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->get(['stock_item_id', 'remaining_quantity'])
            ->groupBy('stock_item_id')
            ->map(fn (Collection $reservations): float => (float) $reservations->sum('remaining_quantity'))
            ->all();

        $rows = [];
        $materialBatchesByStockItem = [];
        $kitSummaries = [];
        $addPhysicalRow = function (
            ?StockItem $stockItem,
            string $itemName,
            float $quantity,
            int $sourceItemId,
            string $sourceLabel,
            bool $manual = false,
            ?float $batchUnits = null,
            ?float $quantityPerUnit = null,
        ) use (&$rows, &$materialBatchesByStockItem): void {
            if ($quantity <= 0.0005 || trim($itemName) === '') {
                return;
            }

            $stockItemId = $stockItem instanceof StockItem ? (int) $stockItem->id : null;
            $key = $manual || $stockItemId === null
                ? 'manual:'.$sourceItemId
                : 'stock:'.$stockItemId;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'key' => $key,
                    'stock_item_id' => $stockItemId,
                    'item_name' => $stockItem instanceof StockItem ? $stockItem->linkLabel() : $itemName,
                    'admin_url' => $stockItem instanceof StockItem
                        ? route($stockItem->is_kit ? 'admin.shop.stock.kit.edit' : 'admin.shop.stock.edit', $stockItem)
                        : null,
                    'unit' => $stockItem instanceof StockItem ? (string) $stockItem->unit : null,
                    'quantity' => 0.0,
                    'kind' => $manual || $stockItemId === null
                        ? 'manual'
                        : (($stockItem instanceof StockItem && $stockItem->is_kit) ? 'kit' : 'stock'),
                    'sources' => [],
                ];
            }

            $rows[$key]['quantity'] += $quantity;
            if ($stockItem instanceof StockItem
                && ! $stockItem->is_kit
                && $batchUnits !== null
                && $quantityPerUnit !== null
                && $batchUnits > 0
                && $quantityPerUnit > 0) {
                $materialBatchesByStockItem[(int) $stockItem->id][] = [
                    'units' => $batchUnits,
                    'quantity_per_unit' => $quantityPerUnit,
                ];
            }
            $sourceKey = $sourceItemId.'|'.$sourceLabel;
            if (! isset($rows[$key]['sources'][$sourceKey])) {
                $rows[$key]['sources'][$sourceKey] = [
                    'item_id' => $sourceItemId,
                    'label' => $sourceLabel,
                    'quantity' => 0.0,
                ];
            }
            $rows[$key]['sources'][$sourceKey]['quantity'] += $quantity;
        };

        $nextKitChecklistId = 0;
        $allocate = function (
            int $stockItemId,
            float $quantity,
            int $sourceItemId,
            array $stack,
            ?string $recipeNote = null,
        ) use (&$allocate, &$reservedByStockItem, &$nextKitChecklistId, $componentsByKit, $stockItems): array {
            $stockItem = $stockItems->get($stockItemId);
            $readyQuantity = 0.0;
            if ($stockItem instanceof StockItem && $stockItem->tracksInventory()) {
                $readyQuantity = min($quantity, max(0.0, (float) ($reservedByStockItem[$stockItemId] ?? 0)));
                $reservedByStockItem[$stockItemId] = max(0.0, (float) ($reservedByStockItem[$stockItemId] ?? 0) - $readyQuantity);
            }
            $remaining = max(0.0, $quantity - $readyQuantity);
            $kitName = $stockItem instanceof StockItem ? $stockItem->linkLabel() : 'Missing stock item';
            $node = [
                'key' => 'kit:'.$sourceItemId.':'.$stockItemId.':'.$nextKitChecklistId++,
                'item_id' => $sourceItemId,
                'stock_item_id' => $stockItemId,
                'item_name' => $kitName,
                'admin_url' => $stockItem instanceof StockItem ? route('admin.shop.stock.kit.edit', $stockItem) : null,
                'required' => round($quantity, 3),
                'ready_made' => round($readyQuantity, 3),
                'to_assemble' => round($remaining, 3),
                'no_recipe' => false,
                'is_kit' => true,
                'note' => trim((string) $recipeNote) ?: null,
                'contents' => [],
            ];

            if (! $stockItem instanceof StockItem || ! $stockItem->is_kit || isset($stack[$stockItemId])) {
                $node['no_recipe'] = true;

                return $node;
            }

            if ($remaining <= 0.0005) {
                return $node;
            }

            $kitComponents = $componentsByKit->get($stockItemId, collect())
                ->filter(fn (StockItemComponent $component): bool => $component->component instanceof StockItem
                    && (float) $component->quantity > 0)
                ->values();
            if ($kitComponents->isEmpty()) {
                $node['no_recipe'] = true;

                return $node;
            }

            $stack[$stockItemId] = true;
            $leafIndexes = [];
            foreach ($kitComponents as $componentRecipe) {
                $component = $componentRecipe->component;
                if (! $component instanceof StockItem) {
                    continue;
                }

                if ($component->is_kit) {
                    $node['contents'][] = $allocate(
                        (int) $component->id,
                        $remaining * (float) $componentRecipe->quantity,
                        $sourceItemId,
                        $stack,
                        $componentRecipe->note,
                    );

                    continue;
                }

                $leafKey = (string) $component->id;
                if (! array_key_exists($leafKey, $leafIndexes)) {
                    $leafIndexes[$leafKey] = count($node['contents']);
                    $node['contents'][] = [
                        'stock_item_id' => (int) $component->id,
                        'item_name' => $component->linkLabel(),
                        'admin_url' => route($component->is_kit ? 'admin.shop.stock.kit.edit' : 'admin.shop.stock.edit', $component),
                        'unit' => (string) $component->unit,
                        'quantity' => 0.0,
                        'notes' => [],
                        'is_kit' => false,
                        'material_batches' => [],
                    ];
                }
                $quantityPerKit = (float) $componentRecipe->quantity;
                $node['contents'][$leafIndexes[$leafKey]]['quantity'] += $remaining * $quantityPerKit;
                $node['contents'][$leafIndexes[$leafKey]]['material_batches'][] = [
                    'units' => $remaining,
                    'quantity_per_unit' => $quantityPerKit,
                ];
                $recipeNote = trim((string) $componentRecipe->note);
                if ($recipeNote !== '' && ! in_array($recipeNote, $node['contents'][$leafIndexes[$leafKey]]['notes'], true)) {
                    $node['contents'][$leafIndexes[$leafKey]]['notes'][] = $recipeNote;
                }
            }

            foreach ($node['contents'] as &$child) {
                if (($child['is_kit'] ?? false) === true) {
                    continue;
                }

                $component = $stockItems->get((int) $child['stock_item_id']);
                if ($component instanceof StockItem) {
                    $estimatedUsage = 0;
                    foreach ($child['material_batches'] ?? [] as $batch) {
                        $estimatedUsage += StockItem::estimateMaterialUse(
                            (float) $batch['units'],
                            (float) $batch['quantity_per_unit'],
                            $component->stockIssueIncrement(),
                        );
                    }
                    $child['quantity'] = $component->roundStockIssueQuantity(
                        $estimatedUsage > 0 ? $estimatedUsage : (float) $child['quantity'],
                    );
                } else {
                    $child['quantity'] = round((float) $child['quantity'], 3);
                }
                unset($child['material_batches']);
            }
            unset($child);

            return $node;
        };

        foreach ($calculatedItems as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            $itemName = trim((string) ($item['item_name'] ?? ''));
            $stockItemId = (int) ($item['stock_item_id'] ?? 0);
            $quantity = $stockItemId > 0
                ? max(0.0, (float) ($item['stock_quantity_total'] ?? 0))
                : max(0.0, (float) ($item['quantity'] ?? 0));
            if ($itemId <= 0 || $quantity <= 0.0005 || $itemName === '') {
                continue;
            }

            if ($stockItemId <= 0) {
                $addPhysicalRow(null, $itemName, $quantity, $itemId, 'Manual: '.$itemName, true);
                continue;
            }

            $stockItem = $stockItems->get($stockItemId);
            if ($stockItem instanceof StockItem && $stockItem->is_kit) {
                $kitSummaries[] = $allocate($stockItemId, $quantity, $itemId, []);
            } else {
                $isManual = ! ($stockItem instanceof StockItem);
                $units = max(0, (float) ($item['quantity'] ?? 0));
                $quantityPerUnit = $units > 0.0005 ? $quantity / $units : $quantity;
                $addPhysicalRow(
                    $stockItem instanceof StockItem ? $stockItem : null,
                    $itemName,
                    $quantity,
                    $itemId,
                    'Direct: '.$itemName,
                    $isManual,
                    $units > 0.0005 ? $units : 1,
                    $quantityPerUnit,
                );
            }
        }

        $normalizedRows = collect($rows)
            ->map(function (array $row) use ($stockItems, $materialBatchesByStockItem): array {
                $stockItem = isset($row['stock_item_id']) ? $stockItems->get((int) $row['stock_item_id']) : null;
                if ($stockItem instanceof StockItem) {
                    $estimatedUsage = (float) $row['quantity'];
                    $batches = $materialBatchesByStockItem[(int) $stockItem->id] ?? [];
                    if (! $stockItem->is_kit && $batches !== []) {
                        $estimatedUsage = 0;
                        foreach ($batches as $batch) {
                            $estimatedUsage += StockItem::estimateMaterialUse(
                                (float) $batch['units'],
                                (float) $batch['quantity_per_unit'],
                                $stockItem->stockIssueIncrement(),
                            );
                        }
                    }
                    $row['quantity'] = $stockItem->roundStockIssueQuantity($estimatedUsage);
                } else {
                    $row['quantity'] = round((float) $row['quantity'], 3);
                }
                $row['sources'] = collect($row['sources'])
                    ->map(function (array $source): array {
                        $source['quantity'] = round((float) $source['quantity'], 3);

                        return $source;
                    })
                    ->values()
                    ->all();

                return $row;
            })
            ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return ['rows' => $normalizedRows, 'kit_summaries' => $kitSummaries];
    }

    /**
     * @param list<int|string> $checkedItemIds
     * @param array{rows: list<ShelfPickRow>, kit_summaries: list<WorkshopKitSummary>} $shelfPickList
     * @return list<string>
     */
    public function normalizeCheckedItemIds(array $checkedItemIds, array $shelfPickList): array
    {
        $validRows = collect($shelfPickList['rows'])
            ->keyBy('key');
        foreach ($shelfPickList['kit_summaries'] as $kit) {
            if (isset($kit['key'])) {
                $validRows->put((string) $kit['key'], $kit);
            }
        }
        $selectedRows = [];
        $legacyItemIds = [];

        foreach ($checkedItemIds as $checkedItemId) {
            if (! is_string($checkedItemId) && ! is_int($checkedItemId)) {
                continue;
            }

            $value = trim((string) $checkedItemId);
            if ($value === '') {
                continue;
            }
            if ($validRows->has($value)) {
                $selectedRows[$value] = true;
                continue;
            }
            if (preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
                $legacyItemIds[(int) $value] = true;
            }
        }

        if ($legacyItemIds !== []) {
            foreach ($shelfPickList['rows'] as $row) {
                $sourceIds = collect($row['sources'])
                    ->pluck('item_id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();
                if ($sourceIds->isNotEmpty() && $sourceIds->every(fn (int $id): bool => isset($legacyItemIds[$id]))) {
                    $selectedRows[(string) $row['key']] = true;
                }
            }
        }

        return array_keys($selectedRows);
    }

    /**
     * @param Collection<int, array{id: int, item_name: string, stock_item_id: ?int, stock_quantity: ?float, quantity_type: string, quantity_value: int, sort_order: int}> $items
     * @return array<int, list<KitRecipePart>>
     */
    public function kitContentsForItems(Collection $items): array
    {
        $stockItemIds = $items->pluck('stock_item_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($stockItemIds->isEmpty()) {
            return [];
        }

        $kitIds = StockItem::query()
            ->whereIn('id', $stockItemIds->all())
            ->where('is_kit', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        if ($kitIds === []) {
            return [];
        }

        $componentsByKit = StockItemComponent::query()
            ->with('component.group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('kit_stock_item_id');
        $recipes = [];

        foreach ($kitIds as $kitId) {
            $parts = [];
            $expand = function (int $currentKitId, float $quantity, array $stack, int $depth = 0, string $parentName = '') use (&$expand, &$parts, $componentsByKit): void {
                if (isset($stack[$currentKitId])) {
                    return;
                }
                $stack[$currentKitId] = true;

                foreach ($componentsByKit->get($currentKitId, collect()) as $componentRecipe) {
                    $component = $componentRecipe->component;
                    if (! $component instanceof StockItem) {
                        continue;
                    }

                    $stockQuantity = $quantity * (float) $componentRecipe->quantity;
                    if ($stockQuantity <= 0) {
                        continue;
                    }

                    $parts[] = [
                        'stock_item_id' => (int) $component->id,
                        'stock_item_name' => $component->linkLabel(),
                        'stock_unit' => (string) $component->unit,
                        'quantity_per_kit' => $stockQuantity,
                        'depth' => $depth,
                        'parent_name' => $parentName,
                        'note' => trim((string) $componentRecipe->note) ?: null,
                        'is_kit' => (bool) $component->is_kit,
                    ];

                    if ($component->is_kit) {
                        $expand((int) $component->id, $stockQuantity, $stack, $depth + 1, $component->linkLabel());
                    }
                }
            };

            $expand($kitId, 1.0, [], 0, '');
            $recipes[$kitId] = $parts;
        }

        return $recipes;
    }

    /**
     * @param  Collection<int, Workshop>  $workshops
     * @return array{
     *     workshopSummaries: Collection<int, array{workshop: Workshop, participants: int, calculatedItems: Collection<int, CalculatedPickItem>, pickListNotes: string}>,
     *     materialRows: Collection<int, array{item_name: string, stock_item_id: ?int, total_quantity: float, workshopBreakdowns: Collection<int, array{workshop_id: string, workshop_title: string, starts_at_label: string, quantity: float}>}>,
     *     totalQuantity: float,
     *     uniqueItemCount: int
     * }
     */
    public function buildMonthMaterials(Collection $workshops): array
    {
        $workshopSummaries = $workshops
            ->map(function (Workshop $workshop): array {
                $summary = $this->build($workshop);

                return [
                    'workshop' => $workshop,
                    'participants' => $summary['participants'],
                    'calculatedItems' => $summary['calculatedItems'],
                    'pickListNotes' => $summary['pickListNotes'],
                ];
            })
            ->values();

        $materials = [];
        $totalQuantity = 0.0;

        foreach ($workshopSummaries as $summary) {
            $workshop = $summary['workshop'];
            $workshopId = (string) $workshop->getKey();
            $workshopTitle = trim((string) ($workshop->title ?? 'Workshop'));
            $startsAtLabel = $workshop->starts_at?->format('D j M g:ia') ?? '-';

            foreach ($summary['calculatedItems'] as $item) {
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    continue;
                }

                $stockItemId = (int) ($item['stock_item_id'] ?? 0);
                $quantity = $stockItemId > 0
                    ? max(0.0, (float) ($item['stock_quantity_total'] ?? $item['quantity'] ?? 0))
                    : max(0.0, (float) ($item['quantity'] ?? 0));
                $totalQuantity += $quantity;

                $key = $stockItemId > 0 ? 'stock:'.$stockItemId : Str::lower($itemName);
                if (! array_key_exists($key, $materials)) {
                    $materials[$key] = [
                        'item_name' => $itemName,
                        'stock_item_id' => $stockItemId > 0 ? $stockItemId : null,
                        'total_quantity' => 0.0,
                        'workshopBreakdowns' => [],
                    ];
                }

                $materials[$key]['total_quantity'] += $quantity;
                if (! array_key_exists($workshopId, $materials[$key]['workshopBreakdowns'])) {
                    $materials[$key]['workshopBreakdowns'][$workshopId] = [
                        'workshop_id' => $workshopId,
                        'workshop_title' => $workshopTitle,
                        'starts_at_label' => $startsAtLabel,
                        'quantity' => 0.0,
                        'sort' => $workshop->starts_at?->getTimestamp() ?? 0,
                    ];
                }

                $materials[$key]['workshopBreakdowns'][$workshopId]['quantity'] += $quantity;
            }
        }

        $materialRows = collect($materials)
            ->map(function (array $row): array {
                $row['workshopBreakdowns'] = collect($row['workshopBreakdowns'])
                    ->sortBy([['sort', 'asc'], ['workshop_title', 'asc']])
                    ->map(function (array $breakdown): array {
                        unset($breakdown['sort']);

                        return $breakdown;
                    })
                    ->values();

                /** @var array{item_name: string, stock_item_id: ?int, total_quantity: float, workshopBreakdowns: Collection<int, array{workshop_id: string, workshop_title: string, starts_at_label: string, quantity: float}>} $row */
                return $row;
            })
            ->sortBy('item_name')
            ->values();

        return [
            'workshopSummaries' => $workshopSummaries,
            'materialRows' => $materialRows,
            'totalQuantity' => $totalQuantity,
            'uniqueItemCount' => $materialRows->count(),
        ];
    }

    /**
     * @return array<int, array{id: int, item_name: string, stock_item_id: ?int, stock_quantity: ?float, quantity_type: string, quantity_value: int, sort_order: int}>
     */
    public function normalizePickListItems(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    'pick_list_custom_items' => 'Custom pick list items could not be parsed.',
                ]);
            }
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            throw ValidationException::withMessages([
                'pick_list_custom_items' => 'Custom pick list items format is invalid.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'pick_list_custom_items' => 'Custom pick list items format is invalid.',
            ]);
        }

        $stockItemIds = collect($decoded)
            ->map(fn ($row): int => is_array($row) ? (int) ($row['stock_item_id'] ?? 0) : 0)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $stockItems = $stockItemIds->isNotEmpty()
            ? StockItem::query()->with('group')->whereIn('id', $stockItemIds)->get()->keyBy('id')
            : collect();

        $normalized = [];
        $usedIds = [];
        $nextId = 1;

        foreach ($decoded as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $stockItemId = isset($row['stock_item_id']) && (int) $row['stock_item_id'] > 0 ? (int) $row['stock_item_id'] : null;
            $stockItem = $stockItemId !== null ? $stockItems->get($stockItemId) : null;
            $itemName = $stockItem instanceof StockItem
                ? $stockItem->linkLabel()
                : trim((string) ($row['item_name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $quantityType = (string) ($row['quantity_type'] ?? PickListTemplateItem::TYPE_PER_PARTICIPANT);
            if (! in_array($quantityType, PickListTemplateItem::TYPES, true)) {
                $quantityType = PickListTemplateItem::TYPE_PER_PARTICIPANT;
            }

            $quantityValue = max(1, (int) ($row['quantity_value'] ?? 1));
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($itemId <= 0 || in_array($itemId, $usedIds, true)) {
                $itemId = $nextId;
            }

            $nextId = max($nextId + 1, $itemId + 1);
            $usedIds[] = $itemId;

            $normalized[] = [
                'id' => $itemId,
                'item_name' => $itemName,
                'stock_item_id' => $stockItemId,
                'stock_quantity' => ($row['stock_quantity'] ?? '') !== '' ? max(0.001, (float) $row['stock_quantity']) : null,
                'quantity_type' => $quantityType,
                'quantity_value' => $quantityValue,
                'sort_order' => ($index + 1) * 10,
            ];
        }

        return $normalized;
    }

    public function activeTicketCount(Workshop $workshop): int
    {
        return $this->resolveActiveTicketCount($workshop);
    }

    public function reservationParticipants(Workshop $workshop): int
    {
        if ($workshop->registration === 'tickets') {
            return max(1, (int) ($workshop->max_tickets ?? 1));
        }

        return max(1, (int) ($workshop->max_attendance ?? $workshop->pick_list_participants ?? 1));
    }

    private function pickListNotes(Workshop $workshop): string
    {
        $pickListNotes = trim((string) ($workshop->pick_list_notes ?? ''));
        if ($pickListNotes === '') {
            $pickListNotes = trim((string) ($workshop->pickListTemplate->description ?? ''));
        }

        return $pickListNotes;
    }

    private function resolvedParticipants(Workshop $workshop): int
    {
        if ($workshop->registration === 'tickets') {
            return $this->reservationParticipants($workshop);
        }

        $configured = (int) ($workshop->pick_list_participants ?? 0);
        if ($configured > 0) {
            $capacity = (int) ($workshop->max_attendance ?? 0);

            return $capacity > 0 ? min($configured, $capacity) : $configured;
        }

        $maxAttendance = (int) ($workshop->max_attendance ?? 0);
        if ($maxAttendance > 0) {
            return $maxAttendance;
        }

        $fromTickets = $this->resolveActiveTicketCount($workshop);
        if ($fromTickets > 0) {
            return $fromTickets;
        }

        return 1;
    }

    /**
     * @return Collection<int, array{id: int, item_name: string, stock_item_id: ?int, stock_quantity: ?float, quantity_type: string, quantity_value: int, sort_order: int}>
     */
    private function resolvedPickListItems(Workshop $workshop): Collection
    {
        if ($workshop->pick_list_is_customized) {
            return collect($this->normalizePickListItems($workshop->pick_list_custom_items));
        }

        if ($workshop->pick_list_template_id === null || ! $workshop->pickListTemplate) {
            return collect();
        }

        $items = $workshop->pickListTemplate->items;
        $items->loadMissing('stockItem.group');

        return $items
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function (PickListTemplateItem $item): array {
                return [
                    'id' => (int) $item->id,
                    'item_name' => (string) ($item->stockItem?->linkLabel() ?? $item->item_name),
                    'stock_item_id' => $item->stock_item_id !== null ? (int) $item->stock_item_id : null,
                    'stock_quantity' => $item->stock_quantity !== null ? (float) $item->stock_quantity : null,
                    'quantity_type' => (string) $item->quantity_type,
                    'quantity_value' => (int) $item->quantity_value,
                    'sort_order' => (int) ($item->sort_order ?? 0),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{id: int, item_name: string, stock_item_id: ?int, stock_quantity: ?float, quantity_type: string, quantity_value: int, sort_order: int}>  $items
     * @param  array<int, list<KitRecipePart>>  $kitContentsByStockItem
     * @return Collection<int, CalculatedPickItem>
     */
    private function buildCalculatedItems(Collection $items, int $participants, array $kitContentsByStockItem): Collection
    {
        return $items
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function (array $item) use ($participants, $kitContentsByStockItem): array {
                $quantityType = (string) $item['quantity_type'];
                $quantityValue = (int) $item['quantity_value'];
                $quantity = $this->computedItemQuantity($participants, $quantityType, $quantityValue);
                $stockQuantityMultiplier = $item['stock_quantity'] !== null
                    ? max(0.001, (float) $item['stock_quantity'])
                    : 1.0;
                $stockQuantityTotal = (float) $quantity * $stockQuantityMultiplier;
                $perParticipantQuantity = (float) $quantityValue * $stockQuantityMultiplier;
                $stockItemId = $item['stock_item_id'] !== null ? (int) $item['stock_item_id'] : null;
                $kitContents = [];
                if ($stockItemId !== null) {
                    foreach ($kitContentsByStockItem[$stockItemId] ?? [] as $part) {
                        $quantityPerKit = (float) $part['quantity_per_kit'];
                        $estimatedUsage = (bool) $part['is_kit']
                            ? $quantityPerKit * $stockQuantityTotal
                            : StockItem::estimateMaterialUse(
                                $stockQuantityTotal,
                                $quantityPerKit,
                                StockItem::STOCK_ISSUE_INCREMENT,
                            );
                        $kitContents[] = [
                            'stock_item_id' => $part['stock_item_id'],
                            'stock_item_name' => $part['stock_item_name'],
                            'stock_unit' => $part['stock_unit'],
                            'quantity' => round(ceil(($estimatedUsage - 0.0000001) / StockItem::STOCK_ISSUE_INCREMENT) * StockItem::STOCK_ISSUE_INCREMENT, 3),
                            'depth' => $part['depth'],
                            'parent_name' => $part['parent_name'],
                            'note' => $part['note'],
                            'is_kit' => $part['is_kit'],
                        ];
                    }
                }

                return [
                    'item_id' => (int) $item['id'],
                    'item_name' => (string) $item['item_name'],
                    'stock_item_id' => $stockItemId,
                    'stock_quantity_total' => $stockQuantityTotal,
                    'quantity' => $quantity,
                    'quantity_text' => $item['stock_item_id'] !== null
                        ? $this->formatQuantity($stockQuantityTotal)
                        : (string) $quantity,
                    'type_note' => $quantityType === PickListTemplateItem::TYPE_PER_PARTICIPANT
                        ? '('.$this->formatQuantity($perParticipantQuantity).' per participant)'
                        : '',
                    'kit_contents' => $kitContents,
                ];
            })
            ->values();
    }

    private function computedItemQuantity(int $participants, string $quantityType, int $quantityValue): int
    {
        $participants = max(0, $participants);
        $quantityValue = max(1, $quantityValue);

        if ($quantityType === PickListTemplateItem::TYPE_PER_PARTICIPANT) {
            return max(0, $quantityValue * $participants);
        }

        return $quantityValue;
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    private function resolveActiveTicketCount(Workshop $workshop): int
    {
        $count = $workshop->getAttribute('active_tickets_count');
        if ($count !== null) {
            return (int) $count;
        }

        if ($workshop->relationLoaded('tickets')) {
            return $workshop->tickets
                ->filter(fn (Ticket $ticket): bool => in_array($ticket->status, Ticket::activePurchasedStatuses(), true))
                ->count();
        }

        return Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->count();
    }
}
