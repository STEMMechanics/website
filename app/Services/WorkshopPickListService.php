<?php

namespace App\Services;

use App\Models\PickListTemplateItem;
use App\Models\Product;
use App\Models\ProductRecipeComponent;
use App\Models\SiteOption;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class WorkshopPickListService
{
    public function __construct(private readonly ProductRecipeService $recipes) {}

    public function build(Workshop $workshop, ?int $participantsOverride = null): array
    {
        $participants = $participantsOverride !== null ? max(1, $participantsOverride) : $this->resolvedParticipants($workshop);
        $items = $workshop->pick_list_is_customized
            ? $this->normalizePickListItems($workshop->pick_list_custom_items, false)
            : ($workshop->pickListTemplate?->items->map(fn (PickListTemplateItem $item): array => $item->only(['id', 'item_name', 'product_id', 'cut_size_id', 'length', 'length_unit', 'quantity_type', 'quantity_value', 'sort_order']))->all() ?? []);
        $resolved = collect($this->normalizePickListItems($items, false));
        $products = Product::query()->with(['cutSizes', 'recipeComponents'])->whereIn('id', $resolved->pluck('product_id')->filter())->get()->keyBy('id');
        $calculated = $resolved->map(function (array $item) use ($participants, $products): array {
            $quantity = $this->computedItemQuantity($participants, $item['quantity_type'], $item['quantity_value']);
            $product = $products->get((int) ($item['product_id'] ?? 0));
            $row = new ProductRecipeComponent(['component_product_id' => $item['product_id'], 'quantity' => 1, 'cut_size_id' => $item['cut_size_id'], 'length' => $item['length'], 'length_unit' => $item['length_unit']]);
            $row->setRelation('component', $product);
            $row->setRelation('variant', null);
            $row->setRelation('cutSize', $product?->cutSizes->find($item['cut_size_id']));
            $name = $product ? $row->preparationLabel() : $item['item_name'];
            $contents = $product && $product->isKit() ? $this->recipes->preparationRows($product, $quantity) : [];
            $units = $product ? $row->purchasedUnits() : null;
            $cost = $product ? $this->recipes->materialCost($product) : null;

            return [
                'item_id' => $item['id'], 'product_id' => $item['product_id'], 'item_name' => $name,
                'quantity' => $quantity, 'quantity_text' => (string) $quantity,
                'type_note' => $item['quantity_type'] === PickListTemplateItem::TYPE_PER_PARTICIPANT ? '('.$item['quantity_value'].' per participant)' : '',
                'kit_contents' => $contents,
                'is_kit' => (bool) $product?->isKit(),
                'admin_url' => $product ? route('admin.shop.product.edit', $product) : null,
                'material_cost' => $cost !== null && $units !== null ? $cost * $units * $quantity : null,
                'raw_note' => $row->preparationNote($quantity),
            ];
        });

        return ['participants' => $participants, 'resolvedItems' => $resolved, 'calculatedItems' => $calculated, 'pickListNotes' => $this->pickListNotes($workshop)];
    }

    /** Preparation counts are deliberately independent of saleable inventory. */
    public function buildShelfPickList(Workshop $workshop, ?int $participantsOverride = null, ?array $data = null): array
    {
        $items = ($data ?? $this->build($workshop, $participantsOverride))['calculatedItems'];
        $rows = [];
        $kits = [];
        foreach ($items as $item) {
            if ($item['is_kit']) {
                $kits[] = [
                    'key' => 'item:'.$item['item_id'], 'item_id' => $item['item_id'], 'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'], 'required' => $item['quantity'], 'is_kit' => true,
                    'admin_url' => $item['admin_url'], 'contents' => $item['kit_contents'],
                    'no_recipe' => $item['kit_contents'] === [],
                ];
            } else {
                $rows[] = [
                    'key' => 'item:'.$item['item_id'], 'item_id' => $item['item_id'], 'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'], 'quantity' => $item['quantity'], 'unit' => '',
                    'kind' => $item['product_id'] ? 'product' : 'manual', 'admin_url' => $item['admin_url'], 'raw_note' => $item['raw_note'],
                ];
            }
        }

        return ['rows' => $rows, 'kit_summaries' => $kits];
    }

    public function normalizeCheckedItemIds(array $checkedItemIds, array $shelfPickList): array
    {
        $allowed = collect([...$shelfPickList['rows'], ...$shelfPickList['kit_summaries']])->pluck('key')->all();

        return collect($checkedItemIds)->map(function ($id): string {
            if (is_numeric($id)) {
                return 'item:'.$id;
            }
            $id = (string) $id;

            // Existing workshops can still hold checkmarks using the previous row keys.
            return str_starts_with($id, 'manual:') ? 'item:'.substr($id, 7) : $id;
        })
            ->filter(fn (string $id): bool => in_array($id, $allowed, true))->unique()->values()->all();
    }

    /** Validate a blueprint or workshop's structured preparation quantities. */
    public function normalizePickListItems(mixed $value, bool $validateLinks = true): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['pick_list_custom_items' => 'The pick list items could not be read.']);
            }
        }
        if (! is_array($value) || count($value) > 200) {
            throw ValidationException::withMessages(['pick_list_custom_items' => 'Use a valid pick list with up to 200 items.']);
        }
        $normalized = [];
        $usedIds = [];
        $nextId = 1;
        foreach ($value as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $productId = (int) ($row['product_id'] ?? 0) ?: null;
            $name = trim((string) ($row['item_name'] ?? ''));
            if ($name === '' && ! $productId) {
                continue;
            }
            $validated = validator($row, [
                'product_id' => 'nullable|integer', 'cut_size_id' => 'nullable|integer',
                'item_name' => 'nullable|string|max:255', 'length' => 'nullable|numeric|gt:0|max:10000000',
                'length_unit' => ['nullable', Rule::in(array_keys(ProductRecipeComponent::LENGTH_UNITS))],
                'quantity_type' => ['required', Rule::in(PickListTemplateItem::TYPES)],
                'quantity_value' => 'required|integer|min:1|max:100000',
            ]);
            if ($validated->fails()) {
                throw ValidationException::withMessages(['pick_list_custom_items' => 'Check the quantity and measurements for '.($name ?: 'each item').'.']);
            }
            $cutId = $productId ? ((int) ($row['cut_size_id'] ?? 0) ?: null) : null;
            $length = $productId && ($row['length'] ?? '') !== '' && ($row['length'] ?? null) !== null ? (float) $row['length'] : null;
            $lengthUnit = $length !== null ? ($row['length_unit'] ?? null) : null;
            if ($productId && $validateLinks) {
                $this->recipes->validateComponent(['component_product_id' => $productId, 'cut_size_id' => $cutId, 'length' => $length, 'length_unit' => $lengthUnit, 'quantity' => 1], 'pick_list_custom_items.'.$index);
                $name = (string) (Product::query()->find($productId)->title ?? $name);
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || in_array($id, $usedIds, true)) {
                $id = $nextId;
            }
            $usedIds[] = $id;
            $nextId = max($nextId + 1, $id + 1);
            $normalized[] = ['id' => $id, 'item_name' => $name, 'product_id' => $productId,
                'cut_size_id' => $cutId, 'length' => $length, 'length_unit' => $lengthUnit,
                'quantity_type' => $row['quantity_type'], 'quantity_value' => (int) $row['quantity_value'], 'sort_order' => ($index + 1) * 10];
        }

        return $normalized;
    }

    public function materialBudget(Workshop $workshop, ?array $data = null): array
    {
        $data ??= $this->build($workshop);
        $items = $data['calculatedItems'];
        $knownCost = (float) $items->sum(fn (array $item): float => (float) ($item['material_cost'] ?? 0));
        $unknown = $items->filter(fn (array $item): bool => $item['product_id'] !== null && $item['material_cost'] === null)->count();
        $manual = $items->whereNull('product_id')->count();
        $budget = (float) SiteOption::value('store.costing.workshop-material-budget', (string) config('product-costing.workshop_material_budget')) * $data['participants'];
        $label = 'planning allowance';
        $savedBudget = DB::table('finance_budgets')->where('workshop_id', $workshop->id)->first();
        $categoryId = DB::table('finance_categories')->whereRaw('LOWER(name) = ?', ['consumables'])->value('id');
        $targets = $savedBudget ? json_decode((string) $savedBudget->targets, true) : [];
        if ($categoryId && is_array($targets) && array_key_exists($categoryId, $targets)) {
            $budget = (float) $targets[$categoryId] / 100;
            $label = 'Consumables allocation';
        }

        return ['known_cost' => round($knownCost, 2), 'per_participant' => round($knownCost / max(1, $data['participants']), 2),
            'unknown_items' => $unknown, 'manual_items' => $manual, 'budget' => round($budget, 2), 'budget_label' => $label,
            'over_budget' => $knownCost > $budget + 0.005];
    }

    public function buildMonthMaterials(Collection $workshops): array
    {
        $summaries = $workshops->map(function (Workshop $workshop): array {
            $data = $this->build($workshop);

            return ['workshop' => $workshop, 'participants' => $data['participants'], 'calculatedItems' => $data['calculatedItems'], 'pickListNotes' => $data['pickListNotes']];
        });
        $materials = [];
        foreach ($summaries as $summary) {
            $workshop = $summary['workshop'];
            foreach ($summary['calculatedItems'] as $item) {
                $parts = $item['is_kit'] && $item['kit_contents'] !== [] ? array_filter($item['kit_contents'], fn (array $part): bool => ! $part['is_kit'] || ! $part['has_recipe']) : [$item];
                foreach ($parts as $part) {
                    $name = trim((string) $part['item_name']);
                    $key = mb_strtolower($name);
                    $quantity = (float) $part['quantity'];
                    $materials[$key] ??= ['item_name' => $name, 'product_id' => $part['product_id'], 'total_quantity' => 0, 'workshopBreakdowns' => []];
                    $materials[$key]['total_quantity'] += $quantity;
                    $id = (string) $workshop->id;
                    $materials[$key]['workshopBreakdowns'][$id] ??= ['workshop_id' => $id, 'workshop_title' => $workshop->title, 'starts_at_label' => $workshop->starts_at?->format('D j M g:ia') ?? '-', 'quantity' => 0];
                    $materials[$key]['workshopBreakdowns'][$id]['quantity'] += $quantity;
                }
            }
        }
        $rows = collect($materials)->map(function (array $row): array {
            $row['workshopBreakdowns'] = collect($row['workshopBreakdowns'])->values();

            return $row;
        })->sortBy('item_name')->values();

        return ['workshopSummaries' => $summaries, 'materialRows' => $rows, 'totalQuantity' => $rows->sum('total_quantity'), 'uniqueItemCount' => $rows->count()];
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
            return max(1, $this->activeTicketCount($workshop));
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

    private function computedItemQuantity(int $participants, string $quantityType, int $quantityValue): int
    {
        $participants = max(0, $participants);
        $quantityValue = max(1, $quantityValue);

        if ($quantityType === PickListTemplateItem::TYPE_PER_PARTICIPANT) {
            return max(0, $quantityValue * $participants);
        }

        return $quantityValue;
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
