@push('head')
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="/workshop-course.js?v={{ substr(hash_file('sha256', public_path('workshop-course.js')), 0, 12) }}"></script>
@endpush

@php
$workshopModel = $workshop ?? null;
$selectedBlueprint = $selectedBlueprint ?? null;
$ticketChangeNotificationRecipients = $ticketChangeNotificationRecipients ?? [];
$ticketChangeEmailDefaultTo = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
$ticketChangeEmailDefaultSubject = 'Workshop update: '.trim((string) ($workshopModel?->title ?? 'Workshop'));
$workshopContent = old('content', $workshopModel?->content ?? $selectedBlueprint?->default_workshop_content ?? '');
$workshopSummary = old('summary', $workshopModel?->summary ?? $selectedBlueprint?->default_workshop_summary ?? '');
$workshopStatusForForm = old('status', $workshopModel?->status ?? 'draft');
$workshopStartValue = old('starts_at', \App\Helpers::timestampNoSeconds($workshopModel?->starts_at ?? ''));
$workshopEndValue = old('ends_at', \App\Helpers::timestampNoSeconds($workshopModel?->ends_at ?? ''));
if (in_array($workshopStatusForForm, ['private', 'hidden'], true)) {
    $workshopStatusForForm = 'open';
}
$savedTickets = old('tickets_json');

if ($savedTickets === null) {
$savedTickets = isset($workshop)
? json_encode(
($workshop->tickets ?? collect())->map(fn ($ticket) => [
'id' => $ticket->id,
'status' => (int) $ticket->status,
'user_id' => (string) $ticket->user_id,
'firstname' => (string) ($ticket->firstname ?? ''),
'surname' => (string) ($ticket->surname ?? ''),
'email' => (string) ($ticket->email ?? ''),
'phone' => (string) ($ticket->phone ?? ''),
])->values()->all()
)
: '[]';
}

$pickListTemplateFieldValue = old('pick_list_template_id', $workshopModel?->pick_list_template_id ?? $selectedBlueprint?->id ?? '');
$pickListTemplateMode = old('pick_list_template_id') !== null
    ? $pickListTemplateFieldValue
    : (($workshopModel?->pick_list_is_customized) ? 'custom' : $pickListTemplateFieldValue);
$hasCustomPickList = (bool) ($workshopModel?->pick_list_is_customized);
$hasCustomPickListItems = collect($workshopModel?->pick_list_custom_items ?? [])
    ->contains(function ($item): bool {
        $item = is_array($item) ? $item : (array) $item;

        return (int) ($item['stock_item_id'] ?? 0) > 0 || trim((string) ($item['item_name'] ?? '')) !== '';
    });
$providedWorkshopTaskDrafts = $workshopTaskDrafts ?? null;
$submittedTaskDrafts = old('workshop_tasks_payload');
$decodedTaskDrafts = is_string($submittedTaskDrafts) ? json_decode($submittedTaskDrafts, true) : null;
$taskSource = $workshopModel?->runSheetTasks ?? $selectedBlueprint?->tasks ?? collect();
$workshopTaskDrafts = is_array($decodedTaskDrafts)
    ? $decodedTaskDrafts
    : (is_array($providedWorkshopTaskDrafts)
        ? $providedWorkshopTaskDrafts
        : collect($taskSource)->map(fn ($task) => [
        'id' => $workshopModel ? (int) $task->id : null,
        'blueprint_task_id' => $workshopModel ? ($task->blueprint_task_id ? (int) $task->blueprint_task_id : null) : (int) $task->id,
        'name' => (string) $task->name,
        'notes' => (string) ($task->notes ?? ''),
        'subtasks' => collect($task->subtasks ?? [])->map(fn ($subtask) => [
            'title' => (string) ($subtask['title'] ?? ''),
            'content' => (string) ($subtask['content'] ?? ''),
        ])->values()->all(),
        'reminder_enabled' => (bool) ($task->reminder_enabled ?? false),
        'reminder_offset_days' => $task->reminder_offset_days,
        'reminder_time' => (string) ($task->reminder_time ?? ''),
        'sort_order' => (int) ($task->sort_order ?? 0),
        ])->values()->all());
$soldTicketCount = (int) ($soldTicketCount ?? $activeTicketCount ?? 0);
$soldEarlyBirdTicketCount = (int) ($soldEarlyBirdTicketCount ?? 0);
$maxTicketsTotal = is_numeric($workshopModel?->max_tickets ?? null) ? max(0, (int) $workshopModel->max_tickets) : null;
$earlyBirdTicketLimitTotal = $workshopModel instanceof \App\Models\Workshop ? $workshopModel->earlyBirdTicketLimit() : null;
$earlyBirdPriceValue = old('early_bird_price', $workshopModel?->early_bird_price ?? '');
$earlyBirdEndsAtValue = old('early_bird_ends_at', \App\Helpers::timestampNoSeconds($workshopModel?->early_bird_ends_at ?? ''));
$earlyBirdTicketLimitValue = old('early_bird_ticket_limit', $workshopModel?->early_bird_ticket_limit ?? '');
$maxTicketsRemaining = is_numeric($maxTicketsRemaining ?? null) ? max(0, (int) $maxTicketsRemaining) : null;
$earlyBirdTicketCount = (int) ($earlyBirdTicketCount ?? 0);
$earlyBirdTicketLimitRemaining = is_numeric($earlyBirdTicketLimitRemaining ?? null) ? max(0, (int) $earlyBirdTicketLimitRemaining) : null;
$maxTicketsInfo = 'Set the overall capacity for the workshop.';
$earlyBirdTicketLimitInfo = 'Stops the offer once this many tickets are sold.';
$earlyBirdSectionSummaryParts = [];
if (trim((string) $earlyBirdPriceValue) !== '' && is_numeric($earlyBirdPriceValue)) {
    $earlyBirdSectionSummaryParts[] = 'Price $'.number_format((float) $earlyBirdPriceValue, 2);
}
if (trim((string) $earlyBirdEndsAtValue) !== '') {
    try {
        $earlyBirdSectionSummaryParts[] = 'Ends '.\Carbon\Carbon::parse((string) $earlyBirdEndsAtValue)->format('d M');
    } catch (\Throwable $exception) {
        $earlyBirdSectionSummaryParts[] = 'Ends '.trim((string) $earlyBirdEndsAtValue);
    }
}
if (trim((string) $earlyBirdTicketLimitValue) !== '' && is_numeric($earlyBirdTicketLimitValue) && (int) $earlyBirdTicketLimitValue > 0) {
    $earlyBirdSectionSummaryParts[] = 'Limit '.(int) $earlyBirdTicketLimitValue;
}
$earlyBirdSectionSummary = $earlyBirdSectionSummaryParts !== [] ? implode(' · ', $earlyBirdSectionSummaryParts) : 'No early bird settings';
$earlyBirdSectionOpen = $errors->hasAny(['early_bird_price', 'early_bird_ends_at', 'early_bird_ticket_limit'])
    || trim((string) $earlyBirdPriceValue) !== ''
    || trim((string) $earlyBirdEndsAtValue) !== ''
    || trim((string) $earlyBirdTicketLimitValue) !== '';
$defaultCategoryIds = $workshopModel?->categories?->pluck('id')->all()
    ?? $selectedBlueprint?->categories?->pluck('id')->all()
    ?? [];
$selectedCategoryIds = collect(old('category_ids', $defaultCategoryIds))
    ->map(fn ($id) => (string) $id)
    ->all();
$workshopTypeForForm = old('type', $workshopModel instanceof \App\Models\Workshop ? $workshopModel->locationType() : \App\Models\Workshop::TYPE_ONLINE);
$participantAttachmentNames = old('participant_files');
if (is_string($participantAttachmentNames)) {
    $participantAttachmentNames = json_decode($participantAttachmentNames, true);
}
if (! is_array($participantAttachmentNames)) {
    $participantAttachmentNames = $workshopModel?->participantAttachments()->pluck('media.name')->all() ?? [];
}
$participantAttachmentNames = collect($participantAttachmentNames)->map(fn ($name) => trim((string) $name))->filter()->unique()->values()->all();
if (isset($workshop) && in_array((string) $workshop->registration, ['tickets'], true)) {
    if ($maxTicketsTotal !== null) {
        if ($soldTicketCount >= $maxTicketsTotal) {
            $maxTicketsInfo = 'All '.$maxTicketsTotal.' ticket'.($maxTicketsTotal === 1 ? '' : 's').' are currently sold.';
        } elseif ($soldTicketCount > 0) {
            $maxTicketsInfo = $soldTicketCount.' of '.$maxTicketsTotal.' ticket'.($maxTicketsTotal === 1 ? '' : 's').' are currently sold.';
        } else {
            $maxTicketsInfo = 'No tickets are currently sold. '.$maxTicketsRemaining.' remaining before full.';
        }
    }

    if ($earlyBirdTicketLimitTotal !== null) {
        if ($soldEarlyBirdTicketCount >= $earlyBirdTicketLimitTotal) {
            $earlyBirdTicketLimitInfo = 'All '.$earlyBirdTicketLimitTotal.' early-bird ticket'.($earlyBirdTicketLimitTotal === 1 ? '' : 's').' are currently sold.';
        } elseif ($soldEarlyBirdTicketCount > 0) {
            $earlyBirdTicketLimitInfo = $soldEarlyBirdTicketCount.' of '.$earlyBirdTicketLimitTotal.' early-bird ticket'.($earlyBirdTicketLimitTotal === 1 ? '' : 's').' are currently sold.';
        } else {
            $earlyBirdTicketLimitInfo = 'No early-bird tickets are currently sold. '.$earlyBirdTicketLimitRemaining.' remaining at this limit.';
        }
    } elseif ($soldEarlyBirdTicketCount > 0) {
        $earlyBirdTicketLimitInfo = $soldEarlyBirdTicketCount.' early-bird ticket'.($soldEarlyBirdTicketCount === 1 ? '' : 's').' are currently sold.';
    }
}

$workshopEditorSteps = [
    ['id' => 'details', 'label' => 'Details', 'description' => 'Set the workshop title, format, venue and schedule.'],
    ['id' => 'registration', 'label' => 'Registration', 'description' => 'Choose how people book and set pricing and capacity.'],
    ['id' => 'public', 'label' => 'Public page', 'description' => 'Add the image, categories and information shown to visitors.'],
    ['id' => 'delivery', 'label' => 'Delivery plan', 'description' => 'Choose a blueprint and prepare workshop tasks and materials.'],
    ['id' => 'review', 'label' => 'Review & publish', 'description' => 'Check the key details and choose when the workshop is published.'],
];
$workshopEditorStepFields = [
    'details' => ['title', 'facilitator_user_id', 'type', 'format', 'location_id', 'requested_by_user_id', 'hosted_for_organisation_id', 'starts_at', 'ends_at', 'course_sessions'],
    'registration' => ['is_private', 'is_hidden', 'closes_at', 'private_code', 'price', 'price_info', 'registration', 'registration_data', 'max_tickets', 'max_attendance', 'early_bird_price', 'early_bird_ends_at', 'early_bird_ticket_limit', 'participant_information', 'participant_files', 'ticket_group_slug', 'optional_product_ids', 'welcome_enabled', 'welcome_subject', 'welcome_body', 'welcome_send_at', 'welcome_files'],
    'public' => ['hero_media_name', 'category_ids', 'summary', 'content'],
    'delivery' => ['pick_list_template_id', 'workshop_tasks_payload'],
    'review' => ['status', 'publish_at'],
];
$workshopEditorStep = (string) old('editor_step', 'details');
foreach ($workshopEditorStepFields as $step => $fieldNames) {
    $hasStepError = collect($errors->keys())->contains(fn (string $errorKey): bool => collect($fieldNames)->contains(
        fn (string $fieldName): bool => $errorKey === $fieldName || str_starts_with($errorKey, $fieldName.'.')
    ));
    if ($hasStepError) {
        $workshopEditorStep = $step;
        break;
    }
}
if (! collect($workshopEditorSteps)->contains(fn (array $step): bool => $step['id'] === $workshopEditorStep)) {
    $workshopEditorStep = 'details';
}

$workshopTabs = null;
if (isset($workshop)) {
    $workshopTabs = \App\Support\WorkshopNavigation::tabs($workshop);
}
@endphp
<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops" :tabs="$workshopTabs" :description="isset($workshop) ? view('admin.workshop.partials.mast-context', ['workshop' => $workshop]) : null">
        <x-slot>{{ isset($workshop) ? $workshop->title : 'Create Workshop' }}</x-slot>
        @isset($workshop)
            <x-slot:actions>
                <x-admin.workshop-public-page-action :workshop="$workshop" />
            </x-slot:actions>
        @endisset
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-admin.ai-status-toast id="workshop-ai-toast" message="Preparing workshop copy…" detail="Workshop and blueprint details are being used to draft the content." progress-label="Workshop content generation" />
        <form id="workshop-form" x-data="{
            ...SM.courseEditor(@js(old('format', $workshopModel?->format ?? 'workshop')), @js(old('course_sessions', $workshopModel?->course_sessions ?? []))),
            editorStep: @js($workshopEditorStep),
            editorStepDefinitions: @js($workshopEditorSteps),
            editorStepOrder: @js(collect($workshopEditorSteps)->pluck('id')->values()),
            editorStepIndex() { return this.editorStepOrder.indexOf(this.editorStep); },
            editorStepLabel() { return this.editorStepDefinitions.find((step) => step.id === this.editorStep)?.label || 'Workshop details'; },
            editorStepDescription() { return this.editorStepDefinitions.find((step) => step.id === this.editorStep)?.description || ''; },
            setEditorStep(step) {
                if (!this.editorStepOrder.includes(step)) return;
                this.editorStep = step;
                this.$nextTick(() => document.getElementById('workshop-editor-steps')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            },
            previousEditorStep() {
                const index = this.editorStepIndex();
                if (index > 0) this.setEditorStep(this.editorStepOrder[index - 1]);
            },
            nextEditorStep() {
                const index = this.editorStepIndex();
                if (index >= 0 && index < this.editorStepOrder.length - 1) this.setEditorStep(this.editorStepOrder[index + 1]);
            },
            reviewValue(name) {
                const field = this.$refs.workshopForm?.elements?.namedItem(name);
                return field && typeof field.value === 'string' ? field.value : '';
            },
            reviewDate(value) {
                if (!value) return 'Not set';
                const date = new Date(value);
                return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en-AU', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
            },
            reviewLocation() {
                if (this.type !== 'physical' && this.workshopFormat !== 'course') return 'Online';
                return this.locations.find((location) => String(location.id) === String(this.selectedLocationId))?.name || 'Location not selected';
            },
            validateWorkshopEditor(event) {
                const form = this.$refs.workshopForm;
                const invalidField = Array.from(form?.elements || []).find((field) => field.willValidate && !field.checkValidity());
                if (!invalidField) return true;
                const panel = invalidField.closest('[data-workshop-step-panel]');
                if (panel) this.editorStep = panel.dataset.workshopStepPanel;
                event.preventDefault();
                this.$nextTick(() => {
                    invalidField.focus({ preventScroll: true });
                    invalidField.reportValidity();
                });
                return false;
            },
            workshopTaskPreviewRevision: 0,
            type: @js($workshopTypeForForm),
            status: @js($workshopStatusForForm),
            originalStatus: @js(isset($workshopModel) ? (string) $workshopModel->status : $workshopStatusForForm),
            originalType: @js($workshopTypeForForm),
            isPrivate: @js((bool) old('is_private', isset($workshopModel) ? $workshopModel->isPrivate() : false)),
            isHidden: @js((bool) old('is_hidden', isset($workshopModel) ? (bool) $workshopModel->is_hidden : false)),
            registration: @js(old('registration', $workshopModel?->registration ?? 'none')),
            maxAttendance: @js(old('max_attendance', $workshopModel?->max_attendance ?? '')),
            participantInformationOpen: @js($errors->hasAny(['participant_information', 'participant_files', 'ticket_group_slug'])),
            participantFiles: @js($participantAttachmentNames),
            openParticipantFilePicker() {
                SMMediaPicker.open(this.participantFiles, {
                    allow_multiple: true,
                    allow_uploads: true,
                    public_usable_only: false,
                    title: 'Select Participant Attachments',
                    confirm_button_text: 'Use Selected Files',
                }, (result) => {
                    const values = Array.isArray(result) ? result : [result];
                    this.participantFiles = [...new Set(values.map((value) => String(value || '').trim()).filter(Boolean))];
                });
            },
            removeParticipantFile(index) {
                this.participantFiles.splice(index, 1);
            },
            maxTickets: @js(old('max_tickets', $workshopModel?->max_tickets ?? '')),
            earlyBirdPrice: @js((string) $earlyBirdPriceValue),
            earlyBirdEndsAt: @js((string) $earlyBirdEndsAtValue),
            earlyBirdTicketLimit: @js(old('early_bird_ticket_limit', $workshopModel?->early_bird_ticket_limit ?? '')),
            originalEarlyBirdTicketLimit: @js((int) ($workshopModel?->early_bird_ticket_limit ?? 0)),
            soldTicketCount: @js((int) ($soldTicketCount ?? 0)),
            soldEarlyBirdTicketCount: @js((int) ($soldEarlyBirdTicketCount ?? 0)),
            ticketGroupRaw: @js(old('ticket_group_slug', $workshopModel?->ticket_group_slug ?? '')),
            manualStartsAt: @js($workshopStartValue),
            manualEndsAt: @js($workshopEndValue),
            originalCourseSessions: @js($workshopModel?->course_sessions ?? []),
            originalFormat: @js($workshopModel?->format ?? 'workshop'),
            originalStartsAt: @js(isset($workshopModel) ? $workshopStartValue : ''),
            originalEndsAt: @js(isset($workshopModel) ? $workshopEndValue : ''),
            originalLocationId: @js(isset($workshopModel) ? trim((string) ($workshopModel->location_id ?? '')) : ''),
            ticketHolderNotificationCount: @js((int) ($ticketChangeNotificationRecipientCount ?? 0)),
            notifyTicketHolders: @js((bool) old('notify_ticket_holders', false)),
            ticketChangeEmailNotes: @js((string) old('ticket_change_email_notes', '')),
            ticketChangeEmailTo: @js((string) old('ticket_change_email_to', $ticketChangeEmailDefaultTo)),
            ticketChangeEmailCc: @js((string) old('ticket_change_email_cc', '')),
            ticketChangeEmailBcc: @js((string) old('ticket_change_email_bcc', implode(', ', $ticketChangeNotificationRecipients))),
            ticketChangeEmailSubject: @js((string) old('ticket_change_email_subject', $ticketChangeEmailDefaultSubject)),
            ticketChangeEmailBody: @js((string) old('ticket_change_email_body', '')),
            ticketChangeEmailOpen: false,
            workshopTitle: @js(old('title', $workshopModel?->title ?? $selectedBlueprint?->default_workshop_title ?? $selectedBlueprint?->name ?? '')),
            supportEmail: @js($ticketChangeEmailDefaultTo),
            originalLocationLabel: @js(isset($workshopModel) ? $workshopModel->getLocationName() : 'Online'),
            workshopCancelReasonDefault: @js("We're sorry, but this workshop has been cancelled. Please see below for your refund or credit details."),
            workshopCancelReason: @js((string) old('workshop_cancel_reason', '')),
            hasCustomPickList: @js($hasCustomPickList),
            hasCustomPickListItems: @js($hasCustomPickListItems),
            originalPickListTemplateId: @js((string) ($workshopModel?->pick_list_template_id ?? '')),
            pickListTemplateMode: @js((string) $pickListTemplateMode),
            pickListTemplateId: @js((string) $pickListTemplateFieldValue),
            pickListTemplateReset: false,
            blueprintOptions: @js(collect($pickListTemplates ?? [])->map(fn ($blueprint) => [
                'id' => (string) $blueprint->id,
                'name' => (string) $blueprint->name,
                'tasks_count' => (int) ($blueprint->tasks_count ?? 0),
                'items_count' => (int) ($blueprint->items_count ?? 0),
                'data_url' => route('admin.workshop-blueprint.data', $blueprint),
                'edit_url' => route('admin.workshop-blueprint.edit', $blueprint),
            ])->values()->all()),
            blueprintPickerOpen: false,
            blueprintSearch: '',
            blueprintOptionIndex: 0,
            blueprintDataCache: {},
            blueprintDataRequests: {},
            currentBlueprintSelectorLabel() {
                if (this.pickListTemplateMode === 'custom') return 'Custom pick list';
                if (!String(this.pickListTemplateId || '')) return 'No blueprint';
                return this.blueprintOptions.find((option) => option.id === String(this.pickListTemplateId))?.name || 'Select a blueprint';
            },
            filteredBlueprintOptions() {
                const options = [
                    { id: '', label: 'No blueprint', kind: 'blueprint' },
                    ...(this.hasCustomPickList ? [{ id: 'custom', label: 'Custom pick list', kind: 'custom' }] : []),
                    ...this.blueprintOptions.map((option) => ({ ...option, label: option.name, kind: 'blueprint' })),
                ];
                const query = String(this.blueprintSearch || '').trim().toLocaleLowerCase();
                return query ? options.filter((option) => option.label.toLocaleLowerCase().includes(query)) : options;
            },
            openBlueprintPicker() {
                this.blueprintPickerOpen = true;
                this.blueprintSearch = '';
                this.blueprintOptionIndex = 0;
                this.$nextTick(() => this.$refs.blueprintSearch?.focus());
            },
            toggleBlueprintPicker() {
                if (this.blueprintPickerOpen) {
                    this.blueprintPickerOpen = false;
                    return;
                }
                this.openBlueprintPicker();
            },
            moveBlueprintOption(direction) {
                const count = this.filteredBlueprintOptions().length;
                if (count === 0) return;
                this.blueprintOptionIndex = Math.max(0, Math.min(count - 1, this.blueprintOptionIndex + direction));
            },
            chooseBlueprintOption(option) {
                if (!option) return;
                this.updatePickListTemplateSelection(option.id);
                this.blueprintPickerOpen = false;
                this.blueprintSearch = '';
            },
            blueprintEditUrl() {
                return this.blueprintOptions.find((option) => option.id === String(this.pickListTemplateId || ''))?.edit_url || '#';
            },
            async loadBlueprintData(id) {
                const key = String(id || '');
                if (!key) throw new Error('Choose a workshop blueprint first.');
                if (this.blueprintDataCache[key]) return this.blueprintDataCache[key];
                if (this.blueprintDataRequests[key]) return this.blueprintDataRequests[key];

                const option = this.blueprintOptions.find((item) => item.id === key);
                if (!option?.data_url) throw new Error('Workshop blueprint could not be found.');
                const request = fetch(option.data_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(async (response) => {
                        if (!response.ok) throw new Error('Workshop blueprint could not be loaded.');
                        return response.json();
                    })
                    .then((data) => {
                        this.blueprintDataCache[key] = data;
                        return data;
                    });
                this.blueprintDataRequests[key] = request;
                try {
                    return await request;
                } finally {
                    delete this.blueprintDataRequests[key];
                }
            },
            workshopSummaryAiContext() {
                const form = document.getElementById('workshop-form');
                const value = (name) => {
                    const field = form?.elements?.namedItem(name);
                    return field && typeof field.value === 'string' ? field.value : '';
                };
                const descriptionEditor = document.querySelector('[data-editor-name=content] .tiptap');
                const description = descriptionEditor
                    ? (descriptionEditor.innerText || descriptionEditor.textContent || '')
                    : value('content').replace(/<[^>]*>/g, ' ');
                return {
                    source: 'workshop',
                    blueprint_id: value('pick_list_template_id'),
                    workshop: {
                        title: value('title'),
                        summary: value('summary'),
                        description: String(description).trim().slice(0, 4000),
                    },
                };
            },
            updatePickListTemplateSelection(value) {
                const nextValue = String(value ?? '');
                const wasCustom = this.pickListTemplateMode === 'custom';

                if (nextValue === 'custom') {
                    this.pickListTemplateMode = 'custom';
                    if (String(this.pickListTemplateId || '') === '' && String(this.originalPickListTemplateId || '') !== '') {
                        this.pickListTemplateId = this.originalPickListTemplateId;
                    }
                    this.pickListTemplateReset = false;
                } else {
                    this.pickListTemplateMode = nextValue;
                    this.pickListTemplateId = nextValue;
                    this.pickListTemplateReset = wasCustom && this.hasCustomPickList && !this.hasCustomPickListItems;
                }

                this.$dispatch('workshop-blueprint-selected', {
                    blueprintId: String(this.pickListTemplateId || ''),
                    mode: this.pickListTemplateMode,
                });
            },
            syncRegistrationData() {
                const form = this.$refs.workshopForm;
                const registrationData = form?.elements?.namedItem('registration_data');
                if (!registrationData) {
                    return;
                }

                const fieldName = {
                    link: 'registration_url',
                    email: 'registration_email',
                    message: 'registration_message',
                }[String(this.registration || '')];
                const source = fieldName ? form.elements.namedItem(fieldName) : null;
                registrationData.value = source ? String(source.value || '') : '';
            },
            locations: @js(\App\Models\Location::orderByRaw(" name='Online' DESC, name ASC")->get()->map(fn ($location) => [
            'id' => (string) $location->id,
            'name' => (string) $location->name,
            'address' => (string) ($location->address ?? ''),
            ])->values()->all()),
            selectedLocationId: @js(old('location_id', $workshopModel?->location_id ?? '')),
            createLocationOpen: false,
            createLocationSubmitting: false,
            createLocationError: '',
            cancelWorkshopOpen: false,
            newLocation: {
            name: '',
            address: '',
            url: '',
            address_url: '',
            },
            availableUsers: @js(($users ?? collect())->map(fn ($user) => [
            'id' => (string) $user->id,
            'firstname' => (string) ($user->firstname ?? ''),
            'surname' => (string) ($user->surname ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            ])->values()->all()),
            tickets: (() => {
            try {
            const parsed = JSON.parse(@js($savedTickets));
            if (!Array.isArray(parsed)) {
            return [];
            }

            return parsed.map((ticket) => ({
            id: ticket.id || null,
            status: Number.isFinite(parseInt(ticket.status, 10)) ? parseInt(ticket.status, 10) : 0,
            user_id: (ticket.user_id || '').toString(),
            firstname: ticket.firstname || '',
            surname: ticket.surname || '',
            email: ticket.email || '',
            phone: ticket.phone || '',
            }));
            } catch (e) {
            return [];
            }
            })(),
            serializeTickets() {
            const cleaned = this.tickets
            .map((ticket) => ({
            id: ticket.id || null,
            status: Number.isFinite(parseInt(ticket.status, 10)) ? parseInt(ticket.status, 10) : 0,
            user_id: (ticket.user_id || '').toString().trim(),
            firstname: (ticket.firstname || '').trim(),
            surname: (ticket.surname || '').trim(),
            email: (ticket.email || '').trim(),
            phone: (ticket.phone || '').trim(),
            }))
            .filter((ticket) => ticket.user_id !== '');

            this.$refs.ticketsJson.value = JSON.stringify(cleaned);
            },
            addTicket() {
            this.tickets.push({
            id: null,
            status: 0,
            user_id: '',
            firstname: '',
            surname: '',
            email: '',
            phone: '',
            });
            this.serializeTickets();
            },
            removeTicket(index) {
            this.tickets.splice(index, 1);
            this.serializeTickets();
            },
            applyUserDefaults(index) {
            const selectedUserId = (this.tickets[index]?.user_id || '').toString();
            const selectedUser = this.availableUsers.find((user) => user.id === selectedUserId);
            if (!selectedUser) {
            return;
            }

            this.tickets[index].firstname = selectedUser.firstname || '';
            this.tickets[index].surname = selectedUser.surname || '';
            this.tickets[index].email = selectedUser.email || '';
            this.tickets[index].phone = selectedUser.phone || '';
            this.serializeTickets();
            },
            initLocationSelection() {
            if (this.type !== 'physical' && this.workshopFormat !== 'course') {
            this.selectedLocationId = '';
            return;
            }
            const current = (this.selectedLocationId ?? '').toString();
            if (current !== '' && this.locations.some((location) => String(location.id) === current)) {
                this.selectedLocationId = current;
                return;
            }
            if (@js(isset($workshop)) || this.workshopFormat === 'course') {
                this.selectedLocationId = '';
                return;
            }
            if (this.locations.length > 0) {
            this.selectedLocationId = String(this.locations[0].id);
            } else {
            this.selectedLocationId = '';
            }
            },
            openCreateLocation() {
            this.createLocationError = '';
            this.newLocation = {
            name: '',
            address: '',
            url: '',
            address_url: '',
            };
            this.createLocationOpen = true;
            },
            closeCreateLocation() {
            this.createLocationOpen = false;
            this.createLocationError = '';
            },
            openCancelWorkshopModal() {
            if (!String(this.workshopCancelReason || '').trim()) {
                this.workshopCancelReason = this.workshopCancelReasonDefault;
            }

            this.cancelWorkshopOpen = true;
            },
            closeCancelWorkshopModal() {
            this.cancelWorkshopOpen = false;
            this.status = this.originalStatus;
            },
            openTicketChangeEmailModal() {
            if (!String(this.ticketChangeEmailBody || '').trim()) {
                this.ticketChangeEmailBody = this.defaultTicketChangeEmailBody();
            }
            this.ticketChangeEmailOpen = true;
            },
            closeTicketChangeEmailModal() {
            this.ticketChangeEmailOpen = false;
            },
            saveTicketChangeEmail(sendEmail) {
            this.notifyTicketHolders = Boolean(sendEmail);
            this.ticketChangeEmailOpen = false;
            this.submitForm();
            },
            defaultTicketChangeEmailBody() {
            return `<p>Hi @{{first_name}},</p><p>We wanted to let you know that a few details for your upcoming ${this.workshopTitle} workshop have changed.</p><p><strong>Updated details:</strong><br>Date/Time: ${this.ticketChangeEmailFormatDateTime(this.currentStartsAt())} – ${this.ticketChangeEmailFormatDateTime(this.currentEndsAt())}<br>Location: ${this.ticketChangeEmailNewLocation()}</p><p><strong>For reference, the previous details were:</strong><br>Date/Time: ${this.ticketChangeEmailFormatDateTime(this.originalStartsAt)} – ${this.ticketChangeEmailFormatDateTime(this.originalEndsAt)}<br>Location: ${this.originalLocationLabel}</p><p>We’re sorry for any inconvenience this change may cause. If you have any questions or need a hand, please contact us at ${this.supportEmail}.</p><p>We look forward to seeing you there!</p>`;
            },
            ticketChangeEmailNewLocation() {
            const selectedLocation = this.locations.find((location) => String(location.id) === this.normalizedCurrentLocationId());
            return this.type === 'stemcraft'
                ? 'STEMCraft'
                : (this.workshopFormat === 'course' || this.type === 'physical')
                    ? (selectedLocation?.name || 'Unknown location')
                    : 'Online';
            },
            ticketChangeEmailFormatDateTime(value) {
            const parsed = new Date(String(value || '').replace(' ', 'T'));
            return Number.isNaN(parsed.getTime()) ? String(value || '-') : parsed.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
            },
            confirmCancelWorkshop() {
            this.cancelWorkshopOpen = false;
            this.submitForm();
            },
            currentStartsAt() {
            return String(this.$refs.startsAt?.value || '').trim();
            },
            currentEndsAt() {
            return String(this.$refs.endsAt?.value || '').trim();
            },
            normalizedCurrentLocationId() {
            if (this.type !== 'physical' && this.workshopFormat !== 'course') {
            return '';
            }

            return String(this.selectedLocationId ?? '').trim();
            },
            parseEarlyBirdPrice(value) {
            const raw = String(value || '').trim();
            if (raw === '') {
                return null;
            }

            const amount = Number.parseFloat(raw);
            if (!Number.isFinite(amount) || amount < 0) {
                return null;
            }

            return amount.toFixed(2);
            },
            formatEarlyBirdDate(value) {
            const raw = String(value || '').trim();
            if (raw === '') {
                return '';
            }

            const datePart = raw.split('T')[0] || raw;
            const [year, month, day] = datePart.split('-');
            if (!year || !month || !day) {
                return raw;
            }

            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const monthIndex = Number.parseInt(month, 10) - 1;
            const monthLabel = monthNames[monthIndex] || month;

            return `${day} ${monthLabel}`;
            },
            earlyBirdSummaryText() {
            const parts = [];
            const price = this.parseEarlyBirdPrice(this.earlyBirdPrice);
            const endsAt = this.formatEarlyBirdDate(this.earlyBirdEndsAt);
            const limit = Number.parseInt(String(this.earlyBirdTicketLimit || ''), 10);

            if (price !== null) {
                parts.push(`Price $${price}`);
            }

            if (endsAt !== '') {
                parts.push(`Ends ${endsAt}`);
            }

            if (Number.isFinite(limit) && limit > 0) {
                parts.push(`Limit ${limit}`);
            }

            return parts.length > 0 ? parts.join(' · ') : 'No early bird settings';
            },
            currentEarlyBirdTicketLimit() {
            const value = Number.parseInt(String(this.earlyBirdTicketLimit || 0), 10);
            return Number.isFinite(value) && value > 0 ? value : 0;
            },
            soldStandardTicketCount() {
            return Math.max(0, Number.parseInt(String(this.soldTicketCount || 0), 10) - Number.parseInt(String(this.soldEarlyBirdTicketCount || 0), 10));
            },
            shouldConfirmEarlyBirdLimitIncrease() {
            if (!@js(isset($workshop))) {
            return false;
            }

            return this.currentEarlyBirdTicketLimit() > this.originalEarlyBirdTicketLimit
                && this.soldStandardTicketCount() > 0;
            },
            async confirmEarlyBirdLimitIncrease() {
            const nextLimit = this.currentEarlyBirdTicketLimit();
            const remaining = Math.max(0, nextLimit - Number.parseInt(String(this.soldTicketCount || 0), 10));
            const remainingLabel = remaining === 1 ? 'ticket' : 'tickets';

            if (typeof window.SM === 'undefined' || !window.SM || typeof window.SM.confirm !== 'function') {
            return true;
            }

            const result = await window.SM.confirm(
                'Increase early bird limit?',
                `This will mark existing tickets as early bird in order of purchase.<br><strong>${remaining}</strong> early bird ${remainingLabel} will remain after the update.<br><strong>This cannot be undone.</strong>`,
                'OK'
            );

            return Boolean(result?.isConfirmed);
            },
            hasRelevantTicketHolderChange() {
            if (!@js(isset($workshop)) || Number.parseInt(String(this.ticketHolderNotificationCount || 0), 10) <= 0) {
            return false;
            }

            if (this.status === 'cancelled') {
            return false;
            }

            return this.currentStartsAt() !== this.originalStartsAt
            || this.currentEndsAt() !== this.originalEndsAt
            || this.workshopFormat !== this.originalFormat
            || (this.workshopFormat === 'course' && JSON.stringify(this.courseSessions) !== JSON.stringify(this.originalCourseSessions))
            || String(this.type || '') !== String(this.originalType || '')
            || this.normalizedCurrentLocationId() !== this.originalLocationId;
            },
            submitForm() {
            this.syncRegistrationData();
            const form = this.$refs.workshopForm;
            const nativeSubmit = form?.ownerDocument?.defaultView?.HTMLFormElement?.prototype?.submit;
            if (typeof nativeSubmit !== 'function') {
            return;
            }

            nativeSubmit.call(form);
            },
            async handleSubmit(event) {
            this.syncRegistrationData();

            if (!this.validateWorkshopEditor(event)) {
                return;
            }

            if (this.status === 'cancelled' && this.originalStatus !== 'cancelled' && ['tickets'].includes(String(this.registration || ''))) {
            event.preventDefault();
            this.openCancelWorkshopModal();
            return;
            }

            if (this.shouldConfirmEarlyBirdLimitIncrease()) {
            event.preventDefault();
            if (!await this.confirmEarlyBirdLimitIncrease()) {
                return;
            }
            this.submitForm();
            return;
            }

            if (!this.hasRelevantTicketHolderChange()) {
            this.notifyTicketHolders = false;
            this.ticketChangeEmailNotes = '';
            return;
            }

            event.preventDefault();
            this.openTicketChangeEmailModal();
            },
            async submitCreateLocation() {
            if (this.createLocationSubmitting) {
            return;
            }
            this.createLocationSubmitting = true;
            this.createLocationError = '';
            try {
            const response = await fetch('{{ route('admin.location.store') }}', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(this.newLocation),
            });
            const payload = await response.json();
            if (!response.ok || !payload?.success || !payload?.location) {
            const firstError = payload?.errors ? Object.values(payload.errors)?.[0]?.[0] : null;
            throw new Error(firstError || payload?.message || 'Unable to create location.');
            }
            const location = payload.location;
            this.locations = [...this.locations, location]
            .filter((value, index, array) => array.findIndex((item) => item.id === value.id) === index)
            .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
            this.selectedLocationId = String(location.id);
            this.closeCreateLocation();
            } catch (error) {
            this.createLocationError = error?.message || 'Unable to create location.';
            } finally {
            this.createLocationSubmitting = false;
            }
            },
            async cancelTicketById(ticketId) {
            const id = parseInt(ticketId || 0, 10);
            if (!Number.isFinite(id) || id <= 0) {
                return;
                }

                const confirmed = await new Promise((resolve) => {
                    if (window.SM && typeof window.SM.confirm === 'function') {
                        window.SM.confirm(
                            'Confirm action',
                            'Cancel this ticket now? If applicable, a tax adjustment note and refund will be issued.',
                            'Cancel Ticket',
                            (isConfirmed) => resolve(Boolean(isConfirmed))
                        );
                        return;
                    }
                    resolve(false);
                });
                if (!confirmed) {
                    return;
                }

                const urlTemplate=@js(route('admin.ticket.cancel', ['ticket'=> '__TICKET__']));
                const actionUrl = urlTemplate.replace('__TICKET__', String(id));

                const response = await fetch(actionUrl, {
                method: 'POST',
                headers: {
                'X-CSRF-TOKEN': @js(csrf_token()),
                'Accept': 'application/json',
                },
                });

                if (!response.ok) {
                if (window.SM && typeof window.SM.notice === 'function') {
                    window.SM.notice('Action failed', 'Unable to cancel ticket right now.', 'danger');
                }
                return;
                }

                window.location.reload();
                },
                }" method="POST" action="{{ route('admin.workshop.' . (isset($workshop) ? 'update' : 'store'), $workshop ?? []) }}" enctype="multipart/form-data" novalidate x-init="initLocationSelection(); initCourseSchedule()" x-ref="workshopForm" x-on:input="workshopTaskPreviewRevision++" x-on:change="workshopTaskPreviewRevision++" x-on:submit="handleSubmit($event)">
                @isset($workshop)
                @method('PUT')
                @endisset
                @csrf
                <input type="hidden" name="notify_ticket_holders" :value="notifyTicketHolders ? '1' : '0'">
                <input type="hidden" name="ticket_change_email_notes" :value="ticketChangeEmailNotes">
                <input type="hidden" name="ticket_change_email_body" :value="ticketChangeEmailBody">
                <input type="hidden" name="workshop_cancel_reason" :value="workshopCancelReason">
                <input type="hidden" name="pick_list_template_id" :value="pickListTemplateId || ''">
                <input type="hidden" name="reset_pick_list_customization" :value="pickListTemplateReset ? '1' : '0'">
                <input type="hidden" name="editor_step" x-model="editorStep">

                <nav id="workshop-editor-steps" aria-label="Workshop editor steps" class="mb-6 scroll-mt-24">
                    <ol class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach($workshopEditorSteps as $stepIndex => $step)
                            <li class="min-w-0">
                                <button type="button" class="block w-full text-left" x-on:click="setEditorStep('{{ $step['id'] }}')" x-bind:aria-current="editorStep === '{{ $step['id'] }}' ? 'step' : null">
                                    <span class="block h-1.5 rounded-full transition-colors" x-bind:class="editorStepIndex() >= {{ $stepIndex }} ? 'bg-primary-color' : 'bg-gray-200'"></span>
                                    <span class="mt-2 block truncate text-xs" x-bind:class="editorStep === '{{ $step['id'] }}' ? 'font-semibold text-gray-900' : 'text-gray-500'">{{ $stepIndex + 1 }}. {{ $step['label'] }}</span>
                                </button>
                    </li>
                @endforeach
                    </ol>
                </nav>
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-color" x-text="`Step ${editorStepIndex() + 1} of ${editorStepOrder.length}`"></p>
                    <h2 class="mt-1 text-xl font-semibold text-gray-950" tabindex="-1" x-ref="editorStepHeading" x-text="editorStepLabel()"></h2>
                    <p class="mt-1 text-sm text-gray-600" x-text="editorStepDescription()"></p>
                </div>

                <div data-workshop-step-panel="details" x-show="editorStep === 'details'" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.input label="Title" name="title" x-model="workshopTitle" value="{{ old('title', $workshopModel?->title ?? $selectedBlueprint?->default_workshop_title ?? $selectedBlueprint?->name ?? '') }}" />
                    </div>
                    <div class="flex-1">
                        <x-ui.select
                            label="Facilitator"
                            name="facilitator_user_id"
                            value="{{ old('facilitator_user_id', $workshopModel?->facilitator_user_id ?? $workshopModel?->user_id ?? auth()->id()) }}"
                            info="Workshop task reminders are sent to this person. New workshops default to their creator."
                        >
                            @foreach(($facilitatorOptions ?? collect()) as $facilitator)
                                <option value="{{ $facilitator->id }}" @selected((string) old('facilitator_user_id', $workshopModel?->facilitator_user_id ?? $workshopModel?->user_id ?? auth()->id()) === (string) $facilitator->id)>
                                    {{ $facilitator->getName() }} · {{ $facilitator->email }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <input type="hidden" name="format" x-bind:value="workshopFormat">
                        <input type="hidden" name="type" value="{{ $workshopTypeForForm }}" x-bind:value="type">
                        <x-ui.select label="Type" id="workshop-type" x-bind:value="workshopFormat === 'course' ? 'course' : type"
                            x-on:change="workshopFormat = $event.target.value === 'course' ? 'course' : 'workshop'; type = $event.target.value === 'course' ? (type === 'stemcraft' ? 'physical' : type) : $event.target.value; if (type !== 'physical' && workshopFormat !== 'course') { selectedLocationId = '' } else { initLocationSelection() }; sessionChanged(); $nextTick(() => syncWorkshopClosesAt())">
                            <option value="physical">Physical</option>
                            <option value="online">Online</option>
                            <option value="stemcraft">STEMCraft</option>
                            <option value="course">Course</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <input type="hidden" name="location_id" x-bind:value="normalizedCurrentLocationId()">
                        <span x-show="type === 'physical' || workshopFormat === 'course'">
                            <x-ui.select label="Location" x-model="selectedLocationId" x-bind:disabled="type !== 'physical' && workshopFormat !== 'course'">
                                <x-slot name="labelRight">
                                    <x-ui.button variant="plain" type="button" class="text-primary-color cursor-pointer hover:underline" x-on:click.prevent="openCreateLocation()">Create new location</x-ui.button>
                                </x-slot>
                                <option value="">Select location</option>
                                <template x-for="location in locations" :key="location.id">
                                    <option :value="String(location.id)" :selected="String(selectedLocationId ?? '') === String(location.id)" x-text="location.name"></option>
                                </template>
                            </x-ui.select>
                        </span>
                    </div>
                </div>
                @php
                    $selectedContact = $workshopModel?->requestedBy;
                    $selectedHost = $workshopModel?->hostedFor;
                    $selectedContactLabel = $selectedContact
                        ? trim($selectedContact->getName().' · '.$selectedContact->email.($selectedContact->primaryOrganisation ? ' · '.$selectedContact->primaryOrganisation->name : ''))
                        : '';
                    $selectedHostLabel = $selectedHost
                        ? ($selectedHost->parent ? $selectedHost->parent->name.' — ' : '').$selectedHost->name
                        : '';
                @endphp
                <div class="flex flex-col sm:flex-row sm:gap-8"
                    x-data="{
                        contactId: @js((string) old('requested_by_user_id', $workshopModel?->requested_by_user_id ?? '')),
                        contactSearch: @js($selectedContactLabel),
                        contactResults: [],
                        contactSearching: false,
                        hostId: @js((string) old('hosted_for_organisation_id', $workshopModel?->hosted_for_organisation_id ?? '')),
                        hostSearch: @js($selectedHostLabel),
                        hostResults: [],
                        hostSearching: false,
                        contactSequence: 0,
                        hostSequence: 0,
                        async findContacts() {
                            this.contactId = '';
                            const term = this.contactSearch.trim();
                            const sequence = ++this.contactSequence;
                            if (term.length < 2) { this.contactResults = []; return; }
                            this.contactSearching = true;
                            try {
                                const url = new URL(@js(route('admin.organisation.contact-options')), window.location.origin);
                                url.searchParams.set('search', term);
                                url.searchParams.set('include_ghost', '1');
                                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                                const data = response.ok ? await response.json() : { users: [] };
                                if (sequence === this.contactSequence) this.contactResults = data.users || [];
                            } finally {
                                if (sequence === this.contactSequence) this.contactSearching = false;
                            }
                        },
                        chooseContact(contact) {
                            this.contactId = contact.id;
                            this.contactSearch = `${contact.name} · ${contact.email}${contact.organisation_name ? ` · ${contact.organisation_name}` : ''}`;
                            this.contactResults = [];
                            if (!this.hostId && contact.organisation_id) {
                                this.hostId = contact.organisation_id;
                                this.hostSearch = contact.organisation_name;
                            }
                        },
                        clearContact() {
                            this.contactId = '';
                            this.contactSearch = '';
                            this.contactResults = [];
                        },
                        async findHosts() {
                            this.hostId = '';
                            const term = this.hostSearch.trim();
                            const sequence = ++this.hostSequence;
                            if (term.length < 2) { this.hostResults = []; return; }
                            this.hostSearching = true;
                            try {
                                const url = new URL(@js(route('admin.organisation.options')), window.location.origin);
                                url.searchParams.set('search', term);
                                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                                const data = response.ok ? await response.json() : { organisations: [] };
                                if (sequence === this.hostSequence) this.hostResults = data.organisations || [];
                            } finally {
                                if (sequence === this.hostSequence) this.hostSearching = false;
                            }
                        },
                        chooseHost(organisation) {
                            this.hostId = organisation.id;
                            this.hostSearch = organisation.label;
                            this.hostResults = [];
                        },
                        clearHost() {
                            this.hostId = '';
                            this.hostSearch = '';
                            this.hostResults = [];
                        },
                    }"
                >
                    <div class="relative mb-4 flex-1" x-on:click.outside="contactResults = []">
                        <input type="hidden" name="requested_by_user_id" :value="contactId">
                        <label for="requested_by_user_search" class="mb-1 block pl-1 text-sm">Requested by / Contact</label>
                        <div class="flex gap-2">
                            <x-ui.input-control id="requested_by_user_search" type="search" x-model="contactSearch" x-on:input.debounce.350ms="findContacts()" autocomplete="off" placeholder="Search name, email, or organisation" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-indigo-300" />
                        </div>
                        <div class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" x-show="contactSearch.trim().length >= 2 && !contactSearching && contactResults.length > 0" x-cloak>
                            <template x-for="contact in contactResults" :key="contact.id">
                                <x-ui.button variant="plain" type="button" class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-sky-50" x-on:click="chooseContact(contact)">
                                    <span class="block text-gray-900" x-text="contact.name"></span>
                                    <span class="block text-xs text-gray-500" x-text="`${contact.email}${contact.organisation_name ? ` · ${contact.organisation_name}` : ''}`"></span>
                                </x-ui.button>
                            </template>
                        </div>
                        @error('requested_by_user_id')<div class="ml-2 mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                    </div>
                    <div class="relative mb-4 flex-1" x-on:click.outside="hostResults = []">
                        <input type="hidden" name="hosted_for_organisation_id" :value="hostId">
                        <div class="flex justify-between">
                            <label for="hosted_for_organisation_search" class="mb-1 block pl-1 text-sm">Hosted For</label>
                            <a href="{{ route('admin.organisation.index') }}" class="text-primary-color cursor-pointer hover:underline text-xs">Manage organisations</a>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.input-control id="hosted_for_organisation_search" type="search" x-model="hostSearch" x-on:input.debounce.350ms="findHosts()" autocomplete="off" placeholder="Search organisations" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-indigo-300" />
                        </div>
                        <div class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" x-show="hostSearch.trim().length >= 2 && !hostSearching && hostResults.length > 0" x-cloak>
                            <template x-for="organisation in hostResults" :key="organisation.id">
                                <x-ui.button variant="plain" type="button" class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-sky-50" x-on:click="chooseHost(organisation)" x-text="organisation.label"></x-ui.button>
                            </template>
                        </div>
                        @error('hosted_for_organisation_id')<div class="ml-2 mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                    </div>
                </div>
                </div>
            <div
                x-cloak
                x-show="createLocationOpen"
                x-on:keydown.escape.window="closeCreateLocation()"
                x-on:keydown.enter.prevent.stop="submitCreateLocation()"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true">
                    <div class="absolute inset-0 bg-black/40" x-on:click="closeCreateLocation()"></div>
                <div class="relative w-full max-w-xl rounded-xl bg-white p-5 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Create Location</h3>
                        <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-gray-700" x-on:click="closeCreateLocation()">
                            <i class="fa-solid fa-xmark"></i>
                        </x-ui.button>
                    </div>

                    <x-ui.grid class="gap-3">
                        <x-ui.input label="Name" name="new_location_name" x-model="newLocation.name" />
                        <x-ui.input label="Address" name="new_location_address" x-model="newLocation.address" />
                        <x-ui.input label="Location URL" name="new_location_url" x-model="newLocation.url" />
                        <x-ui.input label="Address URL" name="new_location_address_url" x-model="newLocation.address_url" />
                    </x-ui.grid>

                    <div x-show="createLocationError" x-text="createLocationError" class="mt-2 text-sm text-red-600"></div>

                    <div class="mt-5 flex justify-end gap-3">
                        <x-ui.button type="button" color="secondary" x-on:click.prevent.stop="closeCreateLocation()">Cancel</x-ui.button>
                        <x-ui.button type="button" x-bind:disabled="createLocationSubmitting" x-on:click.prevent.stop="submitCreateLocation()">
                            <span x-show="!createLocationSubmitting">Create Location</span>
                            <span x-show="createLocationSubmitting">Creating...</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
                <div data-workshop-step-panel="details" x-show="editorStep === 'details'" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.input
                            type="datetime-local"
                            label="Start Date"
                            name="starts_at"
                            value="{{ $workshopStartValue }}"
                            onchange="updatedStartsAt()"
                            x-ref="startsAt" x-on:blur="$dispatch('workshop-pricing-changed')"
                            x-on:input="manualStartsAt = $event.target.value"
                            x-on:change="manualStartsAt = $event.target.value"
                            x-bind:value="manualStartsAt"
                        />
                    </div>
                    <div class="flex-1">
                        <x-ui.input
                            type="datetime-local"
                            label="End Date"
                            name="ends_at"
                            value="{{ $workshopEndValue }}"
                            onchange="updatedEndsAt()"
                            x-ref="endsAt" x-on:blur="$dispatch('workshop-pricing-changed')"
                            x-on:input="manualEndsAt = $event.target.value"
                            x-on:change="manualEndsAt = $event.target.value"
                            x-bind:value="manualEndsAt"
                        />
                    </div>
                </div>
            @include('admin.workshop.partials.course-settings')
                </div>

            <div
                x-cloak
                x-show="cancelWorkshopOpen"
                x-on:keydown.escape.window="closeCancelWorkshopModal()"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true">
                    <div class="absolute inset-0 bg-black/40" x-on:click="closeCancelWorkshopModal()"></div>
                    <div class="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Cancel workshop?</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    This will cancel the workshop and any active tickets linked to it.
                                    Refunds will be attempted automatically for Square payments.
                                    Tickets that need manual follow-up will be listed in Refunds.
                                    Any pending workshop task reminders will be cancelled and will not be emailed.
                                </p>
                            </div>
                            <x-ui.button variant="plain" type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeCancelWorkshopModal()" aria-label="Close cancel workshop modal">
                                <i class="fa-solid fa-xmark"></i>
                            </x-ui.button>
                        </div>

                        <div class="mt-5">
                            <label class="mb-1 block text-sm font-semibold text-gray-900" for="workshop-cancel-reason">Cancellation reason</label>
                            <x-ui.textarea-control
                                id="workshop-cancel-reason"
                                rows="4"
                                x-model="workshopCancelReason"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-rose-300 focus:outline-none focus:ring-0"
                            ></x-ui.textarea-control>
                            <p class="mt-1 text-xs text-gray-600">This text replaces the opening line in cancellation emails and records.</p>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-ui.button type="button" color="primary-outline" x-on:click="closeCancelWorkshopModal()">Keep Workshop</x-ui.button>
                            <x-ui.button type="button" color="danger" x-on:click="confirmCancelWorkshop()">Cancel Workshop</x-ui.button>
                        </div>
                    </div>
                </div>

            <div
                x-cloak
                x-show="ticketChangeEmailOpen"
                x-on:keydown.escape.window="closeTicketChangeEmailModal()"
                class="fixed inset-0 z-50 overflow-y-auto p-3 sm:p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="ticket-change-email-title">
                <div class="absolute inset-0 bg-black/40" x-on:click="closeTicketChangeEmailModal()"></div>
                <div class="relative mx-auto my-2 flex h-[calc(100vh-1.5rem)] min-h-0 w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white p-4 shadow-xl sm:my-4 sm:h-[calc(100vh-2rem)] sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 id="ticket-change-email-title" class="text-lg font-semibold text-gray-900">Notify ticket holders?</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                This currently has <strong x-text="ticketHolderNotificationCount"></strong> active
                                <span x-text="Number(ticketHolderNotificationCount) === 1 ? 'ticket holder' : 'ticket holders'"></span>.
                                Notify the ticket holders of the workshop changes by email?
                            </p>
                        </div>
                        <x-ui.button variant="plain" type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeTicketChangeEmailModal()" aria-label="Close ticket holder notification modal">
                            <i class="fa-solid fa-xmark"></i>
                        </x-ui.button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-900" for="ticket-change-email-to">To</label>
                            <input id="ticket-change-email-to" name="ticket_change_email_to" type="text" autocomplete="off" data-bwignore="true" data-1p-ignore="true" data-lpignore="true" data-form-type="other" x-model="ticketChangeEmailTo" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" placeholder="hello@stemmechanics.com.au">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-900" for="ticket-change-email-cc">CC <span class="font-normal text-gray-500">(optional)</span></label>
                            <input id="ticket-change-email-cc" name="ticket_change_email_cc" type="text" autocomplete="off" data-bwignore="true" data-1p-ignore="true" data-lpignore="true" data-form-type="other" x-model="ticketChangeEmailCc" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" placeholder="email@example.com">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-semibold text-gray-900" for="ticket-change-email-bcc">BCC</label>
                            <textarea id="ticket-change-email-bcc" name="ticket_change_email_bcc" rows="2" autocomplete="off" data-bwignore="true" data-1p-ignore="true" data-lpignore="true" data-form-type="other" x-model="ticketChangeEmailBcc" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" placeholder="Separate addresses with commas, semicolons, or new lines"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-semibold text-gray-900" for="ticket-change-email-subject">Subject</label>
                            <input id="ticket-change-email-subject" name="ticket_change_email_subject" type="text" autocomplete="off" data-bwignore="true" data-1p-ignore="true" data-lpignore="true" data-form-type="other" x-model="ticketChangeEmailSubject" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0">
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <label class="block text-sm font-semibold text-gray-900">Message</label>
                            <span class="text-xs text-gray-500">Placeholders: @{{first_name}}, @{{last_name}}, @{{full_name}}</span>
                        </div>
                        <x-ui.mini-editor x-model="ticketChangeEmailBody" content-class="min-h-48" />
                        <p class="mt-1 text-xs text-gray-500">BCC sends one shared message. Name placeholders use a generic greeting when multiple ticket holders are included.</p>
                    </div>
                    </div>

                    <div class="mt-4 flex shrink-0 flex-col gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <x-ui.button type="button" color="primary-outline" x-on:click="closeTicketChangeEmailModal()">Cancel</x-ui.button>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <x-ui.button type="button" color="secondary" x-on:click="saveTicketChangeEmail(false)">Save</x-ui.button>
                            <x-ui.button type="button" x-on:click="saveTicketChangeEmail(true)">Save and Email</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>

                <div data-workshop-step-panel="registration" x-show="editorStep === 'registration'" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
            <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1 content-center flex gap-8">
                        <x-ui.checkbox
 label="Private Workshop"
 name="is_private"
 info="Visible to the public but requires an access code to register"
 value="1"
 :checked="(bool) old('is_private', isset($workshop) ? $workshop->isPrivate() : false)"
 x-model="isPrivate"
 no-wrapper="true"
 />
                        <x-ui.checkbox
 label="Hidden Workshop"
 name="is_hidden"
 info="Not displayed in public lists, search or newsletters"
 value="1"
 :checked="(bool) old('is_hidden', isset($workshop) ? (bool) $workshop->is_hidden : false)"
 x-model="isHidden"
 no-wrapper="true"
 />
                    </div>
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Closes Date" name="closes_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->closes_at ?? '') }}" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8" x-show="isPrivate">
                    <div class="flex-1">
                        <x-ui.input label="Private Code" name="private_code" value="{{ old('private_code', $workshop->private_code ?? '') }}" info="When set, users must enter this code before accessing private registration options." error="{{ $errors->first('private_code') }}" />
                    </div>
                    <div class="hidden flex-1 sm:block" aria-hidden="true"></div>
                </div>
                        @php
                            $ticketBudget = isset($workshop) ? \Illuminate\Support\Facades\DB::table('finance_budgets')->where('workshop_id', $workshop->id)->first() : null;
                            $ticketPlan = \App\Services\Finance\PricingVersion::forDate(today()->toDateString(), $ticketBudget ? (int) $ticketBudget->pricing_version_id : (old('pricing_version_id', $workshop->pricing_version_id ?? null) ?: null));
                            $ticketPlans = \Illuminate\Support\Facades\DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false)->orWhere('id', $ticketPlan->id)->orderBy('name')->get();
                            $ticketPlanOptions = $ticketPlans->mapWithKeys(fn ($version) => [$version->id => array_merge(json_decode($version->prices, true), ['rules' => json_decode($version->rules, true)])]);
                        @endphp
                        <div x-data="{
                            price: @js(old('price', $workshop->price ?? '')),
                            automatic: @js((bool) old('price_is_automatic', $workshop->price_is_automatic ?? !isset($workshop))),
                            planId: @js((string) $ticketPlan->id),
                            plans: @js($ticketPlanOptions),
                            get plan() { return this.plans[this.planId]; },
                            breakdown: { categories: {}, total: 0, participants: 0 },
                            maxBreakdown: { categories: {}, total: 0, participants: 0 },
                            previousPricingInputs: null,
                            reprice(force = false) {
                                const inputs = JSON.stringify([this.planId, this.registration, this.manualStartsAt, this.manualEndsAt, this.maxTickets, this.courseTeachingHours()]);
                                const changed = this.previousPricingInputs !== null && this.previousPricingInputs !== inputs;
                                this.previousPricingInputs = inputs;
                                this.breakdown = SM.ticketCostBreakdown(this.plan, this.manualStartsAt, this.manualEndsAt, this.maxTickets, true, this.courseTeachingHours());
                                this.maxBreakdown = SM.ticketCostBreakdown(this.plan, this.manualStartsAt, this.manualEndsAt, this.maxTickets, false, this.courseTeachingHours());
                                if (!force && (!this.automatic || (!changed && String(this.price ?? '').trim() !== ''))) return;
                                const next = SM.workshopPrice(this.plan, this.registration, this.price, this.manualStartsAt, this.manualEndsAt, this.maxTickets, force || this.automatic, this.courseTeachingHours());
                                if (this.registration === 'tickets' && (next !== this.price || force)) this.automatic = true;
                                this.price = next;
                            }
                        }" x-effect="const r = registration; $nextTick(() => reprice());" x-on:workshop-pricing-changed.window="reprice()">
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Registration" name="registration" x-model="registration" x-on:change="$nextTick(() => syncRegistrationData())">
                            <option value="none" {{ (old('registration', $workshop->registration ?? '')) === 'none' ? 'selected' : '' }}>None</option>
                            <option value="tickets" {{ (old('registration', $workshop->registration ?? '')) === 'tickets' ? 'selected' : '' }}>Tickets</option>
                            <option value="interest" {{ (old('registration', $workshop->registration ?? '')) === 'interest' ? 'selected' : '' }}>Interest</option>
                            <option value="link" {{ (old('registration', $workshop->registration ?? '')) === 'link' ? 'selected' : '' }}>External Link</option>
                            <option value="email" {{ (old('registration', $workshop->registration ?? '')) === 'email' ? 'selected' : '' }}>External Email</option>
                            <option value="message" {{ (old('registration', $workshop->registration ?? '')) === 'message' ? 'selected' : '' }}>Custom Message</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <div x-show="registration === 'tickets'" x-cloak>
                            <x-ui.select label="Allocation plan" name="pricing_version_id" x-model="planId" x-on:blur="reprice()" :disabled="(bool) $ticketBudget">
                                @foreach($ticketPlans as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}{{ $option->archived ? ' (archived)' : '' }}</option>
                                @endforeach
                            </x-ui.select>
                            @if($ticketBudget)
                                <input type="hidden" name="pricing_version_id" value="{{ $ticketPlan->id }}">
                                <p class="mb-3 text-xs text-slate-500">This workshop already has saved allocations using this plan.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                            <input type="hidden" name="price_is_automatic" x-bind:value="registration === 'tickets' && automatic ? 1 : 0">
                            <label class="block text-sm pl-1" for="workshop-price">Price</label>
                            <div class="relative mt-1">
                                <x-ui.input-control id="workshop-price" name="price" x-model="price" x-bind:class="registration === 'tickets' ? 'pr-11' : ''"
                                    x-on:input="automatic = false" x-on:blur="reprice()" />
                                <x-ui.button variant="plain" type="button" x-show="registration === 'tickets'" x-cloak
                                    aria-label="Refresh suggested ticket price" title="Refresh suggested ticket price from the allocation plan"
                                    class="absolute right-1 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-md text-slate-400 hover:bg-sky-50 hover:text-primary-color"
                                    x-on:click="reprice(true)">
                                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                                </x-ui.button>
                            </div>
                            @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            <p class="mb-4 mt-1 text-xs text-gray-500"><span x-show="registration === 'tickets'">Leave blank for free tickets.</span><span x-show="registration !== 'tickets'" class="ml-2 mt-1">Enter - to hide the price from public.</span> Also supports Free, TBD or TBC.</p>
                            <div x-show="registration === 'tickets' && parseFloat(String(price).replace(/[$,]/g, '')) > 0 && (type === 'physical' || workshopFormat === 'course') && locations.some(location => String(location.id) === String(selectedLocationId) && location.name.trim().toLowerCase() !== 'online')" x-cloak>
                                <input type="hidden" name="allow_pay_at_door" value="0">
                                <x-ui.checkbox name="allow_pay_at_door" value="1" label="Allow payment at the door" :checked="(bool) old('allow_pay_at_door', $workshopModel?->allow_pay_at_door ?? false)" />
                                <p class="mb-4 mt-1 text-xs text-gray-500">Enable only when payment can be collected at this venue. Online workshops require another payment method.</p>
                            </div>
                    </div>
                    <div class="flex-1">
                        <x-ui.input label="Ages" name="ages" info="Leave blank to hide from public" value="{{ $workshop->ages ?? '8+' }}" />
                    </div>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <span x-show="registration==='tickets'">
                            <x-ui.input type="number" min="1" step="1" label="Max Tickets" name="max_tickets" x-model="maxTickets" x-on:blur="$dispatch('workshop-pricing-changed')" value="{{ old('max_tickets', $workshop->max_tickets ?? '') }}" info="{{ $maxTicketsInfo }}" error="{{ $errors->first('max_tickets') }}" />
                        </span>
                        <span x-show="registration!=='tickets'" x-cloak>
                            <x-ui.input type="number" min="1" step="1" label="Maximum Attendance" name="max_attendance" x-model="maxAttendance" value="{{ old('max_attendance', $workshop->max_attendance ?? '') }}" info="Used to reserve stock and calculate pick-list quantities for workshops without ticket registration." error="{{ $errors->first('max_attendance') }}" />
                        </span>
                    </div>
                    <div class="flex-1">
                        <x-ui.input
                            label="Price information"
                            name="price_info"
                            :value="old('price_info', $workshopModel?->price_info ?? '')"
                            maxlength="255"
                            placeholder="Complete 8-week course • $16.50 per session"
                            info="Optional small text shown underneath the price on public workshop cards."
                            error="{{ $errors->first('price_info') }}"
                        />
                    </div>
                </div>

                <div class="mt-4">
                        <span x-show="registration==='link'">
                            <x-ui.input label="Registration URL" name="registration_url" id="registration_url" value="{{ old('registration_data', $workshopModel?->registration_data ?? '') }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <span x-show="registration==='email'">
                            <x-ui.input label="Registration Email" name="registration_email" id="registration_email" value="{{ old('registration_data', $workshopModel?->registration_data ?? '') }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <span x-show="registration==='message'">
                            <x-ui.input label="Registration Message" name="registration_message" id="registration_message" value="{{ old('registration_data', $workshopModel?->registration_data ?? '') }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <input type="hidden" name="registration_data" id="registration_data" value="{{ old('registration_data', $workshopModel?->registration_data ?? '') }}">
                </div>
                <div class="grid items-start gap-x-8 lg:grid-cols-2" x-show="registration === 'tickets'" x-cloak>
                <x-ui.collapsible-section
                    :open="$earlyBirdSectionOpen"
                    title="Early Bird"
                    variant="panel"
                    x-show="registration==='tickets'"
                >
                    <x-slot:summary>
                        <span x-text="earlyBirdSummaryText()">{{ $earlyBirdSectionSummary }}</span>
                    </x-slot:summary>
                    <div class="flex flex-col sm:flex-row sm:gap-8">
                        <div class="flex-1">
                            <x-ui.input type="number" step="0.01" min="0" label="Early Bird Price" name="early_bird_price" x-model="earlyBirdPrice" value="{{ $earlyBirdPriceValue }}" info="Optional. Leave blank for a special instead of a discount." error="{{ $errors->first('early_bird_price') }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input type="datetime-local" label="Early Bird Ends At" name="early_bird_ends_at" x-model="earlyBirdEndsAt" value="{{ $earlyBirdEndsAtValue }}" info="Use this, the ticket limit, or both to end the early bird offer." error="{{ $errors->first('early_bird_ends_at') }}" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col sm:flex-row sm:gap-8">
                        <div class="flex-1">
                            <x-ui.input type="number" min="1" step="1" label="Early Bird Ticket Limit" name="early_bird_ticket_limit" x-model="earlyBirdTicketLimit" value="{{ $earlyBirdTicketLimitValue }}" info="{{ $earlyBirdTicketLimitInfo }}" error="{{ $errors->first('early_bird_ticket_limit') }}" />
                        </div>
                        <div class="flex-1"></div>
                    </div>
                </x-ui.collapsible-section>
                    <x-finance.workshop-allocation-preview :budget="$ticketBudget" :workshop="$workshop ?? null" />
                </div>
                </div>
                <div x-show="registration==='tickets'" x-cloak class="mb-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-semibold text-gray-900">Participant information</div>
                            <div class="text-sm text-gray-600">Add instructions, permission forms, or other documents to checkout and the confirmation email.</div>
                            <div class="mt-1 text-xs text-gray-500" x-text="participantFiles.length ? `${participantFiles.length} attachment${participantFiles.length === 1 ? '' : 's'} selected` : 'No attachments selected'"></div>
                        </div>
                        <x-ui.button type="button" color="outline" x-on:click.prevent="participantInformationOpen = true">
                            <i class="fa-solid fa-paperclip mr-2"></i>Configure
                        </x-ui.button>
                    </div>
                </div>
                <div x-show="registration === 'tickets'" x-cloak>
                    <x-ui.collapsible-section title="Optional equipment" variant="panel" subtitle="Offer store products with a ticket">
                        <x-ui.select name="optional_product_ids[]" label="Products" multiple
                            :value="old('optional_product_ids', $workshop->optional_product_ids ?? [])"
                            :options="\App\Models\Product::query()->active()->orderBy('title')->pluck('title', 'id')->all()" />
                        <p class="text-sm text-gray-600">Customers can choose equipment and use the store’s delivery options during ticket checkout.</p>
                    </x-ui.collapsible-section>
                </div>
                </div>
                <input type="hidden" name="participant_files" x-bind:value="JSON.stringify(participantFiles)">
                <div
                    x-cloak
                    x-show="participantInformationOpen"
                    x-on:keydown.escape.window="participantInformationOpen = false"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="participant-information-title">
                    <div class="absolute inset-0 bg-black/40" x-on:click="participantInformationOpen = false"></div>
                    <div class="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <h3 id="participant-information-title" class="text-lg font-semibold text-gray-900">Participant information</h3>
                                <p class="mt-1 text-sm text-gray-600">This content appears during checkout and in the booking confirmation email.</p>
                            </div>
                            <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-gray-800" x-on:click="participantInformationOpen = false" aria-label="Close participant information">
                                <i class="fa-solid fa-xmark"></i>
                            </x-ui.button>
                        </div>
                        <div class="mb-5 border-b border-gray-200 pb-5">
                            <x-ui.input
                                label="Access Group Granted on Checkout Completion"
                                name="ticket_group_slug"
                                :suggestions="$groupSuggestions ?? []"
                                :value="old('ticket_group_slug', $workshop->ticket_group_slug ?? '')"
                                info="Optional. Grants this group to the purchaser-linked account as soon as checkout completes."
                                x-model="ticketGroupRaw"
                                x-on:input="ticketGroupRaw = ticketGroupRaw.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/-+/g, '-').replace(/^[-_]+|[-_]+$/g, '')"
                            />
                        </div>
                        <x-ui.editor
                            label="Instructions or notes"
                            name="participant_information"
                            value="{!! old('participant_information', $workshopModel?->participant_information ?? '') !!}"
                        ></x-ui.editor>
                        <div class="mt-5">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Attachments</div>
                                    <div class="text-xs text-gray-500">PDF, Word, or other supporting files will be attached to the confirmation email.</div>
                                </div>
                                <x-ui.button type="button" color="outline" x-on:click.prevent="openParticipantFilePicker()">Select or Upload</x-ui.button>
                            </div>
                            <div x-show="participantFiles.length === 0" class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-center text-sm text-gray-500">No attachments selected.</div>
                            <div class="space-y-2" x-show="participantFiles.length > 0">
                                <template x-for="(fileName, index) in participantFiles" :key="fileName">
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="min-w-0 truncate text-sm font-medium text-gray-800" x-text="fileName"></div>
                                        <x-ui.button variant="plain" type="button" class="shrink-0 text-red-600 hover:text-red-800" x-on:click="removeParticipantFile(index)" aria-label="Remove attachment"><i class="fa-solid fa-trash"></i></x-ui.button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-ui.button type="button" x-on:click="participantInformationOpen = false">Done</x-ui.button>
                        </div>
                    </div>
                </div>
                <div data-workshop-step-panel="registration" x-show="editorStep === 'registration'" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
                @include('admin.workshop.partials.welcome-settings')
                </div>
                <div x-data="{
                    ...SM.workshopTaskCopy({ publicUrl: @js($workshopModel ? route('workshop.show', $workshopModel) : '') }),
                    previewTask: null,
                    tasks: @js($workshopTaskDrafts).map((task) => {
                        const reminderOffset = Number(task.reminder_offset_days || 0);
                        return {
                            ...task,
                            reminder_days: Math.abs(reminderOffset),
                            reminder_direction: reminderOffset < 0 ? 'before' : 'after',
                            reminder_time: task.reminder_time || '12:00',
                            expanded: false,
                            copy_status: '',
                        };
                    }),
                    blueprintTasksImporting: false,
                    blueprintTaskImportMessage: '',
                    blueprintTaskDraft(task) {
                        const reminderOffset = Number(task.reminder_offset_days || 0);
                        return {
                            id: null,
                            blueprint_task_id: Number(task.id) || null,
                            name: String(task.name || ''),
                            notes: String(task.notes || ''),
                            subtasks: (Array.isArray(task.subtasks) ? task.subtasks : []).map((subtask) => ({
                                title: String(subtask?.title || ''),
                                content: String(subtask?.content || ''),
                            })),
                            reminder_enabled: Boolean(task.reminder_enabled),
                            reminder_days: Math.abs(reminderOffset),
                            reminder_direction: reminderOffset < 0 ? 'before' : 'after',
                            reminder_offset_days: reminderOffset,
                            reminder_time: task.reminder_time || '12:00',
                            expanded: false,
                            copy_status: '',
                            sort_order: Number(task.sort_order || 0),
                        };
                    },
                    hasWorkshopTasks() {
                        return this.tasks.some((task) => String(task.name || '').trim() !== '');
                    },
                    async handleBlueprintSelection(detail) {
                        const blueprintId = String(detail?.blueprintId || '');
                        if (!blueprintId || detail?.mode === 'custom' || this.hasWorkshopTasks()) return;

                        this.blueprintTaskImportMessage = '';
                        this.blueprintTasksImporting = true;
                        try {
                            const data = await this.loadBlueprintData(blueprintId);
                            if (
                                String(this.pickListTemplateId || '') !== blueprintId
                                || this.pickListTemplateMode === 'custom'
                                || this.hasWorkshopTasks()
                            ) return;
                            this.tasks = (Array.isArray(data.tasks) ? data.tasks : []).map((task) => this.blueprintTaskDraft(task));
                        } catch (error) {
                            if (String(this.pickListTemplateId || '') === blueprintId) {
                                this.blueprintTaskImportMessage = 'Could not load tasks from this blueprint. Use the import button to retry.';
                            }
                        } finally {
                            this.blueprintTasksImporting = false;
                        }
                    },
                    async addMissingBlueprintTasks() {
                        const blueprintId = String(this.pickListTemplateId || '');
                        if (!blueprintId || this.blueprintTasksImporting) return;

                        this.blueprintTasksImporting = true;
                        this.blueprintTaskImportMessage = '';
                        try {
                            const data = await this.loadBlueprintData(blueprintId);
                            if (String(this.pickListTemplateId || '') !== blueprintId) return;

                            const existingIds = new Set(this.tasks.map((task) => Number(task.blueprint_task_id || 0)).filter((id) => id > 0));
                            const existingNames = new Set(this.tasks.map((task) => String(task.name || '').trim().toLocaleLowerCase().replace(/\s+/g, ' ')).filter(Boolean));
                            const missing = [];
                            for (const task of (Array.isArray(data.tasks) ? data.tasks : [])) {
                                const id = Number(task.id || 0);
                                const name = String(task.name || '').trim();
                                const normalizedName = name.toLocaleLowerCase().replace(/\s+/g, ' ');
                                if (!name || existingIds.has(id) || existingNames.has(normalizedName)) continue;
                                missing.push(this.blueprintTaskDraft(task));
                                if (id > 0) existingIds.add(id);
                                existingNames.add(normalizedName);
                            }

                            this.tasks.push(...missing);
                            this.blueprintTaskImportMessage = missing.length
                                ? `Added ${missing.length} missing task${missing.length === 1 ? '' : 's'} from the blueprint.`
                                : 'There are no missing tasks to add.';
                        } catch (error) {
                            this.blueprintTaskImportMessage = 'Could not load tasks from this blueprint. Try again.';
                        } finally {
                            this.blueprintTasksImporting = false;
                        }
                    },
                    addTask() { this.tasks.push({ id: null, blueprint_task_id: null, name: 'New task', notes: '', subtasks: [], reminder_enabled: false, reminder_days: 0, reminder_direction: 'before', reminder_offset_days: null, reminder_time: '12:00', expanded: true, copy_status: '', sort_order: (this.tasks.length + 1) * 10 }); },
                    removeTask(index) { this.tasks.splice(index, 1); },
                    addSubtask(task) { task.subtasks ||= []; task.subtasks.push({ title: `Detail ${task.subtasks.length + 1}`, content: '' }); },
                    removeSubtask(task, index) { task.subtasks.splice(index, 1); },
                    toggleTask(index) { this.tasks[index].expanded = !this.tasks[index].expanded; },
                    taskReminderSummary(task) {
                        if (!task.reminder_enabled) return 'No reminder set';
                        const days = Math.max(0, Number(task.reminder_days || 0));
                        const when = days === 0 ? 'on workshop day' : `${days} day${days === 1 ? '' : 's'} ${task.reminder_direction || 'before'}`;
                        const time = ({ '06:00': '6:00am', '12:00': '12:00pm', '16:00': '4:00pm' })[task.reminder_time] || 'time not set';
                        return `Reminder ${when} · ${time}`;
                    },
                    workshopAiContext(taskIndex = null, subtaskIndex = null) {
                        const form = document.getElementById('workshop-form');
                        const value = (name) => { const field = form?.elements?.namedItem(name); return (field && typeof field.value === 'string') ? field.value : ''; };
                        const descriptionEditor = document.querySelector('[data-editor-name=content] .tiptap');
                        const description = descriptionEditor
                            ? (descriptionEditor.innerText || descriptionEditor.textContent || '')
                            : value('content').replace(/<[^>]*>/g, ' ');
                        const task = taskIndex === null ? null : (this.tasks[taskIndex] || null);
                        const subtask = task && subtaskIndex !== null ? (task.subtasks || [])[subtaskIndex] || null : null;
                        const content = (subtask?.content || task?.notes || '').slice(0, 8000);
                        return {
                            source: 'workshop',
                            blueprint_id: value('pick_list_template_id'),
                            workshop: {
                                title: value('title'), summary: value('summary'), description: String(description).trim().slice(0, 4000),
                                public_url: @js($workshopModel ? route('workshop.show', $workshopModel) : ''),
                                type: value('type'), format: value('format'), ages: value('ages'), starts_at: value('starts_at'), ends_at: value('ends_at'),
                                location_id: value('location_id'), price: value('price'), status: value('status'), registration: value('registration'),
                                registration_data: value('registration_data'), max_tickets: value('max_tickets'), max_attendance: value('max_attendance'), hero_media_name: value('hero_media_name'),
                                publish_at: value('publish_at'), closes_at: value('closes_at'), is_private: value('is_private'), is_hidden: value('is_hidden'),
                                allow_pay_at_door: value('allow_pay_at_door'), early_bird_price: value('early_bird_price'),
                                early_bird_ends_at: value('early_bird_ends_at'), early_bird_ticket_limit: value('early_bird_ticket_limit'),
                                sold_ticket_count: @js((int) ($soldTicketCount ?? 0)),
                                sold_early_bird_ticket_count: @js((int) ($soldEarlyBirdTicketCount ?? 0)),
                            },
                            task_outline: this.tasks.filter((item) => String(item.name || '').trim()).slice(0, 40).map((item) => ({
                                name: item.name, notes: String(item.notes || '').replace(/<[^>]*>/g, ' ').slice(0, 250),
                                subtasks: (item.subtasks || []).map((child) => child.title).filter(Boolean).slice(0, 12),
                            })),
                            target: { task_index: taskIndex, subtask_index: subtaskIndex, task_name: task?.name || '', subtask_title: subtask?.title || '', current_content: content },
                        };
                    },
                    applyTaskAiContent(detail) {
                        if (detail?.context?.source !== 'workshop') return;
                        const target = detail.context.target || {};
                        const task = this.tasks[Number(target.task_index)];
                        if (!task) return;
                        if (target.subtask_index === null || target.subtask_index === undefined) task.notes = detail.html;
                        else if (task.subtasks?.[Number(target.subtask_index)]) task.subtasks[Number(target.subtask_index)].content = detail.html;
                    },
                    serializedTasks() {
                        return JSON.stringify(this.tasks.filter((task) => String(task.name || '').trim()).map((task, index) => ({
                            id: task.id || null, blueprint_task_id: task.blueprint_task_id || null, name: String(task.name || '').trim(), notes: task.notes || '',
                            subtasks: (task.subtasks || []).filter((child) => String(child.title || '').trim()).map((child) => ({ title: String(child.title).trim(), content: child.content || '' })),
                            reminder_enabled: task.reminder_enabled ? '1' : '0',
                            reminder_offset_days: task.reminder_direction === 'after' ? Math.abs(Number(task.reminder_days || 0)) : -Math.abs(Number(task.reminder_days || 0)),
                            reminder_time: task.reminder_time || null, sort_order: (index + 1) * 10,
                        })));
                    },
                }" x-on:workshop-task-ai-copy.window="applyTaskAiContent($event.detail)" x-on:workshop-blueprint-selected.window="handleBlueprintSelection($event.detail)">
                <div data-workshop-step-panel="delivery" x-show="editorStep === 'delivery'" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
                <div class="mb-5 flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <div x-id="['workshop-blueprint-option']" x-on:click.outside="blueprintPickerOpen = false" class="relative">
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <label class="pl-1 text-sm font-medium text-gray-700">Workshop Blueprint</label>
                                <div class="flex flex-wrap items-center gap-x-2 text-xs">
                                    <div class="flex items-center gap-x-2">
                                        <a href="{{ route('admin.workshop-blueprint.index') }}" class="text-primary-color hover:underline" target="_blank" rel="noopener noreferrer">Manage blueprints</a>
                                        <a x-show="pickListTemplateId" x-cloak class="border-l border-gray-300 pl-2 text-primary-color hover:underline" target="_blank" rel="noopener noreferrer" x-bind:href="blueprintEditUrl()">Open selected blueprint</a>
                                    </div>
                                    @isset($workshop)
                                        <a class="border-l border-gray-300 pl-2 text-primary-color hover:underline" target="_blank" rel="noopener noreferrer" href="{{ route('admin.workshop.run-sheet', $workshop) }}">Open run sheet</a>
                                    @endisset
                                </div>
                            </div>
                            <div class="relative flex items-center">
                                <x-ui.input-control
                                    type="text"
                                    readonly
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-haspopup="listbox"
                                    aria-label="Workshop Blueprint"
                                    aria-controls="workshop-blueprint-option-list"
                                    x-bind:aria-expanded="blueprintPickerOpen"
                                    x-bind:value="currentBlueprintSelectorLabel()"
                                    class="h-11 bg-white! pr-10! cursor-pointer"
                                    x-on:click="openBlueprintPicker()"
                                />
                                <x-ui.button type="button" variant="plain" class="absolute right-0 size-11 p-0! text-slate-500" aria-label="Choose a workshop blueprint" x-bind:aria-expanded="blueprintPickerOpen" x-on:click="toggleBlueprintPicker()">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                </x-ui.button>
                            </div>
                            <div x-show="blueprintPickerOpen" x-cloak class="absolute z-40 mt-1 w-full rounded-xl border border-gray-200 bg-white p-2 shadow-xl">
                                <x-ui.input-control
                                    type="search"
                                    x-ref="blueprintSearch"
                                    x-model="blueprintSearch"
                                    x-on:input="blueprintOptionIndex = 0"
                                    x-on:keydown.arrow-down.prevent="moveBlueprintOption(1)"
                                    x-on:keydown.arrow-up.prevent="moveBlueprintOption(-1)"
                                    x-on:keydown.enter.prevent="chooseBlueprintOption(filteredBlueprintOptions()[blueprintOptionIndex])"
                                    x-on:keydown.escape.prevent="blueprintPickerOpen = false"
                                    placeholder="Search workshop blueprints"
                                    aria-label="Search workshop blueprints"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-controls="workshop-blueprint-option-list"
                                    x-bind:aria-expanded="blueprintPickerOpen"
                                    x-bind:aria-activedescendant="filteredBlueprintOptions().length ? $id('workshop-blueprint-option') + '-' + blueprintOptionIndex : null"
                                    class="mb-2"
                                />
                                <div id="workshop-blueprint-option-list" role="listbox" class="max-h-72 overflow-y-auto">
                                    <template x-for="(option, optionIndex) in filteredBlueprintOptions()" :key="`${option.kind}-${option.id}`">
                                        <button
                                            type="button"
                                            role="option"
                                            class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm"
                                            x-bind:class="optionIndex === blueprintOptionIndex ? 'bg-sky-50 text-sky-800' : 'text-slate-700 hover:bg-slate-50'"
                                            x-bind:id="$id('workshop-blueprint-option') + '-' + optionIndex"
                                            x-bind:aria-selected="option.kind === 'custom' ? pickListTemplateMode === 'custom' : pickListTemplateMode !== 'custom' && String(option.id) === String(pickListTemplateId || '')"
                                            x-on:mouseenter="blueprintOptionIndex = optionIndex"
                                            x-on:click="chooseBlueprintOption(option)"
                                        >
                                            <span class="min-w-0 truncate" x-text="option.label"></span>
                                            <span x-show="option.kind === 'blueprint' && option.id" class="shrink-0 text-xs text-slate-500" x-text="`${option.tasks_count || 0} tasks · ${option.items_count || 0} items`"></span>
                                        </button>
                                    </template>
                                    <p x-show="filteredBlueprintOptions().length === 0" class="px-3 py-2 text-sm text-slate-500">No matching blueprints.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="workshop_tasks_payload" x-bind:value="serializedTasks()">
                @error('workshop_tasks_payload')<div class="mb-3 text-sm text-red-700" role="alert">{{ $message }}</div>@enderror
                <section class="mb-5 rounded-xl border border-gray-200 bg-white p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="font-semibold text-gray-900">Run sheet tasks</h2><p class="mt-1 text-sm text-gray-600">Existing tasks are kept when you change blueprints.</p><p x-show="blueprintTaskImportMessage" x-cloak class="mt-1 text-sm text-sky-800" role="status" x-text="blueprintTaskImportMessage"></p></div>
                        <div class="flex items-center gap-1">
                            <x-ui.button type="button" variant="plain" class="inline-flex size-9 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800 disabled:cursor-not-allowed disabled:opacity-50" x-show="pickListTemplateId" x-cloak x-bind:disabled="blueprintTasksImporting" x-on:click="addMissingBlueprintTasks()" aria-label="Add missing tasks from blueprint" title="Add missing tasks from blueprint"><i class="fa-solid" x-bind:class="blueprintTasksImporting ? 'fa-spinner fa-spin' : 'fa-arrow-down-to-bracket'" aria-hidden="true"></i></x-ui.button>
                            <x-ui.button type="button" color="primary-outline" size="compact" x-on:click="addTask()"><i class="fa-solid fa-plus mr-1"></i>Add task</x-ui.button>
                        </div>
                    </div>
                    <template x-if="tasks.length === 0"><p class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600">No tasks yet. Choose a blueprint or add a task for this workshop.</p></template>
                    <div class="space-y-4">
                        <template x-for="(task, taskIndex) in tasks" :key="task.id || `new-workshop-task-${taskIndex}`">
                            <article class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex items-start gap-2">
                                    <button type="button" class="flex min-w-0 flex-1 items-start gap-2 text-left" x-on:click="toggleTask(taskIndex)" x-bind:aria-expanded="task.expanded" x-bind:aria-label="`${task.expanded ? 'Collapse' : 'Expand'} ${task.name || 'task'}`">
                                        <i class="fa-solid mt-1 shrink-0 text-xs text-gray-500" x-bind:class="task.expanded ? 'fa-chevron-down' : 'fa-chevron-right'" aria-hidden="true"></i>
                                        <span class="min-w-0 flex-1">
                                            <span class="block break-words font-medium leading-snug text-gray-900" x-text="task.name || 'Untitled task'"></span>
                                            <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                                <span x-show="String(task.notes || '').trim() !== ''" class="inline-flex items-center gap-1"><i class="fa-regular fa-note-sticky" aria-hidden="true"></i>Notes</span>
                                                <span class="inline-flex items-center gap-1"><i class="fa-regular fa-bell" aria-hidden="true"></i><span x-text="taskReminderSummary(task)"></span></span>
                                            </span>
                                        </span>
                                    </button>
                                    <x-ui.button type="button" variant="plain" class="inline-flex size-9 shrink-0 items-center justify-center rounded text-red-600 hover:bg-red-50" x-on:click="removeTask(taskIndex)" aria-label="Remove task" title="Remove task"><i class="fa-solid fa-trash"></i></x-ui.button>
                                </div>
                                <div x-show="task.expanded" x-cloak class="mt-4 border-t border-gray-200 pt-4">
                                    <label class="mb-3 block"><span class="mb-1 block text-sm font-medium text-gray-700">Task</span><x-ui.input-control type="text" maxlength="255" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900" x-model="task.name" /></label>
                                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-gray-700">Notes</span>
                                        <div class="flex items-center gap-1">
                                            <x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-ai-toast" data-ai-processing-message="Writing task content…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-form" data-ai-kind="task_content" data-ai-result-event="workshop-task-ai-copy" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(workshopAiContext(taskIndex))" x-bind:data-ai-mode="String(task.notes || '').trim() ? 'improve' : 'write'" aria-label="Write or improve task notes" title="Write or improve task notes" :disabled="blank(config('services.openai.api_key'))"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button>
                                            <x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" x-show="taskPreviewAvailable(task)" x-cloak x-on:click="previewTask = task" data-open-dialog="workshop-task-copy-dialog" aria-haspopup="dialog" aria-controls="workshop-task-copy-dialog" aria-label="Preview and copy workshop post" title="Preview and copy workshop post"><i class="fa-solid fa-eye" aria-hidden="true"></i></x-ui.button>
                                        </div>
                                    </div>
                                    <div x-show="String(task.notes || '').trim() !== ''">
                                        <x-ui.mini-editor x-model="task.notes">
                                            <x-slot:toolbarActions><x-admin.workshop-placeholder-inserter /></x-slot:toolbarActions>
                                        </x-ui.mini-editor>
                                    </div>
                                    <div x-show="String(task.notes || '').trim() === ''" class="flex items-center justify-between gap-3 rounded-lg border border-dashed border-gray-300 bg-white px-3 py-3 text-sm text-gray-600">
                                        <span>No notes added.</span>
                                        <x-ui.button type="button" color="outline" x-on:click="task.notes = '<p></p>'">Add notes</x-ui.button>
                                    </div>
                                    <div class="mt-4 rounded-lg border border-gray-200 bg-white p-3">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-800">
                                            <input type="checkbox" class="rounded border-gray-300 text-primary-color focus:ring-primary-color" x-model="task.reminder_enabled">
                                            <span>Email a reminder to the workshop facilitator</span>
                                        </label>
                                        <div x-show="task.reminder_enabled" class="mt-3 grid gap-3 sm:grid-cols-3">
                                            <label class="block text-sm text-gray-700">Days
                                                <x-ui.input-control type="number" min="0" max="365" step="1" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900" x-model.number="task.reminder_days" />
                                            </label>
                                            <x-ui.select :name="null" label="When" class="mb-0" x-model="task.reminder_direction">
                                                <option value="before">Before workshop</option>
                                                <option value="after">After workshop</option>
                                            </x-ui.select>
                                            <x-ui.select :name="null" label="Time" class="mb-0" x-model="task.reminder_time">
                                                <option value="06:00">6:00am</option>
                                                <option value="12:00">12:00pm</option>
                                                <option value="16:00">4:00pm</option>
                                            </x-ui.select>
                                        </div>
                                    </div>
                                    <details x-show="(task.subtasks || []).length > 0" class="mt-3 rounded-lg border border-gray-200 bg-white p-3">
                                        <summary class="cursor-pointer text-sm font-medium text-gray-700">Social post sections and other details (<span x-text="task.subtasks?.length || 0"></span>)</summary>
                                        <div class="mt-3 space-y-4">
                                            <template x-for="(subtask, subtaskIndex) in task.subtasks" :key="`workshop-subtask-${task.id || taskIndex}-${subtaskIndex}`">
                                                <div class="border-t border-gray-200 pt-3">
                                                    <div class="flex items-end gap-2"><label class="min-w-0 flex-1"><span class="mb-1 block text-xs font-medium text-gray-600">Section title</span><x-ui.input-control type="text" maxlength="100" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" x-model="subtask.title" /></label><x-ui.button type="button" variant="plain" class="inline-flex size-8 shrink-0 items-center justify-center rounded text-red-600 hover:bg-red-50" x-on:click="removeSubtask(task, subtaskIndex)" aria-label="Remove section"><i class="fa-solid fa-trash"></i></x-ui.button></div>
                                                    <div class="mb-1 mt-3 flex items-center justify-between gap-2"><span class="text-sm font-medium text-gray-700">Section content</span><x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-ai-toast" data-ai-processing-message="Writing task content…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-form" data-ai-kind="task_content" data-ai-result-event="workshop-task-ai-copy" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(workshopAiContext(taskIndex, subtaskIndex))" x-bind:data-ai-mode="String(subtask.content || '').trim() ? 'improve' : 'write'" aria-label="Write or improve section content" title="Write or improve section content" :disabled="blank(config('services.openai.api_key'))"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button></div>
                                                    <x-ui.mini-editor x-model="subtask.content">
                                                        <x-slot:toolbarActions><x-admin.workshop-placeholder-inserter /></x-slot:toolbarActions>
                                                    </x-ui.mini-editor>
                                                </div>
                                            </template>
                                        </div>
                                    </details>
                                    <x-ui.button type="button" variant="plain" class="mt-2 text-sm text-primary-color hover:underline" x-on:click="addSubtask(task)"><i class="fa-solid fa-plus mr-1"></i>Add section</x-ui.button>
                                </div>
                            </article>
                        </template>
                    </div>
                    <x-ui.list-dialog id="workshop-task-copy-dialog" title="Workshop copy preview" kind="edit">
                        <div class="p-4 sm:p-5">
                            <p class="mb-3 text-sm text-slate-600">This preview uses the current workshop details. Saved placeholders stay up to date when you change them.</p>
                            <pre x-show="previewTask" class="max-h-[55dvh] overflow-y-auto whitespace-pre-wrap break-words rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-gray-800" x-text="resolvedWorkshopTaskCopy(previewTask)"></pre>
                            <p x-show="previewTask && !workshopPublicUrl && String(previewTask.notes || '').includes('{workshop-url}')" class="mt-3 text-xs text-slate-600">Save the workshop to add its public link to the preview.</p>
                        </div>
                        <div class="sm-dialog-footer">
                            <x-ui.button type="button" color="outline" data-close-dialog>Close</x-ui.button>
                            <x-ui.button type="button" x-bind:disabled="!previewTask" x-on:click="copyWorkshopTaskCopy(previewTask)"><i class="fa-regular fa-copy mr-2" aria-hidden="true"></i><span x-text="previewTask?.copy_status || 'Copy text'"></span></x-ui.button>
                        </div>
                    </x-ui.list-dialog>
                </section>
                </div>
                <div data-workshop-step-panel="public" x-show="editorStep === 'public'" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
                <div class="mb-4">
                    <x-ui.media label="Image" name="hero_media_name" value="{{ old('hero_media_name', $workshopModel?->hero_media_name ?? $selectedBlueprint?->hero_media_name ?? '') }}" allow_uploads="true" public_usable_only="true" />
                </div>
                <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Categories</h3>
                            <p class="text-xs text-gray-500">Optional public workshop filters. A workshop can have more than one category.</p>
                        </div>
                        <a href="{{ route('admin.workshop-category.index') }}" class="text-xs font-semibold text-primary-color hover:underline">Manage categories</a>
                    </div>

                    @if(($workshopCategories ?? collect())->isEmpty())
                        <p class="text-sm text-gray-500">No workshop categories have been created yet.</p>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($workshopCategories as $category)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 transition hover:border-primary-color hover:bg-primary-color-light/10">
                                    <x-ui.checkbox name="category_ids[]" value="{{ $category->id }}" :checked="in_array((string) $category->id, $selectedCategoryIds, true)" :noWrapper="true" />
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-600 shadow-sm ring-1 ring-gray-200">
                                        <i class="{{ $category->iconClass() }}"></i>
                                    </span>
                                    <span class="font-medium">{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <section class="mb-5 rounded-xl border border-gray-200 bg-white p-4">
                    <div class="mb-5">
                        <div class="mb-1 flex items-center gap-1 pl-1">
                            <label for="summary" class="text-sm">Summary</label>
                            <x-ui.button type="button" variant="plain" class="inline-flex size-7 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#workshop-ai-toast" data-ai-processing-message="Creating workshop summary…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-form" data-ai-kind="workshop_summary" data-ai-result-key="content" data-ai-fill-target="summary" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(workshopSummaryAiContext())" x-bind:data-ai-mode="String(document.querySelector('#workshop-form [name=summary]')?.value || '').trim() ? 'improve' : 'write'" aria-label="Develop summary from workshop description" title="Use the workshop description to draft or improve this summary" :disabled="blank(config('services.openai.api_key'))"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button>
                        </div>
                        <x-ui.input type="textarea" noLabel class="mb-0" id="summary" name="summary" value="{{ $workshopSummary }}" rows="3" />
                    </div>
                    <div class="mb-2 flex items-center justify-between gap-3"><h2 class="font-semibold text-gray-900">Description</h2></div>
                    <x-ui.editor
                        label=""
                        name="content"
                        value="{!! $workshopContent !!}">
                        <x-slot:toolbar>
                            <button type="button" data-admin-ai data-ai-widget-target="#workshop-ai-toast" data-ai-processing-message="Improving workshop description…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-form" data-ai-kind="workshop_description" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(workshopAiContext())" x-bind:data-ai-mode="String(document.querySelector('#workshop-form [name=content]')?.value || '').trim() ? 'improve' : 'write'" data-ai-editor-field="content" data-ai-editor-format="workshop-description" aria-label="Replace and improve workshop description" title="Replace and improve the current description" {{ blank(config('services.openai.api_key')) ? 'disabled' : '' }}>
                                <span class="relative inline-flex"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><i class="fa-solid fa-rotate absolute -right-2 -bottom-1 rounded-full bg-white p-px text-[9px]" aria-hidden="true"></i></span>
                            </button>
                            <button type="button" data-admin-ai data-ai-widget-target="#workshop-ai-toast" data-ai-processing-message="Checking workshop description…" data-ai-url="{{ route('admin.ai.workshops.copy') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#workshop-form" data-ai-kind="workshop_description_amend" data-ai-result-key="content" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(workshopAiContext())" x-bind:data-ai-mode="String(document.querySelector('#workshop-form [name=content]')?.value || '').trim() ? 'improve' : 'write'" data-ai-editor-field="content" data-ai-editor-format="workshop-description" aria-label="Amend description and add missing supported sections" title="Amend the description and add missing supported sections, including learning outcomes">
                                <span class="relative inline-flex"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><i class="fa-solid fa-plus absolute -right-2 -bottom-1 rounded-full bg-white p-px text-[9px]" aria-hidden="true"></i></span>
                            </button>
                        </x-slot:toolbar>
                    </x-ui.editor>
                </section>
                </div>
                </div>
                <section data-workshop-step-panel="review" x-show="editorStep === 'review'" x-cloak class="mb-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-gray-950">Review and publish</h2>
                        <p class="mt-1 text-sm text-gray-600">Check the workshop details, then save. You can return to any step before saving.</p>
                    </div>
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Workshop</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="workshopTitle || 'Untitled workshop'"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Type</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="workshopFormat === 'course' ? 'Course' : ({ physical: 'In-person workshop', online: 'Online workshop', stemcraft: 'STEMCraft workshop' }[type] || type)"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Starts</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="reviewDate(manualStartsAt)"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Ends</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="reviewDate(manualEndsAt)"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Location</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="reviewLocation()"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Registration</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="({ none: 'None', tickets: 'Tickets', interest: 'Interest', link: 'External link', email: 'External email', message: 'Custom message' }[registration] || registration)"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Capacity</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="registration === 'tickets' ? (maxTickets ? `${maxTickets} tickets` : 'No ticket limit set') : (maxAttendance ? `${maxAttendance} attendees` : 'No attendance limit set')"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Price</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="reviewValue('price') || 'No price set'"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Status</dt>
                            <dd class="mt-1 font-medium text-gray-900" x-text="({ draft: 'Draft', scheduled: 'Opens soon', open: 'Open', full: 'Full', closed: 'Closed', cancelled: 'Cancelled' }[status] || status)"></dd>
                        </div>
                    </dl>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-ui.select label="Status" name="status" x-model="status">
                                <option value="draft" {{ $workshopStatusForForm === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="scheduled" {{ $workshopStatusForForm === 'scheduled' ? 'selected' : '' }}>Opens Soon</option>
                                <option value="open" {{ $workshopStatusForForm === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="full" {{ $workshopStatusForForm === 'full' ? 'selected' : '' }}>Full</option>
                                <option value="closed" {{ $workshopStatusForForm === 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="cancelled" {{ $workshopStatusForForm === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.select>
                        </div>
                        <div>
                            <x-ui.input type="datetime-local" label="Publish Date" name="publish_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->publish_at ?? '') }}" onchange="updatedPublishAt()" />
                        </div>
                    </div>
                    @isset($workshop)
                        <div class="mt-4">
                            <x-ui.button color="outline" href="{{ route('workshop.show', $workshop) }}" target="_blank" rel="noopener noreferrer">
                                Preview saved public page <i class="fa-solid fa-arrow-up-right-from-square ml-2" aria-hidden="true"></i>
                            </x-ui.button>
                        </div>
                    @endisset
                </section>

                <div class="sm-workshop-step-footer {{ isset($workshop) ? 'sm-workshop-step-footer--existing' : 'sm-workshop-step-footer--new' }}">
                    @isset($workshop)
                        <div class="sm-workshop-step-footer__delete">
                            @if($workshop->registration === 'interest' || (int) ($workshop->interests_count ?? 0) > 0)
                                <x-ui.button class="sm-workshop-step-control sm-workshop-step-footer__interests" color="primary-outline" href="{{ route('admin.workshop.interests', $workshop) }}">View Interests</x-ui.button>
                            @endif
                            <x-ui.button data-editor-delete type="button" color="danger" class="sm-workshop-step-control sm-workshop-step-footer__delete-button" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')">Delete</x-ui.button>
                        </div>
                    @endisset
                    <div class="sm-workshop-step-footer__progress" x-bind:aria-label="`Step ${editorStepIndex() + 1} of ${editorStepOrder.length}`">
                        <span class="sm:hidden" x-text="`${editorStepIndex() + 1} / ${editorStepOrder.length}`"></span>
                        <span class="hidden sm:inline" x-text="`Step ${editorStepIndex() + 1} of ${editorStepOrder.length}`"></span>
                    </div>
                    <div class="sm-workshop-step-footer__navigation">
                        <x-ui.button class="sm-workshop-step-control sm-workshop-step-footer__previous" type="button" color="outline" x-show="editorStepIndex() > 0" x-cloak x-on:click="previousEditorStep()">
                            <span class="sm:hidden">Back</span>
                            <span class="hidden sm:inline">Previous</span>
                        </x-ui.button>
                        @isset($workshop)
                            <x-ui.button class="sm-workshop-step-control sm-workshop-step-footer__save" type="submit">Save</x-ui.button>
                        @else
                            <x-ui.button class="sm-workshop-step-control sm-workshop-step-footer__create" type="submit" x-show="editorStep === 'review'" x-cloak>Create</x-ui.button>
                        @endisset
                        <x-ui.button class="sm-workshop-step-control sm-workshop-step-footer__next" type="button" x-show="editorStepIndex() < editorStepOrder.length - 1" x-cloak x-on:click="nextEditorStep()">Next <i class="fa-solid fa-arrow-right ml-2" aria-hidden="true"></i></x-ui.button>
                    </div>
                </div>
        </form>
        @isset($workshop)
            <form id="send-workshop-welcome" method="POST" action="{{ route('admin.workshop.welcome.send', $workshop) }}">@csrf</form>
        @endisset

    </x-container>
</x-layout>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    function isStemcraftWorkshopType() {
        const typeElement = document.getElementsByName('type')[0];
        return typeElement && typeElement.value === 'stemcraft';
    }

    function syncWorkshopClosesAt(onlyIfEmpty = false) {
        const startsAtElement = document.getElementsByName('starts_at')[0];
        const endsAtElement = document.getElementsByName('ends_at')[0];
        const closesAtElement = document.getElementsByName('closes_at')[0];

        if (!startsAtElement || !endsAtElement || !closesAtElement) {
            return;
        }

        if (onlyIfEmpty && closesAtElement.value !== '') return;

        if (isStemcraftWorkshopType()) {
            closesAtElement.value = endsAtElement.value || '';
            return;
        }

        if (startsAtElement.value === '') {
            return;
        }

        let closesAt = new Date(startsAtElement.value);
        closesAt.setHours(closesAt.getHours() - 2);
        closesAtElement.value = SM.toLocalISOString(closesAt);
    }

    function updatedStartsAt() {
        const startsAt = document.getElementsByName('starts_at')[0].value;

        const elemEndsAt = document.getElementsByName('ends_at')[0];
        if (elemEndsAt.value === '') {
            let endsAt = new Date(startsAt);
            endsAt.setHours(endsAt.getHours() + 1);
            document.getElementsByName('ends_at')[0].value = SM.toLocalISOString(endsAt);
        }

        syncWorkshopClosesAt();
    }

    function updatedEndsAt() {
        syncWorkshopClosesAt();
    }

    function updatedPublishAt() {
        const publishAt = document.getElementsByName('publish_at')[0].value;
        const now = new Date();
        const statusElement = document.getElementsByName('status')[0];

        if (publishAt > now && statusElement) {
            statusElement.value = 'scheduled';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const elementIds = ['registration_url', 'registration_email', 'registration_message'];
        const registrationElem = document.getElementById('registration_data');

        if (registrationElem) {
            elementIds.forEach(id => {
                const elem = document.getElementById(id);
                if (elem) {
                    ['input', 'change'].forEach(eventName => elem.addEventListener(eventName, function(event) {
                        registrationElem.value = event.target.value;
                    }));
                }
            })
        }
    });

    /* Initalize */
    const elemPublishAt = document.getElementsByName('publish_at')[0];
    if (elemPublishAt && elemPublishAt.value === '') {
        let publishAt = new Date();
        document.getElementsByName('publish_at')[0].value = SM.toLocalISOString(publishAt);
    }

    syncWorkshopClosesAt(true);
</script>
