<?php

namespace App\Http\Controllers;

use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Ticket;
use App\Models\Workshop;
use App\Models\WorkshopRunSheetTask;
use App\Services\PdfAttachmentAppender;
use App\Services\ReminderService;
use App\Services\StockInventoryService;
use App\Services\StoreInventoryAllocatorService;
use App\Services\WorkshopBlueprintService;
use App\Services\WorkshopPickListService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;

class WorkshopPickListController extends Controller
{
    public function __construct(
        private WorkshopPickListService $pickListService,
        private PdfAttachmentAppender $attachmentAppender,
    ) {}

    public function show(Request $request, Workshop $workshop, StockInventoryService $inventory)
    {
        if ($request->query('assembly_preview')) {
            $request->session()->reflash();
        }
        app(WorkshopBlueprintService::class)->ensureWorkshopTasks($workshop);
        $workshop->loadMissing('location', 'pickListTemplate.items.stockItem.group', 'pickListTemplate.attachments', 'runSheetTasks', 'reminders.recipient');

        $stockItems = StockItem::query()->with('group')->where('status', StockItem::STATUS_ACTIVE)->orderBy('name')->get();
        $stockReconciliationTime = $workshop->effectiveEndsAt() ?? $workshop->starts_at;

        $pickListData = $this->pickListService->build($workshop);
        $participants = $pickListData['participants'];
        $shelfPickList = $this->pickListService->buildShelfPickList($workshop, $participants);
        $workshopStockShortages = $inventory->workshopReservationShortages($workshop);
        $shelfPickList = $this->attachStockShortages($shelfPickList, $workshopStockShortages);
        $stockItemsById = $stockItems->keyBy(fn (StockItem $stockItem): int => (int) $stockItem->id);
        $hasWorkshopReservations = StockReservation::query()
            ->where('source_type', Workshop::class)
            ->where('source_id', (string) $workshop->getKey())
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->exists();
        $kitSummaries = collect($shelfPickList['kit_summaries'])
            ->map(function (array $kit) use ($workshop, $stockItemsById, $hasWorkshopReservations): array {
                $stockItem = $stockItemsById->get((int) ($kit['stock_item_id'] ?? 0));
                $toAssemble = (float) ($kit['to_assemble'] ?? 0);
                if ($stockItem instanceof StockItem && ! $hasWorkshopReservations) {
                    $otherReservedQuantity = $stockItem->activeReservedQuantity();
                    $readyQuantity = min(
                        (float) ($kit['required'] ?? 0),
                        max(0, (float) $stockItem->on_hand_quantity - $otherReservedQuantity),
                    );
                    $toAssemble = max(0, (float) ($kit['required'] ?? 0) - $readyQuantity);
                    $kit['ready_made'] = $readyQuantity;
                    $kit['to_assemble'] = $toAssemble;
                }
                if ($stockItem instanceof StockItem
                    && $stockItem->is_kit
                    && $toAssemble > 0.0005
                    && ! ($kit['no_recipe'] ?? false)) {
                    $quantity = max(1, (int) ceil($toAssemble - 0.000001));
                    $kit['assembly_url'] = route('admin.shop.stock.kit.assemble.preview', [
                        'stockItem' => $stockItem,
                        'quantity' => $quantity,
                        'workshop_id' => (string) $workshop->getKey(),
                        'workshop_return_to' => 'run-sheet',
                    ]);
                }

                return $kit;
            })
            ->values()
            ->all();
        $maxParticipants = $workshop->registration === 'tickets'
            ? max(1, (int) ($workshop->max_tickets ?? 5000))
            : max(1, (int) ($workshop->max_attendance ?? 5000));
        $resolvedItems = $pickListData['resolvedItems'];
        $templateItems = $workshop->pickListTemplate?->items
            ?->map(fn (PickListTemplateItem $item): array => [
                'id' => (int) $item->id,
                'item_name' => (string) (data_get($item->stockItem, 'id') ? $item->stockItem->linkLabel() : $item->item_name),
                'quantity_type' => (string) $item->quantity_type,
                'quantity_value' => (int) $item->quantity_value,
                'stock_item_id' => $item->stock_item_id ? (int) $item->stock_item_id : null,
                'stock_quantity' => $item->stock_quantity,
                'sort_order' => (int) $item->sort_order,
            ])
            ->values()
            ->all() ?? [];
        $checkedItemIds = $this->pickListService->normalizeCheckedItemIds(
            collect($workshop->pick_list_checked_item_ids ?? [])->all(),
            $shelfPickList,
        );

        $templateTaskIds = $workshop->runSheetTasks->pluck('id')->map(fn ($id) => (int) $id)->all();
        $completedTaskIds = collect($workshop->run_sheet_completed_task_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $templateTaskIds, true))
            ->unique()->values()->all();

        $taskRemindersBySourceId = $workshop->reminders
            ->where('kind', ReminderService::WORKSHOP_TASK_KIND)
            ->sortByDesc('id')
            ->unique(fn ($reminder) => (string) $reminder->source_id)
            ->keyBy(fn ($reminder) => (string) $reminder->source_id);

        return view('admin.workshop.pick-list', [
            'workshop' => $workshop,
            'participants' => $participants,
            'maxParticipants' => $maxParticipants,
            'activeTicketCount' => $this->pickListService->activeTicketCount($workshop),
            'checkedItemIds' => $checkedItemIds,
            'completedTaskIds' => $completedTaskIds,
            'taskRemindersBySourceId' => $taskRemindersBySourceId,
            'pickListCanvasDataJson' => is_string($workshop->pick_list_canvas_data) ? $workshop->pick_list_canvas_data : null,
            'pickListCanvasThumbnailUrl' => $this->pickListCanvasThumbnailUrl($workshop->pick_list_canvas_thumbnail_path),
            'templateItems' => $templateItems,
            'shelfPickRows' => $shelfPickList['rows'],
            'kitSummaries' => $kitSummaries,
            'stockShortageCount' => $shelfPickList['shortage_count'],
            'customItems' => $workshop->pick_list_is_customized ? $resolvedItems->values()->all() : [],
            'isCustomized' => (bool) $workshop->pick_list_is_customized,
            'stockItems' => $stockItems,
            'canReconcileWorkshopStock' => ! in_array((string) $workshop->status, ['draft', 'cancelled'], true)
                && $stockReconciliationTime !== null
                && $stockReconciliationTime->isPast(),
            'workshopStockCost' => $inventory->workshopCost($workshop),
            'calculatedItems' => $pickListData['calculatedItems'],
            'lastSavedAt' => $workshop->updated_at,
            'pickListNotes' => $pickListData['pickListNotes'],
        ]);
    }

    public function stockReconciliation(Request $request, Workshop $workshop)
    {
        if ($request->query('assembly_preview')) {
            $request->session()->reflash();
        }
        $workshop->loadMissing('location', 'stockReconciledBy');

        $stockItems = StockItem::query()->with('group')->where('status', StockItem::STATUS_ACTIVE)->orderBy('name')->get();
        $stockItemsById = $stockItems->keyBy(fn (StockItem $stockItem): int => (int) $stockItem->id);
        $pickListData = $this->pickListService->build($workshop);
        $workshopPlanStock = collect($pickListData['calculatedItems'])
            ->filter(fn (array $item): bool => (int) ($item['stock_item_id'] ?? 0) > 0)
            ->groupBy(fn (array $item): int => (int) $item['stock_item_id'])
            ->map(function (Collection $items, int|string $stockItemId) use ($stockItemsById): ?array {
                $stockItem = $stockItemsById->get((int) $stockItemId);
                if (! $stockItem instanceof StockItem) {
                    return null;
                }

                return [
                    'stock_item' => $stockItem,
                    'planned_quantity' => (float) $items->sum(fn (array $item): float => (float) ($item['stock_quantity_total'] ?? 0)),
                    'sources' => $items->pluck('item_name')->map(fn ($name): string => trim((string) $name))->filter()->unique()->values()->all(),
                ];
            })
            ->filter()
            ->values();
        $shelfPickList = $this->pickListService->buildShelfPickList($workshop, $pickListData['participants']);
        $hasWorkshopReservations = StockReservation::query()
            ->where('source_type', Workshop::class)
            ->where('source_id', (string) $workshop->getKey())
            ->whereIn('status', StockReservation::ACTIVE_STATUSES)
            ->exists();
        $workshopKitPreparation = collect($shelfPickList['kit_summaries'])
            ->map(function (array $kit) use ($workshop, $stockItemsById, $hasWorkshopReservations): ?array {
                $stockItem = $stockItemsById->get((int) ($kit['stock_item_id'] ?? 0));
                if (! $stockItem instanceof StockItem || ! $stockItem->is_kit || ($kit['no_recipe'] ?? false)) {
                    return null;
                }

                $toAssemble = (float) ($kit['to_assemble'] ?? 0);
                if (! $hasWorkshopReservations) {
                    $otherReservedQuantity = $stockItem->activeReservedQuantity();
                    $readyQuantity = min(
                        (float) ($kit['required'] ?? 0),
                        max(0, (float) $stockItem->on_hand_quantity - $otherReservedQuantity),
                    );
                    $toAssemble = max(0, (float) ($kit['required'] ?? 0) - $readyQuantity);
                }
                if ($toAssemble <= 0.0005) {
                    return null;
                }

                $quantity = max(1, (int) ceil($toAssemble - 0.000001));

                return [
                    'stock_item' => $stockItem,
                    'quantity' => $quantity,
                    'url' => route('admin.shop.stock.kit.assemble.preview', [
                        'stockItem' => $stockItem,
                        'quantity' => $quantity,
                        'workshop_id' => (string) $workshop->getKey(),
                        'workshop_return_to' => 'stock-reconciliation',
                    ]),
                ];
            })
            ->filter()
            ->values();
        $workshopStockUsage = StockMovement::query()
            ->with('stockItem.group')
            ->where('source_type', Workshop::class)
            ->where('source_id', (string) $workshop->getKey())
            ->where('movement_type', StockMovement::TYPE_WORKSHOP)
            ->where('quantity', '<', 0)
            ->orderBy('stock_item_id')
            ->get()
            ->groupBy('stock_item_id')
            ->map(function ($movements): ?array {
                $stockItem = $movements->first()?->stockItem;
                if (! $stockItem instanceof StockItem) {
                    return null;
                }

                return [
                    'stock_item' => $stockItem,
                    'used_quantity' => (float) $movements->sum(fn (StockMovement $movement): float => abs((float) $movement->quantity)),
                ];
            })
            ->filter()
            ->values();

        $reconciliationTime = $workshop->effectiveEndsAt() ?? $workshop->starts_at;
        $hasReconciliableStock = $this->pickListService->plannedStockForReconciliation($workshop)->isNotEmpty();
        $canReconcileWorkshopStock = ! in_array((string) $workshop->status, ['draft', 'cancelled'], true)
            && $reconciliationTime !== null
            && $reconciliationTime->isPast()
            && $hasReconciliableStock;

        return view('admin.workshop.stock-reconciliation', [
            'workshop' => $workshop,
            'stockItems' => $stockItems,
            'workshopPlanStock' => $workshopPlanStock,
            'workshopKitPreparation' => $workshopKitPreparation,
            'workshopStockUsage' => $workshopStockUsage,
            'hasReconciliableStock' => $hasReconciliableStock,
            'canReconcileWorkshopStock' => $canReconcileWorkshopStock,
        ]);
    }

    public function completeTask(Workshop $workshop, WorkshopRunSheetTask $task): RedirectResponse
    {
        abort_unless((string) $task->workshop_id === (string) $workshop->id, 404);

        $completedTaskIds = collect($workshop->run_sheet_completed_task_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $task->id)
            ->unique()
            ->values()
            ->all();

        $workshop->update(['run_sheet_completed_task_ids' => $completedTaskIds]);
        app(ReminderService::class)->syncWorkshop($workshop->fresh());

        session()->flash('message', '“'.$task->name.'” has been marked as complete.');
        session()->flash('message-title', 'Task complete');
        session()->flash('message-type', 'success');

        return redirect()->to(route('admin.workshop.run-sheet', $workshop).'#task-'.$task->id);
    }

    public function save(Request $request, Workshop $workshop, StockInventoryService $inventory): RedirectResponse|JsonResponse
    {
        $maxParticipants = $workshop->registration === 'tickets'
            ? max(1, (int) ($workshop->max_tickets ?? 5000))
            : max(1, (int) ($workshop->max_attendance ?? 5000));
        $validated = $request->validate([
            'pick_list_template_id' => ['sometimes', 'nullable', 'exists:pick_list_templates,id'],
            'pick_list_participants' => ['nullable', 'integer', 'min:1', 'max:'.$maxParticipants],
            'pick_list_notes' => ['nullable', 'string'],
            'pick_list_custom_items' => ['sometimes', 'nullable'],
            'reset_pick_list_customization' => ['nullable', 'boolean'],
            'checked_item_ids' => ['nullable', 'array'],
            'checked_item_ids.*' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if ((! is_string($value) && ! is_int($value))
                    || strlen((string) $value) > 100
                    || preg_match('/^(?:[1-9][0-9]*|stock:[1-9][0-9]*|manual:[1-9][0-9]*|kit:[1-9][0-9]*:[1-9][0-9]*:[0-9]+)$/D', (string) $value) !== 1) {
                    $fail('Select a valid pick list item.');
                }
            }],
            'completed_task_ids' => ['nullable', 'array'],
            'completed_task_ids.*' => ['integer'],
            'workshop_run_sheet' => ['sometimes', 'nullable', 'string'],
            'pick_list_canvas_data' => ['nullable'],
            'pick_list_canvas_thumbnail_data' => ['sometimes', 'nullable', 'string'],
        ]);

        $templateId = array_key_exists('pick_list_template_id', $validated)
            ? ((isset($validated['pick_list_template_id']) && (string) $validated['pick_list_template_id'] !== '') ? (int) $validated['pick_list_template_id'] : null)
            : ($workshop->pick_list_template_id !== null ? (int) $workshop->pick_list_template_id : null);

        $existingCustomized = (bool) $workshop->pick_list_is_customized;
        $resetCustomization = $request->boolean('reset_pick_list_customization');
        $customItemsProvided = $request->exists('pick_list_custom_items') && $request->input('pick_list_custom_items') !== null;
        $notes = array_key_exists('pick_list_notes', $validated)
            ? trim((string) $validated['pick_list_notes'])
            : (string) $workshop->pick_list_notes;

        if (($resetCustomization || (! $existingCustomized && ! $customItemsProvided)) && $templateId !== null && $notes === '') {
            $templateNotes = (string) (PickListTemplate::query()
                ->where('id', $templateId)
                ->value('description') ?? '');
            $notes = trim($templateNotes);
        }

        if ($resetCustomization) {
            $workshop->pick_list_custom_items = null;
            $workshop->pick_list_is_customized = false;
        } elseif ($customItemsProvided) {
            $customItems = $this->pickListService->normalizePickListItems($request->input('pick_list_custom_items'));
            $invalidStockItem = collect($customItems)->first(function (array $item): bool {
                $stockItemId = (int) ($item['stock_item_id'] ?? 0);

                return $stockItemId > 0
                    && ! StockItem::query()->whereKey($stockItemId)->where('status', StockItem::STATUS_ACTIVE)->exists();
            });
            if ($invalidStockItem !== null) {
                throw ValidationException::withMessages(['pick_list_custom_items' => 'Choose a valid active stock item for each linked material.']);
            }
            $workshop->pick_list_custom_items = $customItems;
            $workshop->pick_list_is_customized = true;
        } elseif (! $existingCustomized) {
            $workshop->pick_list_custom_items = null;
            $workshop->pick_list_is_customized = false;
        }

        $workshop->pick_list_template_id = $templateId;
        $workshop->pick_list_participants = $validated['pick_list_participants'] ?? null;
        $workshop->pick_list_notes = $notes !== '' ? $notes : null;

        $requestedCheckedIds = collect($validated['checked_item_ids'] ?? [])
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
        $selectedIds = [];
        $shelfPickList = ['rows' => [], 'kit_summaries' => []];

        app(WorkshopBlueprintService::class)->ensureWorkshopTasks($workshop);
        $allowedTaskIds = $workshop->runSheetTasks()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $completedTaskIds = collect($validated['completed_task_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $allowedTaskIds, true))
            ->unique()->values()->all();

        $canvasDataWasProvided = $request->exists('pick_list_canvas_data');
        $canvasThumbnailWasProvided = $request->exists('pick_list_canvas_thumbnail_data');
        $canvasData = $canvasDataWasProvided
            ? $this->normalizePickListCanvasData($request->input('pick_list_canvas_data'))
            : (is_string($workshop->pick_list_canvas_data) ? $workshop->pick_list_canvas_data : null);

        $workshop->pick_list_checked_item_ids = [];
        $workshop->run_sheet_completed_task_ids = $completedTaskIds;
        if (array_key_exists('workshop_run_sheet', $validated)) {
            $workshop->workshop_run_sheet = trim((string) $validated['workshop_run_sheet']) ?: null;
        }
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
        DB::transaction(function () use ($workshop, $requestedCheckedIds, &$selectedIds, &$shelfPickList, $inventory): void {
            $workshop->save();
            $freshWorkshop = $workshop->fresh();
            $inventory->syncWorkshopReservations(
                $freshWorkshop,
                (string) $freshWorkshop->status === 'cancelled',
                refreshAfterStart: true,
            );
            $freshWorkshop = $freshWorkshop->fresh();
            $shelfPickList = $this->pickListService->buildShelfPickList($freshWorkshop);
            $selectedIds = $this->pickListService->normalizeCheckedItemIds($requestedCheckedIds, $shelfPickList);
            $freshWorkshop->pick_list_checked_item_ids = $selectedIds;
            $freshWorkshop->save();
        });
        $workshop->refresh();
        $workshopStockShortages = $inventory->workshopReservationShortages($workshop);
        $shelfPickList = $this->attachStockShortages($shelfPickList, $workshopStockShortages);
        app(ReminderService::class)->syncWorkshop($workshop->fresh());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'saved_at_iso' => $workshop->updated_at?->toIso8601String(),
                'saved_at_display' => $workshop->updated_at?->format('M j, Y g:i a'),
                'pick_list_participants' => $workshop->pick_list_participants,
                'checked_item_ids' => $selectedIds,
                'shelf_pick_rows' => $shelfPickList['rows'],
                'kit_summaries' => $shelfPickList['kit_summaries'],
                'stock_shortage_count' => $shelfPickList['shortage_count'],
                'completed_task_ids' => $completedTaskIds,
                'workshop_run_sheet' => $workshop->workshop_run_sheet,
                'pick_list_is_customized' => (bool) $workshop->pick_list_is_customized,
                'pick_list_custom_items' => $workshop->pick_list_custom_items ?? [],
                'pick_list_canvas_has_content' => is_string($workshop->pick_list_canvas_data) && trim($workshop->pick_list_canvas_data) !== '',
                'pick_list_canvas_thumbnail_url' => $this->pickListCanvasThumbnailUrl($workshop->pick_list_canvas_thumbnail_path),
            ]);
        }

        session()->flash('message', 'Workshop pick list settings have been saved');
        session()->flash('message-title', 'Pick list saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.run-sheet', $workshop);
    }

    public function reconcileStock(
        Request $request,
        Workshop $workshop,
        StockInventoryService $inventory,
        StoreInventoryAllocatorService $allocator,
    ): RedirectResponse {
        $validated = $request->validate([
            'actual_used' => ['sometimes', 'array'],
            'actual_used.*' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $changedStockItemIds = $inventory->reconcileWorkshopStock(
            $workshop,
            $validated['actual_used'] ?? [],
            $request->user(),
        );

        StockItem::query()
            ->whereIn('id', $changedStockItemIds)
            ->orderBy('id')
            ->get()
            ->each(fn (StockItem $stockItem) => $allocator->allocateForStockItem($stockItem));

        $reservationShortages = $inventory->reservationShortages($changedStockItemIds);
        if ($reservationShortages->isNotEmpty()) {
            $shortageSummary = $reservationShortages
                ->map(fn (StockItem $stockItem): string => $stockItem->linkLabel().' short by '.$stockItem->formatQuantity((float) ($stockItem->reservation_shortage ?? 0)).' '.$stockItem->unit)
                ->implode('; ');

            return redirect()->route('admin.workshop.stock-reconciliation', $workshop)->with([
                'message' => 'Workshop stock use was recorded, but active reservations now exceed stock on hand: '.$shortageSummary.'. Review the affected reservations or replenish stock.',
                'message-title' => 'Reservation stock warning',
                'message-type' => 'warning',
            ]);
        }

        return redirect()->route('admin.workshop.stock-reconciliation', $workshop)->with([
            'message' => 'Workshop stock use has been recorded and unused reservations released.',
            'message-title' => 'Workshop stock reconciled',
            'message-type' => 'success',
        ]);
    }

    public function pdf(Workshop $workshop): Response
    {
        if (! class_exists(DomPdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
        }

        app(WorkshopBlueprintService::class)->ensureWorkshopTasks($workshop);
        $workshop->loadMissing('location', 'pickListTemplate.items', 'pickListTemplate.attachments', 'runSheetTasks');
        $pickListData = $this->pickListService->build($workshop);
        $shelfPickList = $this->pickListService->buildShelfPickList($workshop, $pickListData['participants']);

        $pdf = DomPdf::loadView('pdf.workshop-pick-list', [
            'workshop' => $workshop,
            'participants' => $pickListData['participants'],
            'calculatedItems' => $pickListData['calculatedItems'],
            'kitSummaries' => $shelfPickList['kit_summaries'],
            'pickListNotes' => $pickListData['pickListNotes'],
            'workshopDrawingPath' => $this->pickListCanvasThumbnailPath($workshop->pick_list_canvas_thumbnail_path),
            'generatedAt' => now(),
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);

        $content = $this->attachmentAppender->append(
            $pdf->output(),
            $workshop->pick_list_template_id !== null
                ? $workshop->pickListTemplate->attachments
                : collect(),
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="workshop-'.$workshop->id.'-plan.pdf"',
        ]);
    }

    /**
     * Attach each workshop-specific inventory shortage to the matching pick
     * list item so the warning can move with autosaved changes.
     *
     * @param array{rows: list<array<string, mixed>>, kit_summaries: list<array<string, mixed>>} $shelfPickList
     * @param array<int, float> $shortages
     * @return array{rows: list<array<string, mixed>>, kit_summaries: list<array<string, mixed>>, shortage_count: int}
     */
    private function attachStockShortages(array $shelfPickList, array $shortages): array
    {
        $stockItemIds = [];
        $collectIds = function (array $entry) use (&$collectIds, &$stockItemIds): void {
            $stockItemId = (int) ($entry['stock_item_id'] ?? 0);
            if ($stockItemId > 0) {
                $stockItemIds[$stockItemId] = true;
            }
            foreach (($entry['contents'] ?? []) as $child) {
                if (is_array($child)) {
                    $collectIds($child);
                }
            }
        };
        foreach ([...($shelfPickList['rows'] ?? []), ...($shelfPickList['kit_summaries'] ?? [])] as $entry) {
            if (is_array($entry)) {
                $collectIds($entry);
            }
        }

        $stockItemsById = $stockItemIds === []
            ? collect()
            : StockItem::query()->whereIn('id', array_keys($stockItemIds))->get(['id', 'unit'])->keyBy(fn (StockItem $item): int => (int) $item->id);
        $attach = function (array $entry) use (&$attach, $shortages, $stockItemsById): array {
            $stockItemId = (int) ($entry['stock_item_id'] ?? 0);
            if ($stockItemId > 0) {
                $entry['shortage_quantity'] = round(max(0, (float) ($shortages[$stockItemId] ?? 0)), 3);
                $entry['shortage_unit'] = (string) ($stockItemsById->get($stockItemId)->unit ?? $entry['unit'] ?? '');
            }
            if (isset($entry['contents']) && is_array($entry['contents'])) {
                $entry['contents'] = array_map(
                    fn (array $child): array => $attach($child),
                    $entry['contents'],
                );
            }

            return $entry;
        };

        $shelfPickList['rows'] = array_map(fn (array $row): array => $attach($row), $shelfPickList['rows'] ?? []);
        $shelfPickList['kit_summaries'] = array_map(fn (array $kit): array => $attach($kit), $shelfPickList['kit_summaries'] ?? []);
        $shelfPickList['shortage_count'] = count(array_filter(
            $shortages,
            fn (float $quantity): bool => $quantity > 0.0005,
        ));

        return $shelfPickList;
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

    private function pickListCanvasThumbnailPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }
}
