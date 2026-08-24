<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\Media;
use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\WorkshopTemplateTask;
use App\Services\PdfAttachmentAppender;
use App\Services\ReminderService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PickListTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = PickListTemplate::query()->withCount(['items', 'tasks', 'attachments']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search', ''));
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('run_sheet', 'like', '%'.$search.'%');
            });
        }

        $templates = $query->orderBy('name')->paginate(20)->onEachSide(1);

        return view('admin.pick-list-template.index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return view('admin.pick-list-template.edit', [
            'itemSuggestions' => $this->itemSuggestions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);
        $validated['attachments'] = array_values(array_unique([
            ...$validated['attachments'],
            ...$this->storeAttachmentUploads($request),
        ]));

        $template = DB::transaction(function () use ($validated): PickListTemplate {
            $template = new PickListTemplate;
            $this->fillTemplate($template, $validated);
            $this->syncItems($template, $validated['items'] ?? []);
            $this->syncTasks($template, $validated['tasks'] ?? []);
            $template->updateFiles($validated['attachments'], PickListTemplate::ATTACHMENT_COLLECTION);

            return $template;
        });

        session()->flash('message', 'Workshop template has been created');
        session()->flash('message-title', 'Workshop template created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop-template.edit', $template);
    }

    public function edit(PickListTemplate $pickListTemplate)
    {
        $pickListTemplate->load(['items', 'tasks', 'attachments']);

        return view('admin.pick-list-template.edit', [
            'template' => $pickListTemplate,
            'itemSuggestions' => $this->itemSuggestions(),
        ]);
    }

    public function update(Request $request, PickListTemplate $pickListTemplate): RedirectResponse
    {
        $validated = $this->validateRequest($request, $pickListTemplate);
        $validated['attachments'] = array_values(array_unique([
            ...$validated['attachments'],
            ...$this->storeAttachmentUploads($request),
        ]));

        DB::transaction(function () use ($pickListTemplate, $validated): void {
            $this->fillTemplate($pickListTemplate, $validated);
            $this->syncItems($pickListTemplate, $validated['items'] ?? []);
            $this->syncTasks($pickListTemplate, $validated['tasks'] ?? []);
            $pickListTemplate->updateFiles($validated['attachments'], PickListTemplate::ATTACHMENT_COLLECTION);
        });
        app(ReminderService::class)->syncTemplateWorkshops((int) $pickListTemplate->id);

        session()->flash('message', 'Workshop template has been updated');
        session()->flash('message-title', 'Workshop template updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(PickListTemplate $pickListTemplate): RedirectResponse
    {
        $pickListTemplate->delete();

        session()->flash('message', 'Workshop template has been deleted');
        session()->flash('message-title', 'Workshop template deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.workshop-template.index');
    }

    public function duplicate(PickListTemplate $pickListTemplate): RedirectResponse
    {
        $pickListTemplate->load(['items', 'tasks', 'attachments']);

        $copy = new PickListTemplate;
        $copy->name = trim((string) $pickListTemplate->name).' (Copy)';
        $copy->description = $pickListTemplate->description;
        $copy->duration = $pickListTemplate->duration;
        $copy->participants = $pickListTemplate->participants;
        $copy->run_sheet = $pickListTemplate->run_sheet;
        $copy->run_sheet_drawing_data = $pickListTemplate->run_sheet_drawing_data;
        $copy->save();

        foreach ($pickListTemplate->items as $item) {
            $copy->items()->create([
                'item_name' => (string) $item->item_name,
                'quantity_type' => (string) $item->quantity_type,
                'quantity_value' => (int) $item->quantity_value,
                'sort_order' => (int) ($item->sort_order ?? 0),
            ]);
        }

        foreach ($pickListTemplate->tasks as $task) {
            $copy->tasks()->create([
                'name' => (string) $task->name,
                'notes' => $task->notes,
                'subtasks' => $task->subtasks,
                'reminder_enabled' => (bool) $task->reminder_enabled,
                'reminder_offset_days' => $task->reminder_offset_days,
                'reminder_time' => $task->reminder_time,
                'sort_order' => (int) ($task->sort_order ?? 0),
            ]);
        }

        $copy->updateFiles(
            $pickListTemplate->attachments->pluck('name')->all(),
            PickListTemplate::ATTACHMENT_COLLECTION
        );

        session()->flash('message', 'Workshop template has been duplicated');
        session()->flash('message-title', 'Workshop template duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop-template.edit', $copy);
    }

    public function pdf(PickListTemplate $pickListTemplate): Response
    {
        $pickListTemplate->load(['items', 'tasks', 'attachments']);

        $calculatedItems = $pickListTemplate->items->map(fn (PickListTemplateItem $item): array => [
            'item_name' => (string) $item->item_name,
            'quantity' => (int) $item->quantity_value,
            'quantity_text' => (string) $item->quantity_value,
            'type_note' => $item->quantity_type === PickListTemplateItem::TYPE_PER_PARTICIPANT
                ? '('.$item->quantity_value.' per participant)'
                : '',
        ]);

        $pdf = DomPdf::loadView('pdf.workshop-pick-list', [
            'template' => $pickListTemplate,
            'participants' => null,
            'calculatedItems' => $calculatedItems,
            'pickListNotes' => '',
            'workshopDrawingPath' => null,
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
        $content = app(PdfAttachmentAppender::class)->append($pdf->output(), $pickListTemplate->attachments);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="workshop-template-'.$pickListTemplate->id.'.pdf"',
        ]);
    }

    private function validateRequest(Request $request, ?PickListTemplate $template = null): array
    {
        $tasks = collect($request->input('tasks', []))
            ->map(function ($task): array {
                $task = is_array($task) ? $task : [];
                $subtasks = $task['subtasks'] ?? [];
                if (is_string($subtasks)) {
                    $decoded = json_decode($subtasks, true);
                    $subtasks = is_array($decoded) ? $decoded : [];
                }
                $task['subtasks'] = is_array($subtasks) ? $subtasks : [];

                return $task;
            })
            ->all();
        $request->merge(['tasks' => $tasks]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:255'],
            'participants' => ['nullable', 'string', 'max:255'],
            'run_sheet' => ['nullable', 'string'],
            'run_sheet_drawing_data' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', Rule::exists('media', 'name')],
            'attachment_uploads' => ['nullable', 'array'],
            'attachment_uploads.*' => ['file', 'max:'.max((int) round(Helpers::getMaxUploadSize(auth()->user()) / 1024), 1)],
            'tasks' => ['nullable', 'array'],
            'tasks.*.id' => array_filter([
                'nullable',
                'integer',
                $template ? Rule::exists('workshop_template_tasks', 'id')->where(
                    fn ($query) => $query->where('pick_list_template_id', $template->id)
                ) : null,
            ]),
            'tasks.*.name' => ['required', 'string', 'max:255'],
            'tasks.*.notes' => ['nullable', 'string'],
            'tasks.*.subtasks' => ['nullable', 'array'],
            'tasks.*.subtasks.*.title' => ['required', 'string', 'max:100'],
            'tasks.*.subtasks.*.content' => ['nullable', 'string'],
            'tasks.*.reminder_enabled' => ['nullable', 'boolean'],
            'tasks.*.reminder_offset_days' => ['nullable', 'required_if:tasks.*.reminder_enabled,1', 'integer', 'between:-365,365'],
            'tasks.*.reminder_time' => ['nullable', 'required_if:tasks.*.reminder_enabled,1', Rule::in(['06:00', '12:00', '16:00'])],
            'tasks.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.id' => array_filter([
                'nullable',
                'integer',
                $template ? Rule::exists('pick_list_template_items', 'id')->where(
                    fn ($query) => $query->where('pick_list_template_id', $template->id)
                ) : null,
            ]),
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity_type' => ['required', 'string', 'in:'.implode(',', PickListTemplateItem::TYPES)],
            'items.*.quantity_value' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['items'] = collect($validated['items'] ?? [])
            ->map(function (array $row): array {
                return [
                    'id' => isset($row['id']) && (int) $row['id'] > 0 ? (int) $row['id'] : null,
                    'item_name' => trim((string) ($row['item_name'] ?? '')),
                    'quantity_type' => (string) ($row['quantity_type'] ?? PickListTemplateItem::TYPE_PER_PARTICIPANT),
                    'quantity_value' => max(1, (int) ($row['quantity_value'] ?? 1)),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                ];
            })
            ->filter(fn (array $row): bool => $row['item_name'] !== '')
            ->values()
            ->all();

        $validated['tasks'] = collect($validated['tasks'] ?? [])
            ->map(fn (array $row): array => [
                'id' => isset($row['id']) && (int) $row['id'] > 0 ? (int) $row['id'] : null,
                'name' => trim((string) ($row['name'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                'subtasks' => collect($row['subtasks'] ?? [])
                    ->map(fn (array $subtask): array => [
                        'title' => trim((string) ($subtask['title'] ?? '')),
                        'content' => trim((string) ($subtask['content'] ?? '')),
                    ])
                    ->filter(fn (array $subtask): bool => $subtask['title'] !== '')
                    ->values()
                    ->all(),
                'reminder_enabled' => filter_var($row['reminder_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reminder_offset_days' => isset($row['reminder_offset_days']) && $row['reminder_offset_days'] !== '' ? (int) $row['reminder_offset_days'] : null,
                'reminder_time' => in_array(($row['reminder_time'] ?? null), ['06:00', '12:00', '16:00'], true) ? $row['reminder_time'] : null,
                'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            ])
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();
        $validated['attachments'] = array_values($validated['attachments'] ?? []);

        return $validated;
    }

    private function fillTemplate(PickListTemplate $template, array $validated): void
    {
        $template->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration' => trim((string) ($validated['duration'] ?? '')) ?: null,
            'participants' => trim((string) ($validated['participants'] ?? '')) ?: null,
            'run_sheet' => $validated['run_sheet'] ?? null,
            'run_sheet_drawing_data' => $validated['run_sheet_drawing_data'] ?? null,
        ]);
        $template->save();
    }

    private function syncItems(PickListTemplate $template, array $items): void
    {
        $existingItems = $template->items()->get()->keyBy(fn (PickListTemplateItem $item): int => (int) $item->id);
        $keptIds = [];

        foreach ($items as $index => $row) {
            $payload = [
                'item_name' => $row['item_name'],
                'quantity_type' => $row['quantity_type'],
                'quantity_value' => $row['quantity_value'],
                'sort_order' => $row['sort_order'] ?: (($index + 1) * 10),
            ];
            $itemId = isset($row['id']) ? (int) $row['id'] : null;

            if ($itemId !== null && $existingItems->has($itemId)) {
                $item = $existingItems->get($itemId);
                $item->fill($payload);
                $item->save();
                $keptIds[] = $itemId;

                continue;
            }

            $item = $template->items()->create($payload);
            $keptIds[] = (int) $item->id;
        }

        if ($keptIds === []) {
            $template->items()->delete();

            return;
        }

        $template->items()->whereNotIn('id', $keptIds)->delete();
    }

    private function syncTasks(PickListTemplate $template, array $tasks): void
    {
        $existingTasks = $template->tasks()->get()->keyBy(fn (WorkshopTemplateTask $task): int => (int) $task->id);
        $keptIds = [];

        foreach ($tasks as $index => $row) {
            $payload = [
                'name' => $row['name'],
                'notes' => $row['notes'],
                'subtasks' => $row['subtasks'],
                'reminder_enabled' => $row['reminder_enabled'],
                'reminder_offset_days' => $row['reminder_enabled'] ? $row['reminder_offset_days'] : null,
                'reminder_time' => $row['reminder_enabled'] ? $row['reminder_time'] : null,
                'sort_order' => ($index + 1) * 10,
            ];
            $taskId = isset($row['id']) ? (int) $row['id'] : null;

            if ($taskId !== null && $existingTasks->has($taskId)) {
                $task = $existingTasks->get($taskId);
                $task->fill($payload);
                $task->save();
                $keptIds[] = $taskId;

                continue;
            }

            $task = $template->tasks()->create($payload);
            $keptIds[] = (int) $task->id;
        }

        if ($keptIds === []) {
            $template->tasks()->delete();

            return;
        }

        $template->tasks()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function itemSuggestions(): array
    {
        return PickListTemplateItem::query()
            ->whereRaw("TRIM(item_name) <> ''")
            ->select('item_name')
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function storeAttachmentUploads(Request $request): array
    {
        $storedNames = [];
        foreach ($request->file('attachment_uploads', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $fileName = $this->uniqueMediaFileName($file->getClientOriginalName());
            $hash = hash_file('sha256', $file->path());
            $storage = Storage::disk('archive');
            $exists = $storage->exists($hash);
            if (! $exists && $file->storeAs('/', $hash, 'archive') === false) {
                continue;
            }

            $media = Media::query()->create([
                'title' => Helpers::filenameToTitle($fileName),
                'user_id' => auth()->id(),
                'name' => $fileName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'hash' => $hash,
                'storage_disk' => 'archive',
                'visibility' => 'private',
            ]);

            if (! $exists) {
                $media->generateVariants(false);
            }

            $storedNames[] = $media->name;
        }

        return $storedNames;
    }

    private function uniqueMediaFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = Helpers::cleanFileName(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'workshop-template-attachment';
        $fileName = $extension !== '' ? $name.'.'.$extension : $name;
        $increment = 1;

        while (Media::query()->whereKey($fileName)->exists()) {
            $fileName = $extension !== '' ? $name.'-'.$increment.'.'.$extension : $name.'-'.$increment;
            $increment++;
        }

        return $fileName;
    }
}
