<?php

namespace App\Http\Controllers;

use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\Ticket;
use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class WorkshopPickListController extends Controller
{
    public function show(Workshop $workshop)
    {
        $workshop->loadMissing('location', 'pickListTemplate.items');

        $participants = $this->resolvedParticipants($workshop);

        return view('admin.workshop.pick-list', [
            'workshop' => $workshop,
            'participants' => $participants,
            'activeTicketCount' => $this->activeTicketCount($workshop),
            'checkedItemIds' => collect($workshop->pick_list_checked_item_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all(),
            'templateItems' => ($workshop->pick_list_template_id !== null ? $workshop->pickListTemplate->items : collect())
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->map(function (PickListTemplateItem $item): array {
                    return [
                        'id' => (int) $item->id,
                        'item_name' => (string) $item->item_name,
                        'quantity_type' => (string) $item->quantity_type,
                        'quantity_value' => (int) $item->quantity_value,
                    ];
                })
                ->values()
                ->all(),
            'calculatedItems' => $this->buildCalculatedItems(
                $workshop->pick_list_template_id !== null ? $workshop->pickListTemplate->items : collect(),
                $participants
            ),
            'lastSavedAt' => $workshop->updated_at,
        ]);
    }

    public function save(Request $request, Workshop $workshop): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'pick_list_template_id' => ['sometimes', 'nullable', 'exists:pick_list_templates,id'],
            'pick_list_participants' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'pick_list_notes' => ['nullable', 'string'],
            'checked_item_ids' => ['nullable', 'array'],
            'checked_item_ids.*' => ['integer'],
        ]);

        $templateId = array_key_exists('pick_list_template_id', $validated)
            ? ((isset($validated['pick_list_template_id']) && (string) $validated['pick_list_template_id'] !== '') ? (int) $validated['pick_list_template_id'] : null)
            : ($workshop->pick_list_template_id !== null ? (int) $workshop->pick_list_template_id : null);
        $notes = trim((string) ($validated['pick_list_notes'] ?? ''));

        if ($templateId !== null && $notes === '') {
            $templateNotes = (string) (PickListTemplate::query()
                ->where('id', $templateId)
                ->value('description') ?? '');
            $notes = trim($templateNotes);
        }

        $workshop->pick_list_template_id = $templateId;
        $workshop->pick_list_participants = $validated['pick_list_participants'] ?? null;
        $workshop->pick_list_notes = $notes !== '' ? $notes : null;
        $selectedIds = collect($validated['checked_item_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $allowedIds = $templateId !== null
            ? PickListTemplateItem::query()
                ->where('pick_list_template_id', $templateId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        $allowedLookup = array_fill_keys($allowedIds, true);
        $selectedIds = array_values(array_filter($selectedIds, fn (int $id) => isset($allowedLookup[$id])));

        $workshop->pick_list_checked_item_ids = $selectedIds;
        $workshop->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'saved_at_iso' => $workshop->updated_at?->toIso8601String(),
                'saved_at_display' => $workshop->updated_at?->format('M j, Y g:i a'),
                'pick_list_participants' => $workshop->pick_list_participants,
                'checked_item_ids' => $selectedIds,
            ]);
        }

        session()->flash('message', 'Workshop pick list settings have been saved');
        session()->flash('message-title', 'Pick list saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.pick-list', $workshop);
    }

    public function pdf(Workshop $workshop): Response
    {
        if (! class_exists(DomPdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
        }

        $workshop->loadMissing('location', 'pickListTemplate.items');
        $participants = $this->resolvedParticipants($workshop);

        $pdf = DomPdf::loadView('pdf.workshop-pick-list', [
            'workshop' => $workshop,
            'participants' => $participants,
            'calculatedItems' => $this->buildCalculatedItems(
                $workshop->pick_list_template_id !== null ? $workshop->pickListTemplate->items : collect(),
                $participants
            ),
            'generatedAt' => now(),
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);

        return $pdf->stream('workshop-'.$workshop->id.'-pick-list.pdf');
    }

    private function activeTicketCount(Workshop $workshop): int
    {
        if ($workshop->registration !== 'tickets') {
            return 0;
        }

        return Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->count();
    }

    private function resolvedParticipants(Workshop $workshop): int
    {
        $configured = (int) ($workshop->pick_list_participants ?? 0);
        if ($configured > 0) {
            return $configured;
        }

        $fromTickets = $this->activeTicketCount($workshop);
        if ($fromTickets > 0) {
            return $fromTickets;
        }

        return 1;
    }

    /**
     * @param Collection<int, PickListTemplateItem> $items
     * @return Collection<int, array{item_id: int, item_name: string, quantity: int, quantity_text: string, type_note: string}>
     */
    private function buildCalculatedItems(Collection $items, int $participants): Collection
    {
        return $items
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function (PickListTemplateItem $item) use ($participants): array {
                $quantity = $item->computedQuantity($participants);
                $quantityText = (string) $quantity;
                $typeNote = (string) $item->quantity_type === PickListTemplateItem::TYPE_PER_PARTICIPANT
                    ? '('.((int) $item->quantity_value).' per participant)'
                    : '';

                return [
                    'item_id' => (int) $item->id,
                    'item_name' => (string) $item->item_name,
                    'quantity' => $quantity,
                    'quantity_text' => $quantityText,
                    'type_note' => $typeNote,
                ];
            })
            ->values();
    }
}
