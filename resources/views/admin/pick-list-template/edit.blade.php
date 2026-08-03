@php
    $editing = isset($template);
    $seedItems = old('items');

    if (! is_array($seedItems)) {
        $seedItems = $editing
            ? $template->items->map(fn ($item) => [
                'id' => (int) $item->id,
                'item_name' => (string) $item->item_name,
                'quantity_type' => (string) $item->quantity_type,
                'quantity_value' => (int) $item->quantity_value,
                'sort_order' => (int) ($item->sort_order ?? 0),
            ])->values()->all()
            : [];
    }

    $seedTasks = old('tasks');
    if (! is_array($seedTasks)) {
        $seedTasks = $editing
            ? $template->tasks->map(fn ($task) => [
                'id' => (int) $task->id,
                'name' => (string) $task->name,
                'notes' => (string) ($task->notes ?? ''),
                'reminder_enabled' => (bool) ($task->reminder_enabled ?? false),
                'reminder_days' => abs((int) ($task->reminder_offset_days ?? 0)),
                'reminder_direction' => (int) ($task->reminder_offset_days ?? 0) < 0 ? 'before' : 'after',
                'reminder_time' => (string) ($task->reminder_time ?? '06:00'),
                'sort_order' => (int) ($task->sort_order ?? 0),
            ])->values()->all()
            : [];
    }

    $seedAttachments = collect(old('attachments', $editing ? $template->attachments->pluck('name')->all() : []))
        ->map(fn ($name) => (string) $name)
        ->filter()
        ->values()
        ->all();
    $seedAttachmentDetails = $editing
        ? $template->attachments->mapWithKeys(fn ($attachment) => [
            $attachment->name => [
                'type' => (string) $attachment->file_type,
                'size' => (int) $attachment->size,
                'view_url' => $attachment->url,
                'download_url' => $attachment->url.'?download=1',
            ],
        ])->all()
        : [];
@endphp

<x-layout>
    <x-mast backRoute="admin.workshop-template.index" backTitle="Workshop Templates">{{ $editing ? 'Edit' : 'Create' }} Workshop Template</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.workshop-template.'.($editing ? 'update' : 'store'), $template ?? []) }}" x-data="{
            items: @js($seedItems),
            tasks: @js($seedTasks),
            attachments: @js($seedAttachments),
            attachmentDetails: @js($seedAttachmentDetails),
            pendingAttachments: [],
            taskEditorIndex: null,
            submitting: false,
            drawingChanged: false,
            drawingContext: null,
            drawingActive: false,
            initDrawing() {
                const canvas = this.$refs.runSheetCanvas;
                if (!(canvas instanceof HTMLCanvasElement)) return;
                this.drawingContext = canvas.getContext('2d');
                this.drawingContext.lineCap = 'round';
                this.drawingContext.lineJoin = 'round';
                this.drawingContext.lineWidth = 3;
                this.drawingContext.strokeStyle = '#111827';
                const saved = String(this.$refs.runSheetDrawingInput?.value || '');
                if (saved !== '') {
                    const image = new Image();
                    image.onload = () => this.drawingContext.drawImage(image, 0, 0, canvas.width, canvas.height);
                    image.src = saved;
                }
            },
            drawingPoint(event) {
                const canvas = this.$refs.runSheetCanvas;
                const bounds = canvas.getBoundingClientRect();
                return {
                    x: (event.clientX - bounds.left) * (canvas.width / bounds.width),
                    y: (event.clientY - bounds.top) * (canvas.height / bounds.height),
                };
            },
            startDrawing(event) {
                if (!this.drawingContext) return;
                const point = this.drawingPoint(event);
                this.drawingActive = true;
                this.drawingContext.beginPath();
                this.drawingContext.moveTo(point.x, point.y);
                event.currentTarget.setPointerCapture?.(event.pointerId);
            },
            draw(event) {
                if (!this.drawingActive || !this.drawingContext) return;
                const point = this.drawingPoint(event);
                this.drawingContext.lineTo(point.x, point.y);
                this.drawingContext.stroke();
                this.drawingChanged = true;
            },
            stopDrawing() {
                if (!this.drawingActive) return;
                this.drawingActive = false;
                this.saveDrawing();
            },
            saveDrawing() {
                if (!this.drawingChanged) return;
                if (this.$refs.runSheetCanvas && this.$refs.runSheetDrawingInput) {
                    this.$refs.runSheetDrawingInput.value = this.$refs.runSheetCanvas.toDataURL('image/png');
                }
            },
            clearDrawing() {
                const canvas = this.$refs.runSheetCanvas;
                if (!canvas || !this.drawingContext) return;
                this.drawingContext.clearRect(0, 0, canvas.width, canvas.height);
                this.$refs.runSheetDrawingInput.value = '';
                this.drawingChanged = true;
            },
            seededBlankTask() {
                return { id: null, name: '', notes: '', reminder_enabled: false, reminder_days: 0, reminder_direction: 'before', reminder_time: '06:00', sort_order: 0 };
            },
            isBlankTask(task) {
                return String(task?.name || '').trim() === '';
            },
            hasSingleTrailingBlankTask() {
                if (this.tasks.length === 0) return false;
                const blankCount = this.tasks.filter((task) => this.isBlankTask(task)).length;
                return blankCount === 1 && this.isBlankTask(this.tasks[this.tasks.length - 1]);
            },
            ensureSingleTrailingBlankTask() {
                const nonBlank = this.tasks.filter((task) => !this.isBlankTask(task));
                this.tasks = [...nonBlank, this.seededBlankTask()];
                this.normalizeTaskSort();
            },
            handleTaskRowChange(index) {
                const isLastRow = index === (this.tasks.length - 1);
                if (isLastRow && !this.isBlankTask(this.tasks[index])) {
                    this.tasks.push(this.seededBlankTask());
                    this.normalizeTaskSort();
                    return;
                }

                if (!this.hasSingleTrailingBlankTask()) {
                    this.ensureSingleTrailingBlankTask();
                }
            },
            removeTask(index) {
                this.tasks.splice(index, 1);
                this.ensureSingleTrailingBlankTask();
            },
            moveTask(index, direction) {
                const destination = index + direction;
                if (this.isBlankTask(this.tasks[index]) || destination < 0 || destination >= this.tasks.length - 1) return;
                [this.tasks[index], this.tasks[destination]] = [this.tasks[destination], this.tasks[index]];
                this.normalizeTaskSort();
            },
            normalizeTaskSort() {
                this.tasks.forEach((task, index) => task.sort_order = (index + 1) * 10);
            },
            chooseAttachments() {
                window.SMMediaPicker.open(this.attachments, {
                    title: 'Select Workshop Template Attachments',
                    allow_multiple: true,
                    allow_uploads: true,
                    public_usable_only: false,
                }, (selected) => {
                    this.attachments = Array.isArray(selected) ? [...new Set(selected)] : [];
                });
            },
            addAttachmentFiles(fileList) {
                Array.from(fileList || []).forEach((file) => {
                    const key = `${file.name}:${file.size}:${file.lastModified}`;
                    if (!this.pendingAttachments.some((item) => item.key === key)) {
                        this.pendingAttachments.push({ key, file, name: file.name, size: file.size });
                    }
                });
                this.syncPendingAttachments();
            },
            removePendingAttachment(index) {
                this.pendingAttachments.splice(index, 1);
                this.syncPendingAttachments();
            },
            syncPendingAttachments() {
                if (!(this.$refs.attachmentUploads instanceof HTMLInputElement)) return;
                const transfer = new DataTransfer();
                this.pendingAttachments.forEach((item) => transfer.items.add(item.file));
                this.$refs.attachmentUploads.files = transfer.files;
            },
            attachmentSize(size) {
                const bytes = Number(size || 0);
                if (bytes < 1024) return `${bytes} B`;
                if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
                return `${(bytes / 1048576).toFixed(1)} MB`;
            },
            seededBlankItem(previousItem = null) {
                const previousType = String(previousItem?.quantity_type ?? '');

                return {
                    id: null,
                    item_name: '',
                    quantity_type: ['per_participant', 'fixed'].includes(previousType) ? previousType : 'per_participant',
                    quantity_value: 1,
                    sort_order: 0,
                };
            },
            isBlankItem(item) {
                const name = String(item?.item_name || '').trim();
                return name === '';
            },
            hasSingleTrailingBlank() {
                if (this.items.length === 0) {
                    return false;
                }
                const blankCount = this.items.filter((item) => this.isBlankItem(item)).length;
                return blankCount === 1 && this.isBlankItem(this.items[this.items.length - 1]);
            },
            ensureSingleTrailingBlank() {
                const nonBlank = this.items.filter((item) => !this.isBlankItem(item));
                const previousItem = nonBlank.length > 0 ? nonBlank[nonBlank.length - 1] : null;
                this.items = [...nonBlank, this.seededBlankItem(previousItem)];
                this.normalizeSort();
            },
            handleRowChange(index) {
                const isLastRow = index === (this.items.length - 1);
                if (isLastRow && !this.isBlankItem(this.items[index])) {
                    this.items.push(this.seededBlankItem(this.items[index]));
                    this.normalizeSort();
                    return;
                }

                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
            normalizeSort() {
                this.items = this.items.map((item, index) => ({
                    ...item,
                    sort_order: (index + 1) * 10,
                }));
            },
            addItem() {
                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
            removeItem(index) {
                this.items.splice(index, 1);
                this.ensureSingleTrailingBlank();
            },
            moveUp(index) {
                if (index <= 0) {
                    return;
                }
                const previous = this.items[index - 1];
                this.items[index - 1] = this.items[index];
                this.items[index] = previous;
                this.normalizeSort();
                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
            moveDown(index) {
                const lastRealIndex = this.items.length - 2;
                if (index >= lastRealIndex) {
                    return;
                }
                const next = this.items[index + 1];
                this.items[index + 1] = this.items[index];
                this.items[index] = next;
                this.normalizeSort();
                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
        }" enctype="multipart/form-data" x-init="ensureSingleTrailingBlank(); ensureSingleTrailingBlankTask(); $nextTick(() => initDrawing())" x-on:submit="saveDrawing(); submitting = true">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Overview</h2>
                <x-ui.input label="Template Name" name="name" value="{{ old('name', $template->name ?? '') }}" />
                <x-ui.input type="textarea" label="Notes" name="description" value="{{ old('description', $template->description ?? '') }}" rows="3" />
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.input label="Duration" name="duration" value="{{ old('duration', $template->duration ?? '') }}" placeholder="e.g. 1 hr, 1.5 hours, or 90 mins" />
                    <x-ui.input label="Participants" name="participants" value="{{ old('participants', $template->participants ?? '') }}" placeholder="e.g. 10 or 10-15" />
                </div>
            </div>

            <template x-teleport="#workshop-template-tasks">
            <div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">Tasks</h2>
                </div>
                <div class="space-y-3">
                    <template x-for="(task, index) in tasks" :key="task.id || `new-task-${index}`">
                        <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 md:grid-cols-[minmax(0,1fr)_auto]">
                            <div>
                                <label class="block text-sm pl-1 mb-1">Task</label>
                                <input type="hidden" x-bind:name="!isBlankTask(task) && task.id ? `tasks[${index}][id]` : null" x-model="task.id">
                                <input type="hidden" x-bind:name="!isBlankTask(task) ? `tasks[${index}][sort_order]` : null" x-model="task.sort_order">
                                <input type="hidden" x-bind:name="!isBlankTask(task) ? `tasks[${index}][notes]` : null" x-model="task.notes">
                                <input type="hidden" x-bind:name="!isBlankTask(task) ? `tasks[${index}][reminder_enabled]` : null" x-bind:value="task.reminder_enabled ? '1' : '0'">
                                <input type="hidden" x-bind:name="!isBlankTask(task) ? `tasks[${index}][reminder_offset_days]` : null" x-bind:value="task.reminder_direction === 'before' ? -Math.abs(Number(task.reminder_days || 0)) : Math.abs(Number(task.reminder_days || 0))">
                                <input type="hidden" x-bind:name="!isBlankTask(task) ? `tasks[${index}][reminder_time]` : null" x-model="task.reminder_time">
                                <div class="flex gap-4 items-center">
                                    <input class="bg-white block px-2.5 py-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-bind:name="!isBlankTask(task) ? `tasks[${index}][name]` : null" x-model="task.name" x-bind:required="!isBlankTask(task)" x-on:input="handleTaskRowChange(index)">
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" class="text-gray-700 hover:text-primary-color" x-on:click="taskEditorIndex = index" title="Notes and reminder"><i class="fa-solid fa-sliders"></i></button>
                                        <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveTask(index, -1)" x-bind:disabled="index === 0 || isBlankTask(task)" title="Move up"><i class="fa-solid fa-arrow-up"></i></button>
                                        <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveTask(index, 1)" x-bind:disabled="index >= tasks.length - 2 || isBlankTask(task)" title="Move down"><i class="fa-solid fa-arrow-down"></i></button>
                                        <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeTask(index)" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                                <div class="mt-1 pl-1 flex flex-wrap gap-2 text-xs text-gray-500">
                                    <span x-show="String(task.notes || '').trim() !== ''"><i class="fa-regular fa-note-sticky mr-1"></i>Has notes</span>
                                    <span x-show="task.reminder_enabled"><i class="fa-regular fa-bell mr-1"></i><span x-text="`${task.reminder_days} day${Number(task.reminder_days) === 1 ? '' : 's'} ${task.reminder_direction} at ${task.reminder_time}`"></span></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="taskEditorIndex !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="taskEditorIndex = null">
                <div class="w-full max-w-xl rounded-xl border border-gray-200 bg-white p-5 shadow-xl" x-on:click.outside="taskEditorIndex = null">
                    <template x-if="taskEditorIndex !== null && tasks[taskEditorIndex]">
                        <div>
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div><h3 class="text-lg font-semibold">Task Details</h3><p class="text-sm text-gray-600" x-text="tasks[taskEditorIndex].name || 'Untitled task'"></p></div>
                                <button type="button" class="text-gray-500 hover:text-gray-800" x-on:click="taskEditorIndex = null"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <label class="mb-1 block pl-1 text-sm">Notes</label>
                            <textarea rows="5" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900" x-model="tasks[taskEditorIndex].notes" placeholder="Plain text instructions included in the reminder email."></textarea>

                            <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <x-ui.checkbox label="Email a reminder to the workshop facilitator" :noWrapper="true" x-model="tasks[taskEditorIndex].reminder_enabled" />
                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3" x-show="tasks[taskEditorIndex].reminder_enabled">
                                    <x-ui.input type="number" min="0" max="365" step="1" label="Days" name="task_reminder_days_display" :noLabel="false" x-model="tasks[taskEditorIndex].reminder_days" />
                                    <x-ui.select label="When" name="task_reminder_direction_display" x-model="tasks[taskEditorIndex].reminder_direction">
                                        <option value="before">Before workshop</option>
                                        <option value="after">After workshop</option>
                                    </x-ui.select>
                                    <x-ui.select label="Time" name="task_reminder_time_display" x-model="tasks[taskEditorIndex].reminder_time">
                                        <option value="06:00">6:00am</option>
                                        <option value="12:00">12:00pm</option>
                                        <option value="16:00">4:00pm</option>
                                    </x-ui.select>
                                </div>
                            </div>
                            <div class="mt-5 flex justify-end"><x-ui.button type="button" x-on:click="taskEditorIndex = null">Done</x-ui.button></div>
                        </div>
                    </template>
                </div>
            </div>
            </div>
            </template>

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 overflow-x-auto overflow-y-visible shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">Pick List</h2>
                </div>

                <template x-if="items.length === 0">
                    <p class="text-sm text-gray-600">No items yet. Add your first pick list item.</p>
                </template>

                <div x-show="items.length > 0">
                    <table class="min-w-full border border-gray-200 rounded-md">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2 border-b">Item</th>
                                <th class="text-left p-2 border-b hidden md:table-cell">Type</th>
                                <th class="text-left p-2 border-b hidden md:table-cell">Quantity</th>
                                <th class="text-left p-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-b last:border-b-0">
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-model="item.id" :name="!isBlankItem(item) && item.id ? `items[${index}][id]` : null">
                                        <input type="hidden" x-model="item.sort_order" :name="!isBlankItem(item) ? `items[${index}][sort_order]` : null">
                                        <input type="hidden" x-model="item.item_name" x-bind:name="!isBlankItem(item) ? `items[${index}][item_name]` : null">
                                        <input type="hidden" x-model="item.quantity_type" x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_type]` : null">
                                        <input type="hidden" x-model="item.quantity_value" x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_value]` : null">

                                        <div class="md:hidden grid grid-cols-1 gap-2">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1 md:hidden">Item</label>
                                                <x-ui.input
                                                    name="item_name_placeholder"
                                                    label="Item"
                                                    :noLabel="true"
                                                    class="mb-0"
                                                    fieldClasses="mt-0"
                                                    :suggestions="$itemSuggestions ?? []"
                                                    x-model="item.item_name"
                                                    x-on:input="item.item_name = $event.target.value; handleRowChange(index)"
                                                    x-on:change="item.item_name = $event.target.value; handleRowChange(index)" />
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1 md:hidden">Type</label>
                                                    <x-ui.select
                                                        name="quantity_type_placeholder"
                                                        label="Type"
                                                        :noLabel="true"
                                                        class="mb-0"
                                                        x-model="item.quantity_type"
                                                        x-on:change="item.quantity_type = $event.target.value; handleRowChange(index)">
                                                        <option value="per_participant">Per Participant</option>
                                                        <option value="fixed">Fixed amount</option>
                                                    </x-ui.select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1 md:hidden">Quantity</label>
                                                    <x-ui.input
                                                        type="number"
                                                        name="quantity_value_placeholder"
                                                        label="Quantity"
                                                        :noLabel="true"
                                                        class="mb-0"
                                                        fieldClasses="mt-0"
                                                        min="1"
                                                        step="1"
                                                        x-model="item.quantity_value"
                                                        x-bind:required="!isBlankItem(item)"
                                                        x-on:input="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)"
                                                        x-on:change="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="hidden md:block">
                                            <x-ui.input
                                                name="item_name_placeholder_desktop"
                                                label="Item"
                                                :noLabel="true"
                                                class="mb-0"
                                                fieldClasses="mt-0"
                                                :suggestions="$itemSuggestions ?? []"
                                                x-model="item.item_name"
                                                x-on:input="item.item_name = $event.target.value; handleRowChange(index)"
                                                x-on:change="item.item_name = $event.target.value; handleRowChange(index)" />
                                        </div>
                                    </td>
                                    <td class="p-2 align-top hidden md:table-cell">
                                        <x-ui.select
                                            name="quantity_type_placeholder_desktop"
                                            label="Type"
                                            :noLabel="true"
                                            class="mx-0"
                                            x-model="item.quantity_type"
                                            x-on:change="item.quantity_type = $event.target.value; handleRowChange(index)">
                                            <option value="per_participant">Per Participant</option>
                                            <option value="fixed">Fixed amount</option>
                                        </x-ui.select>
                                    </td>
                                    <td class="p-2 align-top hidden md:table-cell">
                                        <x-ui.input
                                            type="number"
                                            name="quantity_value_placeholder_desktop"
                                            label="Quantity"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            min="1"
                                            step="1"
                                            x-model="item.quantity_value"
                                            x-bind:required="!isBlankItem(item)"
                                            x-on:input="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)"
                                            x-on:change="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-middle">
                                        <div class="flex items-center justify-center gap-3 h-full">
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveUp(index)" :disabled="index === 0 || isBlankItem(item)" title="Move up">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </button>
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveDown(index)" :disabled="index >= (items.length - 2) || isBlankItem(item)" title="Move down">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeItem(index)" title="Remove">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="workshop-template-tasks"></div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Run Sheet</h2>
                <x-ui.editor
                    name="run_sheet"
                    label="Instructions"
                    class="workshop-template-editor"
                    value="{!! old('run_sheet', $template->run_sheet ?? '') !!}"
                />

                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h3 class="font-semibold">Drawing</h3>
                            <p class="text-xs text-gray-500">Sketch layouts, wiring, assembly steps, or other visual notes for the run sheet.</p>
                        </div>
                        <x-ui.button type="button" color="outline" x-on:click="clearDrawing()">Clear Drawing</x-ui.button>
                    </div>
                    <input
                        type="hidden"
                        name="run_sheet_drawing_data"
                        x-ref="runSheetDrawingInput"
                        value="{{ old('run_sheet_drawing_data', $template->run_sheet_drawing_data ?? '') }}"
                    >
                    <div class="overflow-hidden rounded-lg border border-gray-300 bg-white touch-none">
                        <canvas
                            x-ref="runSheetCanvas"
                            width="1200"
                            height="500"
                            class="block h-72 w-full cursor-crosshair touch-none"
                            x-on:pointerdown.prevent="startDrawing($event)"
                            x-on:pointermove.prevent="draw($event)"
                            x-on:pointerup.prevent="stopDrawing()"
                            x-on:pointercancel.prevent="stopDrawing()"
                            x-on:pointerleave="stopDrawing()"
                        ></canvas>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div>
                        <h2 class="text-lg font-semibold">Attachments</h2>
                        <p class="text-xs text-gray-500">Files selected here remain linked to this template and are copied when the template is duplicated.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="chooseAttachments()">Select Attachments</x-ui.button>
                </div>
                <template x-for="name in attachments" :key="name">
                    <input type="hidden" name="attachments[]" x-bind:value="name">
                </template>
                <input type="file" name="attachment_uploads[]" multiple class="hidden" x-ref="attachmentUploads" x-on:change="addAttachmentFiles($event.target.files)">
                <div
                    class="mt-3 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center transition hover:border-primary-color hover:bg-sky-50"
                    x-on:dragover.prevent="$el.classList.add('border-primary-color', 'bg-sky-50')"
                    x-on:dragleave.prevent="$el.classList.remove('border-primary-color', 'bg-sky-50')"
                    x-on:drop.prevent="$el.classList.remove('border-primary-color', 'bg-sky-50'); addAttachmentFiles($event.dataTransfer.files)"
                    x-on:click="$refs.attachmentUploads.click()"
                >
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-400"></i>
                    <div class="mt-2 text-sm font-semibold text-gray-700">Drop files here or click to browse</div>
                    <div class="mt-1 text-xs text-gray-500">Files are uploaded when the template is saved.</div>
                </div>
                <div class="mt-3 space-y-2" x-show="attachments.length > 0">
                    <template x-for="(name, index) in attachments" :key="name">
                        <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <div class="min-w-0">
                                <div class="truncate" x-text="name"></div>
                                <div class="text-xs text-gray-500" x-text="attachmentDetails[name] ? `${attachmentDetails[name].type} · ${attachmentSize(attachmentDetails[name].size)}` : 'File details available after saving'"></div>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <a x-show="attachmentDetails[name]?.view_url" x-bind:href="attachmentDetails[name]?.view_url" target="_blank" class="text-gray-500 hover:text-primary-color" title="View attachment"><i class="fa-solid fa-eye"></i></a>
                                <a x-show="attachmentDetails[name]?.download_url" x-bind:href="attachmentDetails[name]?.download_url" class="text-gray-500 hover:text-primary-color" title="Download attachment"><i class="fa-solid fa-download"></i></a>
                                <button type="button" class="text-red-600 hover:text-red-700" x-on:click="attachments.splice(index, 1)" title="Remove attachment"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-3 space-y-2" x-show="pendingAttachments.length > 0">
                    <template x-for="(item, index) in pendingAttachments" :key="item.key">
                        <div class="flex items-center justify-between gap-3 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm">
                            <div class="min-w-0"><div class="truncate" x-text="item.name"></div><div class="text-xs text-gray-500" x-text="`${item.file.type || 'File'} · ${attachmentSize(item.size)}`"></div></div>
                            <button type="button" class="shrink-0 text-red-600 hover:text-red-700" x-on:click.stop="removePendingAttachment(index)" title="Remove pending upload"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </template>
                </div>
                <p x-show="attachments.length === 0 && pendingAttachments.length === 0" class="mt-3 text-sm text-gray-600">No attachments selected.</p>
            </div>

            <div class="flex justify-end gap-2">
                @if($editing)
                    <x-ui.button color="outline" href="{{ route('admin.workshop-template.pdf', $template) }}" target="_blank">View PDF</x-ui.button>
                @endif
                <x-ui.button type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting">{{ $editing ? 'Save Template' : 'Create Template' }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span>{{ $editing ? 'Saving...' : 'Creating...' }}</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
