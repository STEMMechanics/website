<?php

namespace App\Http\Controllers;

use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\Ticket;
use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;

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
            'pickListCanvasDataJson' => is_string($workshop->pick_list_canvas_data) ? $workshop->pick_list_canvas_data : null,
            'pickListCanvasThumbnailUrl' => $this->pickListCanvasThumbnailUrl($workshop->pick_list_canvas_thumbnail_path),
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
            'pick_list_canvas_data' => ['nullable'],
            'pick_list_canvas_thumbnail_data' => ['sometimes', 'nullable', 'string'],
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

        $canvasDataWasProvided = $request->exists('pick_list_canvas_data');
        $canvasThumbnailWasProvided = $request->exists('pick_list_canvas_thumbnail_data');
        $canvasData = $canvasDataWasProvided
            ? $this->normalizePickListCanvasData($request->input('pick_list_canvas_data'))
            : (is_string($workshop->pick_list_canvas_data) ? $workshop->pick_list_canvas_data : null);

        $workshop->pick_list_checked_item_ids = $selectedIds;
        if ($canvasDataWasProvided) {
            $workshop->pick_list_canvas_data = $canvasData;
        }
        if ($canvasDataWasProvided && $canvasData === null) {
            $this->deletePickListCanvasThumbnail($workshop->pick_list_canvas_thumbnail_path);
            $workshop->pick_list_canvas_thumbnail_path = null;
        } elseif ($canvasThumbnailWasProvided) {
            $thumbnailData = trim((string) $request->input('pick_list_canvas_thumbnail_data', ''));
            if ($thumbnailData !== '') {
                $workshop->pick_list_canvas_thumbnail_path = $this->storePickListCanvasThumbnail($workshop, $thumbnailData);
            }
        }
        $workshop->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'saved_at_iso' => $workshop->updated_at?->toIso8601String(),
                'saved_at_display' => $workshop->updated_at?->format('M j, Y g:i a'),
                'pick_list_participants' => $workshop->pick_list_participants,
                'checked_item_ids' => $selectedIds,
                'pick_list_canvas_has_content' => is_string($workshop->pick_list_canvas_data) && trim($workshop->pick_list_canvas_data) !== '',
                'pick_list_canvas_thumbnail_url' => $this->pickListCanvasThumbnailUrl($workshop->pick_list_canvas_thumbnail_path),
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

    private function normalizePickListCanvasData(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (strlen($trimmed) > 6_000_000) {
                throw ValidationException::withMessages([
                    'pick_list_canvas_data' => 'Canvas data is too large to save.',
                ]);
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    'pick_list_canvas_data' => 'Canvas data could not be parsed.',
                ]);
            }
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            throw ValidationException::withMessages([
                'pick_list_canvas_data' => 'Canvas data format is invalid.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'pick_list_canvas_data' => 'Canvas data format is invalid.',
            ]);
        }

        try {
            $normalized = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'pick_list_canvas_data' => 'Canvas data could not be encoded.',
            ]);
        }

        return $normalized;
    }

    private function storePickListCanvasThumbnail(Workshop $workshop, string $dataUrl): string
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'pick_list_canvas_thumbnail_data' => 'Canvas preview image format is invalid.',
            ]);
        }

        $binary = base64_decode(str_replace(' ', '+', (string) $matches[1]), true);
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'pick_list_canvas_thumbnail_data' => 'Canvas preview image could not be decoded.',
            ]);
        }

        if (strlen($binary) > 4_000_000) {
            throw ValidationException::withMessages([
                'pick_list_canvas_thumbnail_data' => 'Canvas preview image is too large to save.',
            ]);
        }

        $path = 'workshop-pick-list-thumbnails/workshop-'.$workshop->id.'.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function deletePickListCanvasThumbnail(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function pickListCanvasThumbnailUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
