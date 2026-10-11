<?php

namespace App\Http\Controllers;

use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\Workshop;
use App\Models\WorkshopRunSheetTask;
use App\Services\PdfAttachmentAppender;
use App\Services\ProductRecipeService;
use App\Services\ReminderService;
use App\Services\WorkshopBlueprintService;
use App\Services\WorkshopPickListService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function show(Request $request, Workshop $workshop)
    {
        app(WorkshopBlueprintService::class)->ensureWorkshopTasks($workshop);
        $workshop->loadMissing('location', 'pickListTemplate.items.product', 'pickListTemplate.attachments', 'runSheetTasks', 'reminders.recipient');
        $data = $this->pickListService->build($workshop);
        $shelf = $this->pickListService->buildShelfPickList($workshop, $data['participants'], $data);
        $templateItems = $workshop->pickListTemplate?->items->map(fn (PickListTemplateItem $item): array => $item->only(['id', 'item_name', 'product_id', 'cut_size_id', 'length', 'length_unit', 'quantity_type', 'quantity_value', 'sort_order']))->values()->all() ?? [];
        $allowedTasks = $workshop->runSheetTasks->modelKeys();

        return view('admin.workshop.pick-list', [
            'workshop' => $workshop, 'participants' => $data['participants'],
            'maxParticipants' => max(1, (int) ($workshop->registration === 'tickets' ? ($workshop->max_tickets ?? 5000) : ($workshop->max_attendance ?? 5000))),
            'activeTicketCount' => $this->pickListService->activeTicketCount($workshop),
            'checkedItemIds' => $this->pickListService->normalizeCheckedItemIds($workshop->pick_list_checked_item_ids ?? [], $shelf),
            'completedTaskIds' => collect($workshop->run_sheet_completed_task_ids ?? [])->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => in_array($id, $allowedTasks, true))->values()->all(),
            'taskRemindersBySourceId' => $workshop->reminders->where('kind', ReminderService::WORKSHOP_TASK_KIND)->sortByDesc('id')->unique(fn ($reminder) => (string) $reminder->source_id)->keyBy(fn ($reminder) => (string) $reminder->source_id),
            'pickListCanvasDataJson' => is_string($workshop->pick_list_canvas_data) ? $workshop->pick_list_canvas_data : null,
            'pickListCanvasThumbnailUrl' => $this->pickListCanvasThumbnailUrl($workshop->pick_list_canvas_thumbnail_path),
            'templateItems' => $templateItems, 'shelfPickRows' => $shelf['rows'], 'kitSummaries' => $shelf['kit_summaries'],
            'customItems' => $workshop->pick_list_is_customized ? $data['resolvedItems']->values()->all() : [],
            'isCustomized' => (bool) $workshop->pick_list_is_customized,
            'productCatalog' => app(ProductRecipeService::class)->catalog(),
            'blueprintItemNames' => PickListTemplateItem::query()->orderBy('item_name')->pluck('item_name')->filter()->unique()->values()->all(),
            'materialBudget' => $this->pickListService->materialBudget($workshop, $data),
            'calculatedItems' => $data['calculatedItems'], 'lastSavedAt' => $workshop->updated_at,
            'pickListNotes' => $data['pickListNotes'],
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

    public function save(Request $request, Workshop $workshop): RedirectResponse|JsonResponse
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
                    || preg_match('/^(?:[1-9][0-9]*|item:[1-9][0-9]*)$/D', (string) $value) !== 1) {
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
        DB::transaction(function () use ($workshop, $requestedCheckedIds, &$selectedIds, &$shelfPickList): void {
            $workshop->save();
            $freshWorkshop = $workshop->fresh();
            $shelfPickList = $this->pickListService->buildShelfPickList($freshWorkshop);
            $selectedIds = $this->pickListService->normalizeCheckedItemIds($requestedCheckedIds, $shelfPickList);
            $freshWorkshop->pick_list_checked_item_ids = $selectedIds;
            $freshWorkshop->save();
        });
        $workshop->refresh();
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
                'material_budget' => $this->pickListService->materialBudget($workshop),
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

    public function pdf(Workshop $workshop): Response
    {
        if (! class_exists(DomPdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
        }

        app(WorkshopBlueprintService::class)->ensureWorkshopTasks($workshop);
        $workshop->loadMissing('location', 'pickListTemplate.items', 'pickListTemplate.attachments', 'runSheetTasks');
        $pickListData = $this->pickListService->build($workshop);
        $shelfPickList = $this->pickListService->buildShelfPickList($workshop, $pickListData['participants'], $pickListData);

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
