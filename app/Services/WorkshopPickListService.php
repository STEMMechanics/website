<?php

namespace App\Services;

use App\Models\PickListTemplateItem;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;
use Illuminate\Validation\ValidationException;

class WorkshopPickListService
{
    /**
     * @return array{
     *     participants: int,
     *     resolvedItems: Collection<int, array{id: int, item_name: string, quantity_type: string, quantity_value: int, sort_order: int}>,
     *     calculatedItems: Collection<int, array{item_id: int, item_name: string, quantity: int, quantity_text: string, type_note: string}>,
     *     pickListNotes: string
     * }
     */
    public function build(Workshop $workshop): array
    {
        $participants = $this->resolvedParticipants($workshop);
        $resolvedItems = $this->resolvedPickListItems($workshop);

        return [
            'participants' => $participants,
            'resolvedItems' => $resolvedItems,
            'calculatedItems' => $this->buildCalculatedItems($resolvedItems, $participants),
            'pickListNotes' => $this->pickListNotes($workshop),
        ];
    }

    /**
     * @param Collection<int, Workshop> $workshops
     * @return array{
     *     workshopSummaries: Collection<int, array{workshop: Workshop, participants: int, calculatedItems: Collection<int, array{item_id: int, item_name: string, quantity: int, quantity_text: string, type_note: string}>, pickListNotes: string}>,
     *     materialRows: Collection<int, array{item_name: string, total_quantity: int, workshopBreakdowns: Collection<int, array{workshop_id: string, workshop_title: string, starts_at_label: string, quantity: int}>}>,
     *     totalQuantity: int,
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
        $totalQuantity = 0;

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

                $quantity = max(0, (int) ($item['quantity'] ?? 0));
                $totalQuantity += $quantity;

                $key = Str::lower($itemName);
                if (! array_key_exists($key, $materials)) {
                    $materials[$key] = [
                        'item_name' => $itemName,
                        'total_quantity' => 0,
                        'workshopBreakdowns' => [],
                    ];
                }

                $materials[$key]['total_quantity'] += $quantity;
                if (! array_key_exists($workshopId, $materials[$key]['workshopBreakdowns'])) {
                    $materials[$key]['workshopBreakdowns'][$workshopId] = [
                        'workshop_id' => $workshopId,
                        'workshop_title' => $workshopTitle,
                        'starts_at_label' => $startsAtLabel,
                        'quantity' => 0,
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

                /** @var array{item_name: string, total_quantity: int, workshopBreakdowns: Collection<int, array{workshop_id: string, workshop_title: string, starts_at_label: string, quantity: int}>} $row */
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

        $normalized = [];
        $usedIds = [];
        $nextId = 1;

        foreach ($decoded as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemName = trim((string) ($row['item_name'] ?? ''));
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
            $fromTickets = $this->resolveActiveTicketCount($workshop);
            if ($fromTickets > 0) {
                return $fromTickets;
            }

            return 1;
        }

        $configured = (int) ($workshop->pick_list_participants ?? 0);
        if ($configured > 0) {
            return $configured;
        }

        $fromTickets = $this->resolveActiveTicketCount($workshop);
        if ($fromTickets > 0) {
            return $fromTickets;
        }

        return 1;
    }

    /**
     * @return Collection<int, array{id: int, item_name: string, quantity_type: string, quantity_value: int, sort_order: int}>
     */
    private function resolvedPickListItems(Workshop $workshop): Collection
    {
        if ($workshop->pick_list_is_customized) {
            return collect($this->normalizePickListItems($workshop->pick_list_custom_items));
        }

        return ($workshop->pick_list_template_id !== null ? $workshop->pickListTemplate->items : collect())
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function (PickListTemplateItem $item): array {
                return [
                    'id' => (int) $item->id,
                    'item_name' => (string) $item->item_name,
                    'quantity_type' => (string) $item->quantity_type,
                    'quantity_value' => (int) $item->quantity_value,
                    'sort_order' => (int) ($item->sort_order ?? 0),
                ];
            })
            ->values();
    }

    /**
     * @param Collection<int, array{id: int, item_name: string, quantity_type: string, quantity_value: int, sort_order: int}> $items
     * @return Collection<int, array{item_id: int, item_name: string, quantity: int, quantity_text: string, type_note: string}>
     */
    private function buildCalculatedItems(Collection $items, int $participants): Collection
    {
        return $items
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function (array $item) use ($participants): array {
                $quantityType = (string) $item['quantity_type'];
                $quantityValue = (int) $item['quantity_value'];
                $quantity = $this->computedItemQuantity($participants, $quantityType, $quantityValue);

                return [
                    'item_id' => (int) $item['id'],
                    'item_name' => (string) $item['item_name'],
                    'quantity' => $quantity,
                    'quantity_text' => (string) $quantity,
                    'type_note' => $quantityType === PickListTemplateItem::TYPE_PER_PARTICIPANT
                        ? '('.$quantityValue.' per participant)'
                        : '',
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
