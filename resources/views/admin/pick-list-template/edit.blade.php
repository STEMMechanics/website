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
                'subtasks' => collect($task->subtasks ?? [])->map(fn ($subtask) => [
                    'title' => (string) ($subtask['title'] ?? ''),
                    'content' => (string) ($subtask['content'] ?? ''),
                ])->values()->all(),
                'reminder_enabled' => (bool) ($task->reminder_enabled ?? false),
                'reminder_days' => abs((int) ($task->reminder_offset_days ?? 0)),
                'reminder_direction' => (int) ($task->reminder_offset_days ?? 0) < 0 ? 'before' : 'after',
                'reminder_time' => (string) ($task->reminder_time ?? '06:00'),
                'sort_order' => (int) ($task->sort_order ?? 0),
            ])->values()->all()
            : ($defaultSocialTasks ?? []);
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
    @push('head')
        @vite('resources/js/workshop-pick-list.js')
    @endpush
    <x-mast backRoute="admin.workshop-blueprint.index" backTitle="Workshop Blueprints">{{ $editing ? 'Edit' : 'Create' }} Workshop Blueprint</x-mast>

    <x-container class="mt-4">
        <x-admin.ai-status-toast id="workshop-blueprint-ai-toast" message="Preparing workshop copy…" detail="Blueprint details are being used to draft this content." progress-label="Workshop blueprint copy generation" />
        <form id="workshop-blueprint-form" method="POST" action="{{ route('admin.workshop-blueprint.'.($editing ? 'update' : 'store'), $template ?? []) }}" x-data="{
            items: @js($seedItems),
            tasks: @js($seedTasks),
            attachments: @js($seedAttachments),
            attachmentDetails: @js($seedAttachmentDetails),
            pendingAttachments: [],
            taskEditorIndex: null,
            taskEditorTab: 'details',
            taskEditorExpanded: false,
            submitting: false,
            ...window.SM.drawingCanvas({
                initialData: @js(old('run_sheet_canvas_data', $template->run_sheet_canvas_data ?? '')),
                initialImage: @js(old('run_sheet_drawing_data', $template->run_sheet_drawing_data ?? '')),
                exportName: @js(($template->name ?? 'workshop-template').'-drawing'),
            }),
            seededBlankTask() {
                return { id: null, name: '', notes: '', subtasks: [], reminder_enabled: false, reminder_days: 0, reminder_direction: 'before', reminder_time: '06:00', sort_order: 0 };
            },
            defaultSocialPostTasks() {
                return [
                    { name: 'Social Media: Workshop Announcement', notes: '<p>Something exciting is coming up! Join us at {location} on {date-long}, {time-range}, for a hands-on STEM workshop for ages {ages}.</p><p>The workshop costs {cost}. See the details and current booking status here: {workshop-url}</p>', reminder_enabled: true, reminder_days: 14, reminder_direction: 'before', reminder_time: '12:00' },
                    { name: 'Social Media: Before the Workshop', notes: '<p>Not long now until our hands-on STEM workshop at {location} on {date-long}, {time-range}!</p><p>Makers aged {ages} can dive into creative building and problem-solving. The session costs {cost}. See the details and current booking status: {workshop-url}</p>', reminder_enabled: true, reminder_days: 3, reminder_direction: 'before', reminder_time: '12:00' },
                    { name: 'Workshop Packing', notes: '', reminder_enabled: true, reminder_days: 7, reminder_direction: 'before', reminder_time: '16:00' },
                    { name: 'Social Media: Workshop Day Post', notes: '<p>It is workshop day! Today, {date-long}, we are at {location} for a hands-on STEM session full of making, testing and creative problem-solving.</p><p>We cannot wait to see what everyone creates. Workshop details: {workshop-url}</p>', reminder_enabled: true, reminder_days: 0, reminder_direction: 'before', reminder_time: '06:00' },
                    { name: 'Social Media: After the Workshop', notes: '<p>That is a wrap on {date-long} at {location}! We loved seeing the creativity, teamwork and ideas from everyone who joined us for a hands-on STEM session.</p><p>Thanks for being part of it. Find the workshop details here: {workshop-url}</p>', reminder_enabled: true, reminder_days: 1, reminder_direction: 'after', reminder_time: '12:00' },
                ];
            },
            allDefaultSocialPostTasksAdded() {
                const existing = new Set(this.tasks.map((task) => String(task.name || '').trim().toLowerCase()));
                return this.defaultSocialPostTasks().every((task) => existing.has(task.name.toLowerCase()));
            },
            addDefaultSocialPostTasks(trigger = null) {
                const existing = new Set(this.tasks.map((task) => String(task.name || '').trim().toLowerCase()));
                const defaults = this.defaultSocialPostTasks().filter((task) => !existing.has(task.name.toLowerCase()));
                const insertionIndex = this.hasSingleTrailingBlankTask() ? this.tasks.length - 1 : this.tasks.length;
                const addedTasks = defaults.map((task, index) => ({
                    id: null,
                    ...task,
                    subtasks: [],
                    sort_order: (insertionIndex + index + 1) * 10,
                }));
                this.tasks.splice(insertionIndex, 0, ...addedTasks);
                this.ensureSingleTrailingBlankTask();
                const socialTaskNames = new Set(this.defaultSocialPostTasks()
                    .filter((task) => task.name.startsWith('Social Media:'))
                    .map((task) => task.name));
                const socialTasks = this.tasks.filter((task) => socialTaskNames.has(task.name));
                if (trigger && socialTasks.length > 0) {
                    const context = this.blueprintFormContext();
                    context.target = {
                        social_tasks: socialTasks.map((task) => ({ name: task.name, current_content: task.notes })),
                    };
                    trigger.dataset.aiContext = JSON.stringify(context);
                }

                return socialTasks.length > 0;
            },
            applyDefaultSocialPostCopies(detail) {
                const result = detail?.result || {};
                const htmlByKey = detail?.htmlByKey || {};
                const socialTasks = detail?.context?.target?.social_tasks || [];
                const keysByTaskName = {
                    'Social Media: Workshop Announcement': 'announcement',
                    'Social Media: Before the Workshop': 'before_workshop',
                    'Social Media: Workshop Day Post': 'workshop_day',
                    'Social Media: After the Workshop': 'after_workshop',
                };

                socialTasks.forEach(({ name, current_content }) => {
                    const task = this.tasks.find((candidate) => candidate.name === name);
                    const resultKey = keysByTaskName[name];
                    if (!task || !resultKey || task.notes !== current_content || typeof result[resultKey] !== 'string' || !result[resultKey].trim() || typeof htmlByKey[resultKey] !== 'string') return;
                    task.notes = htmlByKey[resultKey];
                });
            },
            openTaskEditor(index) {
                if (!Array.isArray(this.tasks[index].subtasks)) this.tasks[index].subtasks = [];
                this.taskEditorTab = 'details';
                this.taskEditorExpanded = false;
                this.taskEditorIndex = index;
            },
            closeTaskEditor() {
                this.taskEditorIndex = null;
                this.taskEditorTab = 'details';
                this.taskEditorExpanded = false;
            },
            addSubtask() {
                if (this.taskEditorIndex === null) return;
                const subtasks = this.tasks[this.taskEditorIndex].subtasks;
                subtasks.push({ title: `Subtask ${subtasks.length + 1}`, content: '' });
                this.taskEditorTab = `subtask-${subtasks.length - 1}`;
            },
            removeSubtask(index) {
                if (this.taskEditorIndex === null) return;
                this.tasks[this.taskEditorIndex].subtasks.splice(index, 1);
                this.taskEditorTab = 'details';
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
            serializedTasks() {
                return JSON.stringify(this.tasks
                    .filter((task) => !this.isBlankTask(task))
                    .map((task) => ({
                        ...task,
                        reminder_enabled: task.reminder_enabled ? '1' : '0',
                        reminder_offset_days: task.reminder_direction === 'before'
                            ? -Math.abs(Number(task.reminder_days || 0))
                            : Math.abs(Number(task.reminder_days || 0)),
                    })));
            },
            blueprintFieldValue(name) {
                if (name === 'default_workshop_content') {
                    const editor = document.querySelector('[data-editor-name=default_workshop_content] .tiptap');
                    if (editor) return editor.innerText || editor.textContent || '';
                }
                const form = document.getElementById('workshop-blueprint-form');
                return form?.elements.namedItem(name)?.value || '';
            },
            blueprintFormContext() {
                const form = document.getElementById('workshop-blueprint-form');
                const value = (name) => form?.elements.namedItem(name)?.value || '';
                const descriptionEditor = document.querySelector('[data-editor-name=default_workshop_content] .tiptap');
                const defaultDescription = descriptionEditor
                    ? (descriptionEditor.innerText || descriptionEditor.textContent || '')
                    : value('default_workshop_content');
                return {
                    source: 'blueprint',
                    blueprint: {
                        id: @js($template->id ?? null),
                        name: value('name'), notes: value('description'), duration: value('duration'), participants: value('participants'),
                        default_title: value('default_workshop_title'), default_summary: value('default_workshop_summary'),
                        default_description: String(defaultDescription).trim().slice(0, 8000), hero_media_name: value('hero_media_name'),
                        run_sheet: String(value('run_sheet')).replace(/<[^>]*>/g, ' ').slice(0, 3500),
                        attachments: this.attachments.slice(0, 30),
                        materials: this.items.filter((item) => !this.isBlankItem(item)).slice(0, 50).map((item) => ({ item: item.item_name, quantity: item.quantity_value, basis: item.quantity_type })),
                        tasks: this.tasks.filter((task) => !this.isBlankTask(task)).slice(0, 20).map((task) => ({
                            name: task.name,
                            notes: String(task.notes || '').replace(/<[^>]*>/g, ' ').slice(0, 400),
                            subtasks: (task.subtasks || []).slice(0, 4).map((subtask) => ({ title: subtask.title, content: String(subtask.content || '').replace(/<[^>]*>/g, ' ').slice(0, 180) })),
                        })),
                    },
                };
            },
            refreshBlueprintAiContext(trigger) {
                if (trigger) trigger.dataset.aiContext = JSON.stringify(this.blueprintFormContext());
            },
            taskAiContext(taskIndex, subtaskIndex = null) {
                const context = this.blueprintFormContext();
                const task = this.tasks[taskIndex] || {};
                const subtask = subtaskIndex === null ? null : ((task.subtasks || [])[subtaskIndex] || null);
                context.target = {
                    task_index: taskIndex,
                    subtask_index: subtaskIndex,
                    task_name: task.name || '',
                    subtask_title: subtask?.title || '',
                    current_content: String(subtask ? (subtask.content || '') : (task.notes || '')).slice(0, 8000),
                };
                return context;
            },
            applyTaskAiContent(detail) {
                if (detail?.context?.source !== 'blueprint') return;
                const target = detail.context.target || {};
                const task = this.tasks[Number(target.task_index)];
                if (!task) return;
                if (target.subtask_index === null || target.subtask_index === undefined) {
                    task.notes = detail.html;
                    return;
                }
                if (task.subtasks?.[Number(target.subtask_index)]) task.subtasks[Number(target.subtask_index)].content = detail.html;
            },
            chooseAttachments() {
                window.SMMediaPicker.open(this.attachments, {
                    title: 'Select Workshop Blueprint Attachments',
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
        }" enctype="multipart/form-data" x-init="ensureSingleTrailingBlank(); ensureSingleTrailingBlankTask(); $nextTick(() => initCanvas())" x-on:submit.prevent="await saveDrawing(); submitting = true; $el.submit()" x-on:workshop-task-ai-copy.window="applyTaskAiContent($event.detail)" x-on:workshop-social-post-bundle.window="applyDefaultSocialPostCopies($event.detail)">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <input type="hidden" name="tasks_payload" x-bind:value="serializedTasks()">

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Blueprint overview</h2>
                <x-ui.input label="Blueprint Name" name="name" value="{{ old('name', $template->name ?? '') }}" />
                <x-ui.input type="textarea" label="Notes" name="description" value="{{ old('description', $template->description ?? '') }}" rows="3" />
                <x-ui.grid class="gap-4 md:grid-cols-2">
                    <x-ui.input label="Duration" name="duration" value="{{ old('duration', $template->duration ?? '') }}" placeholder="e.g. 1 hr, 1.5 hours, or 90 mins" />
                    <x-ui.input label="Participants" name="participants" value="{{ old('participants', $template->participants ?? '') }}" placeholder="e.g. 10 or 10-15" />
                </x-ui.grid>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <div class="mb-3"><h2 class="text-lg font-semibold">Default workshop details</h2><p class="mt-1 text-sm text-gray-600">These are copied into a new workshop and can be changed there.</p></div>
                <x-ui.input label="Default Workshop Title" name="default_workshop_title" value="{{ old('default_workshop_title', $template->default_workshop_title ?? $template->name ?? '') }}" />
                <div class="mb-4">
                    <div class="mb-1 flex items-center gap-1 pl-1">
                        <label for="default_workshop_summary" class="text-sm">Default Summary</label>
                        <x-ui.button type="button" variant="plain" class="inline-flex size-7 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-blueprint-ai-toast" data-ai-processing-message="Creating workshop summary…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-blueprint-form" data-ai-kind="workshop_summary" data-ai-result-key="content" data-ai-fill-target="default_workshop_summary" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(blueprintFormContext())" x-on:click="refreshBlueprintAiContext($event.currentTarget)" x-bind:data-ai-mode="String(blueprintFieldValue('default_workshop_summary')).trim() ? 'improve' : 'write'" aria-label="Develop summary from workshop description" title="Use the workshop description to draft or improve this summary" :disabled="blank(config('services.openai.api_key'))"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button>
                    </div>
                    <x-ui.input type="textarea" noLabel class="mb-0" id="default_workshop_summary" name="default_workshop_summary" value="{{ old('default_workshop_summary', $template->default_workshop_summary ?? '') }}" rows="3" />
                </div>
                <x-ui.editor name="default_workshop_content" label="Default Workshop Description" class="workshop-template-editor" value="{!! old('default_workshop_content', $template->default_workshop_content ?? '') !!}">
                    <x-slot:toolbar>
                        <button type="button" data-admin-ai data-ai-widget-target="#workshop-blueprint-ai-toast" data-ai-processing-message="Improving workshop description…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-blueprint-form" data-ai-kind="blueprint_description" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(blueprintFormContext())" x-on:click="refreshBlueprintAiContext($event.currentTarget)" x-bind:data-ai-mode="String(blueprintFieldValue('default_workshop_content')).trim() ? 'improve' : 'write'" data-ai-editor-field="default_workshop_content" data-ai-editor-format="workshop-description" aria-label="Replace and improve default workshop description" title="Replace and improve the current description" {{ blank(config('services.openai.api_key')) ? 'disabled' : '' }}>
                            <span class="relative inline-flex"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><i class="fa-solid fa-rotate absolute -right-2 -bottom-1 rounded-full bg-white p-px text-[9px]" aria-hidden="true"></i></span>
                        </button>
                        <button type="button" data-admin-ai data-ai-widget-target="#workshop-blueprint-ai-toast" data-ai-processing-message="Checking for missing learning outcomes…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-blueprint-form" data-ai-kind="blueprint_description_amend" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(blueprintFormContext())" x-on:click="refreshBlueprintAiContext($event.currentTarget)" x-bind:data-ai-mode="String(blueprintFieldValue('default_workshop_content')).trim() ? 'improve' : 'write'" data-ai-editor-field="default_workshop_content" data-ai-editor-format="workshop-description" aria-label="Add missing supported learning outcomes without changing the description" title="Add only supported learning outcomes that are missing. Existing description and outcomes are kept unchanged." {{ blank(config('services.openai.api_key')) ? 'disabled' : '' }}>
                            <span class="relative inline-flex"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><i class="fa-solid fa-plus absolute -right-2 -bottom-1 rounded-full bg-white p-px text-[9px]" aria-hidden="true"></i></span>
                        </button>
                    </x-slot:toolbar>
                </x-ui.editor>
                <div class="mt-4"><x-ui.media label="Default Hero Image" name="hero_media_name" value="{{ old('hero_media_name', $template->hero_media_name ?? '') }}" allow_uploads="true" public_usable_only="true" /></div>
            </div>

            <template x-teleport="#workshop-template-tasks">
            <div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h2 class="text-lg font-semibold">Tasks</h2>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-ui.button type="button" variant="plain" class="inline-flex size-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-blueprint-ai-toast" data-ai-processing-message="Drafting social posts from the workshop description…" data-ai-complete-message="Social post copy is ready. Review and edit it before saving." data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-blueprint-form" data-ai-kind="social_post_bundle" data-ai-mode="write" data-ai-result-key="announcement" data-ai-result-event="workshop-social-post-bundle" data-ai-context="{}" x-on:click="addDefaultSocialPostTasks($event.currentTarget)" x-bind:title="allDefaultSocialPostTasksAdded() ? 'Redraft the default social post copy with AI' : 'Add missing social post tasks and draft their copy with AI'" aria-label="Add missing tasks and draft or refresh social post copy"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i></x-ui.button>
                    </div>
                </div>
                <div class="space-y-3">
                    <template x-for="(task, index) in tasks" :key="task.id || `new-task-${index}`">
                        <x-ui.grid class="gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 md:grid-cols-[minmax(0,1fr)_auto]">
                            <div>
                                <label class="block text-sm pl-1 mb-1">Task</label>
                                <div class="flex gap-4 items-center">
                                    <x-ui.input-control class="bg-white block px-2.5 py-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="task.name" x-bind:required="!isBlankTask(task)" x-on:input="handleTaskRowChange(index)" />
                                    <x-ui.row-actions :menu="false">
                                        <x-ui.row-action label="Notes, subtasks, and reminder" icon="fa-solid fa-sliders" tone="neutral" type="button" x-on:click="openTaskEditor(index)" />
                                        <x-ui.row-action label="Move up" icon="fa-solid fa-arrow-up" tone="neutral" type="button" x-on:click="moveTask(index, -1)" x-bind:disabled="index === 0 || isBlankTask(task)" />
                                        <x-ui.row-action label="Move down" icon="fa-solid fa-arrow-down" tone="neutral" type="button" x-on:click="moveTask(index, 1)" x-bind:disabled="index >= tasks.length - 2 || isBlankTask(task)" />
                                        <x-ui.row-action label="Remove" icon="fa-solid fa-trash" tone="danger" type="button" x-on:click="removeTask(index)" />
                                    </x-ui.row-actions>
                                </div>
                                <div class="mt-1 pl-1 flex flex-wrap gap-2 text-xs text-gray-500">
                                    <span x-show="String(task.notes || '').trim() !== ''"><i class="fa-regular fa-note-sticky mr-1"></i>Has notes</span>
                                    <span x-show="task.reminder_enabled"><i class="fa-regular fa-bell mr-1"></i><span x-text="`${task.reminder_days} day${Number(task.reminder_days) === 1 ? '' : 's'} ${task.reminder_direction} at ${task.reminder_time}`"></span></span>
                                </div>
                            </div>
                        </x-ui.grid>
                    </template>
                </div>
            </div>

            <div x-show="taskEditorIndex !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain bg-black/50 p-4" x-on:keydown.escape.window="closeTaskEditor()" x-on:click.self="closeTaskEditor()" x-effect="document.body.classList.toggle('overflow-hidden', taskEditorIndex !== null)">
                <div class="max-h-[calc(100dvh-2rem)] w-full overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white p-5 shadow-xl" x-bind:class="taskEditorExpanded ? 'h-[calc(100dvh-2rem)] max-w-none' : 'max-w-4xl'" x-on:wheel.stop>
                    <template x-if="taskEditorIndex !== null && tasks[taskEditorIndex]">
                        <div>
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div><h3 class="text-lg font-semibold">Task Details</h3><p class="text-sm text-gray-600" x-text="tasks[taskEditorIndex].name || 'Untitled task'"></p></div>
                                <div class="flex items-center gap-3">
                                    <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-gray-800" x-on:click="taskEditorExpanded = !taskEditorExpanded" x-bind:title="taskEditorExpanded ? 'Restore task editor' : 'Expand task editor'" x-bind:aria-label="taskEditorExpanded ? 'Restore task editor' : 'Expand task editor'"><i class="fa-solid" x-bind:class="taskEditorExpanded ? 'fa-compress' : 'fa-expand'"></i></x-ui.button>
                                    <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-gray-800" x-on:click="closeTaskEditor()" title="Close task editor" aria-label="Close task editor"><i class="fa-solid fa-xmark"></i></x-ui.button>
                                </div>
                            </div>

                            <div class="mb-5 flex items-end gap-1 overflow-x-auto border-b border-gray-200" role="tablist">
                                <x-ui.button variant="plain" type="button" class="shrink-0 rounded-t-lg border border-b-0 px-4 py-2 text-sm font-semibold" x-bind:class="taskEditorTab === 'details' ? 'border-gray-300 bg-white text-primary-color' : 'border-transparent bg-gray-100 text-gray-600'" x-on:click="taskEditorTab = 'details'">Details and Alerts</x-ui.button>
                                <template x-for="(subtask, subtaskIndex) in tasks[taskEditorIndex].subtasks" :key="`subtask-tab-${subtaskIndex}`">
                                    <x-ui.button variant="plain" type="button" class="max-w-48 shrink-0 truncate rounded-t-lg border border-b-0 px-4 py-2 text-sm font-semibold" x-bind:class="taskEditorTab === `subtask-${subtaskIndex}` ? 'border-gray-300 bg-white text-primary-color' : 'border-transparent bg-gray-100 text-gray-600'" x-on:click="taskEditorTab = `subtask-${subtaskIndex}`" x-text="subtask.title || `Subtask ${subtaskIndex + 1}`"></x-ui.button>
                                </template>
                                <x-ui.button variant="plain" type="button" class="shrink-0 rounded-t-lg border border-transparent bg-sky-50 px-4 py-2 text-sm font-semibold text-primary-color hover:bg-sky-100" x-on:click="addSubtask()" title="Add subtask"><i class="fa-solid fa-plus"></i></x-ui.button>
                            </div>

                            <div x-show="taskEditorTab === 'details'">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    @include('admin.pick-list-template.partials.workshop-placeholder-help', ['label' => 'Notes'])
                                    <x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-blueprint-ai-toast" data-ai-processing-message="Writing task content…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-blueprint-form" data-ai-kind="task_content" data-ai-result-event="workshop-task-ai-copy" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(taskAiContext(taskEditorIndex))" x-bind:data-ai-mode="String(tasks[taskEditorIndex]?.notes || '').trim() ? 'improve' : 'write'" aria-label="Write or improve task content" title="Write or improve task content" :disabled="blank(config('services.openai.api_key'))"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button>
                                </div>
                                <x-ui.mini-editor x-model="tasks[taskEditorIndex].notes">
                                    <x-slot:toolbarActions><x-admin.workshop-placeholder-inserter /></x-slot:toolbarActions>
                                </x-ui.mini-editor>

                                <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <x-ui.checkbox label="Email a reminder to the workshop facilitator" :noWrapper="true" x-model="tasks[taskEditorIndex].reminder_enabled" />
                                    <x-ui.grid class="mt-4 gap-3 sm:grid-cols-3" x-show="tasks[taskEditorIndex].reminder_enabled">
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
                                    </x-ui.grid>
                                </div>
                            </div>

                            <template x-for="(subtask, subtaskIndex) in tasks[taskEditorIndex].subtasks" :key="`subtask-panel-${subtaskIndex}`">
                                <div x-show="taskEditorTab === `subtask-${subtaskIndex}`">
                                    <div class="mb-4 flex items-end gap-3">
                                        <label class="block min-w-0 flex-1"><span class="mb-1 block pl-1 text-sm">Tab title</span><x-ui.input-control type="text" maxlength="100" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900" x-model="subtask.title" /></label>
                                        <x-ui.button variant="plain" type="button" class="mb-1 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50" x-on:click="removeSubtask(subtaskIndex)"><i class="fa-solid fa-trash mr-1"></i>Remove</x-ui.button>
                                    </div>
                                    <div class="mb-1 flex items-center justify-between gap-2">
                                        @include('admin.pick-list-template.partials.workshop-placeholder-help', ['label' => 'Subtask content'])
                                        <x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-blueprint-ai-toast" data-ai-processing-message="Writing task content…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-blueprint-form" data-ai-kind="task_content" data-ai-result-event="workshop-task-ai-copy" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(taskAiContext(taskEditorIndex, subtaskIndex))" x-bind:data-ai-mode="String(subtask.content || '').trim() ? 'improve' : 'write'" aria-label="Write or improve subtask content" title="Write or improve subtask content" :disabled="blank(config('services.openai.api_key'))"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button>
                                    </div>
                                    <x-ui.mini-editor x-model="subtask.content">
                                        <x-slot:toolbarActions><x-admin.workshop-placeholder-inserter /></x-slot:toolbarActions>
                                    </x-ui.mini-editor>
                                </div>
                            </template>

                            <div class="mt-5 flex justify-end"><x-ui.button type="button" x-on:click="closeTaskEditor()">Done</x-ui.button></div>
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
                    <x-ui.table variant="plain" table-class="min-w-full border border-gray-200 rounded-md">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2 border-b border-gray-300">Item</th>
                                <th class="p-2 border-b border-gray-300 hidden md:table-cell text-center!">Type</th>
                                <th class="text-left p-2 border-b border-gray-300 hidden md:table-cell">Quantity</th>
                                <th class="text-center! p-2 border-b border-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-b border-gray-300 last:border-b-0">
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-model="item.id" :name="!isBlankItem(item) && item.id ? `items[${index}][id]` : null">
                                        <input type="hidden" x-model="item.sort_order" :name="!isBlankItem(item) ? `items[${index}][sort_order]` : null">
                                        <input type="hidden" x-model="item.item_name" x-bind:name="!isBlankItem(item) ? `items[${index}][item_name]` : null">
                                        <input type="hidden" x-model="item.quantity_type" x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_type]` : null">
                                        <input type="hidden" x-model="item.quantity_value" x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_value]` : null">

                                        <x-ui.grid class="md:hidden gap-2">
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
                                        </x-ui.grid>

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
                                    <td class="p-2 align-top hidden md:table-cell text-center!">
                                        <x-ui.select
                                            name="quantity_type_placeholder_desktop"
                                            label="Type"
                                            :noLabel="true"
                                            class="mb-0"
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
                                        <x-ui.row-actions :menu="false" class="h-full">
                                            <x-ui.row-action label="Move up" icon="fa-solid fa-arrow-up" tone="neutral" type="button" x-on:click="moveUp(index)" x-bind:disabled="index === 0 || isBlankItem(item)" />
                                            <x-ui.row-action label="Move down" icon="fa-solid fa-arrow-down" tone="neutral" type="button" x-on:click="moveDown(index)" x-bind:disabled="index >= (items.length - 2) || isBlankItem(item)" />
                                            <x-ui.row-action label="Remove" icon="fa-solid fa-trash" tone="danger" type="button" x-on:click="removeItem(index)" />
                                        </x-ui.row-actions>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </x-ui.table>
                </div>
            </div>

            <div id="workshop-template-tasks"></div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Run Sheet Instructions</h2>
                <x-ui.editor
                    name="run_sheet"
                    label="Instructions"
                    class="workshop-template-editor"
                    value="{!! old('run_sheet', $template->run_sheet ?? '') !!}"
                />

                <div class="mt-6">
                    <h3 class="mb-1 font-semibold">Drawing</h3>
                    <p class="mb-3 text-xs text-gray-500">Sketch layouts, wiring, assembly steps, or other visual notes for the run sheet.</p>
                    <input type="hidden" name="run_sheet_canvas_data" x-ref="canvasDataInput" value="{{ old('run_sheet_canvas_data', $template->run_sheet_canvas_data ?? '') }}">
                    <input type="hidden" name="run_sheet_drawing_data" x-ref="canvasImageInput" value="{{ old('run_sheet_drawing_data', $template->run_sheet_drawing_data ?? '') }}">
                    @include('admin.shared.drawing-canvas', ['heightClass' => 'h-[60vh] min-h-[420px]'])
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div>
                        <h2 class="text-lg font-semibold">Attachments</h2>
                        <p class="text-xs text-gray-500">Files selected here remain linked to this blueprint and are copied when the blueprint is duplicated.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="chooseAttachments()">Select Attachments</x-ui.button>
                </div>
                <template x-for="name in attachments" :key="name">
                    <input type="hidden" name="attachments[]" x-bind:value="name">
                </template>
                <x-ui.input-control type="file" name="attachment_uploads[]" multiple class="hidden" x-ref="attachmentUploads" x-on:change="addAttachmentFiles($event.target.files)" />
                <div
                    class="mt-3 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center transition hover:border-primary-color hover:bg-sky-50"
                    x-on:dragover.prevent="$el.classList.add('border-primary-color', 'bg-sky-50')"
                    x-on:dragleave.prevent="$el.classList.remove('border-primary-color', 'bg-sky-50')"
                    x-on:drop.prevent="$el.classList.remove('border-primary-color', 'bg-sky-50'); addAttachmentFiles($event.dataTransfer.files)"
                    x-on:click="$refs.attachmentUploads.click()"
                >
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-400"></i>
                    <div class="mt-2 text-sm font-semibold text-gray-700">Drop files here or click to browse</div>
                    <div class="mt-1 text-xs text-gray-500">Files are uploaded when the blueprint is saved.</div>
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
                                <x-ui.button variant="plain" type="button" class="text-red-600 hover:text-red-700" x-on:click="attachments.splice(index, 1)" title="Remove attachment"><i class="fa-solid fa-xmark"></i></x-ui.button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-3 space-y-2" x-show="pendingAttachments.length > 0">
                    <template x-for="(item, index) in pendingAttachments" :key="item.key">
                        <div class="flex items-center justify-between gap-3 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm">
                            <div class="min-w-0"><div class="truncate" x-text="item.name"></div><div class="text-xs text-gray-500" x-text="`${item.file.type || 'File'} · ${attachmentSize(item.size)}`"></div></div>
                            <x-ui.button variant="plain" type="button" class="shrink-0 text-red-600 hover:text-red-700" x-on:click.stop="removePendingAttachment(index)" title="Remove pending upload"><i class="fa-solid fa-xmark"></i></x-ui.button>
                        </div>
                    </template>
                </div>
                <p x-show="attachments.length === 0 && pendingAttachments.length === 0" class="mt-3 text-sm text-gray-600">No attachments selected.</p>
            </div>

            <div class="flex justify-end gap-2">
                @if($editing)
                    <x-ui.button color="outline" href="{{ route('admin.workshop-blueprint.pdf', $template) }}" target="_blank">View PDF</x-ui.button>
                @endif
                <x-ui.button type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting">{{ $editing ? 'Save Blueprint' : 'Create Blueprint' }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span>{{ $editing ? 'Saving...' : 'Creating...' }}</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
