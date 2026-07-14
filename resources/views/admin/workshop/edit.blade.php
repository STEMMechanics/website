@php
$workshopModel = $workshop ?? null;
$workshopContent = isset($workshop) ? $workshop->content : '';
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

$pickListTemplateFieldValue = old('pick_list_template_id', $workshopModel?->pick_list_template_id ?? '');
$pickListTemplateMode = old('pick_list_template_id') !== null
    ? $pickListTemplateFieldValue
    : (($workshopModel?->pick_list_is_customized) ? 'custom' : $pickListTemplateFieldValue);
$hasCustomPickList = (bool) ($workshopModel?->pick_list_is_customized);
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
$selectedCategoryIds = collect(old('category_ids', $workshopModel?->categories?->pluck('id')->all() ?? []))
    ->map(fn ($id) => (string) $id)
    ->all();
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
@endphp
<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">{{ isset($workshop) ? 'Edit' : 'Create' }} Workshop</x-mast>

    <x-container class="mt-4">
        <form x-data="{
            type: @js(old('type', isset($workshopModel) && $workshopModel->location_id ? 'physical' : 'online')),
            status: @js($workshopStatusForForm),
            originalStatus: @js(isset($workshopModel) ? (string) $workshopModel->status : $workshopStatusForForm),
            isPrivate: @js((bool) old('is_private', isset($workshopModel) ? $workshopModel->isPrivate() : false)),
            isHidden: @js((bool) old('is_hidden', isset($workshopModel) ? (bool) $workshopModel->is_hidden : false)),
            registration: @js(old('registration', $workshopModel?->registration ?? 'none')),
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
            originalStartsAt: @js(isset($workshopModel) ? $workshopStartValue : ''),
            originalEndsAt: @js(isset($workshopModel) ? $workshopEndValue : ''),
            originalLocationId: @js(isset($workshopModel) ? trim((string) ($workshopModel->location_id ?? '')) : ''),
            ticketHolderNotificationCount: @js((int) ($ticketChangeNotificationRecipientCount ?? 0)),
            notifyTicketHolders: @js((bool) old('notify_ticket_holders', false)),
            ticketChangeEmailNotes: @js((string) old('ticket_change_email_notes', '')),
            workshopCancelReasonDefault: @js("We're sorry, but this workshop has been cancelled. Please see below for your refund or credit details."),
            workshopCancelReason: @js((string) old('workshop_cancel_reason', '')),
            hasCustomPickList: @js($hasCustomPickList),
            originalPickListTemplateId: @js((string) ($workshopModel?->pick_list_template_id ?? '')),
            pickListTemplateMode: @js((string) $pickListTemplateMode),
            pickListTemplateId: @js((string) $pickListTemplateFieldValue),
            pickListTemplateReset: false,
            updatePickListTemplateSelection(value) {
                const nextValue = String(value ?? '');
                const wasCustom = this.pickListTemplateMode === 'custom';

                if (nextValue === 'custom') {
                    this.pickListTemplateMode = 'custom';
                    if (String(this.pickListTemplateId || '') === '' && String(this.originalPickListTemplateId || '') !== '') {
                        this.pickListTemplateId = this.originalPickListTemplateId;
                    }
                    this.pickListTemplateReset = false;
                    return;
                }

                this.pickListTemplateMode = nextValue;
                this.pickListTemplateId = nextValue;
                this.pickListTemplateReset = wasCustom && this.hasCustomPickList;
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
            if (this.type !== 'physical') {
            this.selectedLocationId = '';
            return;
            }
            const current = (this.selectedLocationId ?? '').toString();
            if (current !== '' && this.locations.some((location) => String(location.id) === current)) {
                this.selectedLocationId = current;
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
            if (this.type !== 'physical') {
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
            || this.normalizedCurrentLocationId() !== this.originalLocationId;
            },
            submitForm() {
            const form = this.$refs.workshopForm;
            if (!(form instanceof HTMLFormElement)) {
            return;
            }

            form.submit();
            },
            async handleSubmit() {
            if (this.status === 'cancelled' && this.originalStatus !== 'cancelled' && ['tickets'].includes(String(this.registration || ''))) {
            this.openCancelWorkshopModal();
            return;
            }

            if (this.shouldConfirmEarlyBirdLimitIncrease() && !await this.confirmEarlyBirdLimitIncrease()) {
            return;
            }

            if (!this.hasRelevantTicketHolderChange()) {
            this.notifyTicketHolders = false;
            this.ticketChangeEmailNotes = '';
            this.submitForm();
            return;
            }

            if (typeof Swal === 'undefined' || !Swal || typeof Swal.fire !== 'function') {
            if (window.SM && typeof window.SM.confirm === 'function') {
                window.SM.confirm(
                    'Notify ticket holders?',
                    'Date, time, or location changed and active ticket holders exist. Save and email them about the change?',
                    'Save and Email',
                    (isConfirmed) => {
                        this.notifyTicketHolders = Boolean(isConfirmed);
                        this.submitForm();
                    }
                );
                return;
            }

            this.notifyTicketHolders = true;
            this.submitForm();
            return;
            }

            const recipientCount = Number.parseInt(String(this.ticketHolderNotificationCount || 0), 10);
            const recipientLabel = recipientCount === 1 ? 'ticket holder' : 'ticket holders';
            const result = await Swal.fire({
            position: 'top',
            icon: 'question',
            iconColor: '#2563eb',
            title: 'Notify ticket holders?',
            html: `This workshop has <strong>${recipientCount}</strong> active ${recipientLabel}. Date, time, or location changed. You can queue an update email now or save without sending anything.`,
            input: 'textarea',
            inputLabel: 'Additional notes',
            inputValue: this.ticketChangeEmailNotes || '',
            inputPlaceholder: 'Optional extra details to include in the email',
            inputAttributes: {
            'aria-label': 'Additional notes',
            },
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Save and Email',
            confirmButtonColor: '#2563eb',
            denyButtonText: 'Save Only',
            denyButtonColor: '#6b7280',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            focusConfirm: false,
            preConfirm: () => document.querySelector('.swal2-textarea')?.value || '',
            preDeny: () => document.querySelector('.swal2-textarea')?.value || '',
            });

            if (!result.isConfirmed && !result.isDenied) {
                return;
            }

            this.ticketChangeEmailNotes = String(result.value || '').trim();
            this.notifyTicketHolders = result.isConfirmed;
            this.submitForm();
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
                }" method="POST" action="{{ route('admin.workshop.' . (isset($workshop) ? 'update' : 'store'), $workshop ?? []) }}" x-init="initLocationSelection()" x-ref="workshopForm" x-on:submit.prevent="handleSubmit()">
                @isset($workshop)
                @method('PUT')
                @endisset
                @csrf
                <input type="hidden" name="notify_ticket_holders" :value="notifyTicketHolders ? '1' : '0'">
                <input type="hidden" name="ticket_change_email_notes" :value="ticketChangeEmailNotes">
                <input type="hidden" name="workshop_cancel_reason" :value="workshopCancelReason">
                <input type="hidden" name="pick_list_template_id" :value="pickListTemplateId || ''">
                <input type="hidden" name="reset_pick_list_customization" :value="pickListTemplateReset ? '1' : '0'">
                <div class="mb-4">
                    <x-ui.input label="Title" name="title" value="{!! isset($workshop) ? $workshop->title : '' !!}" />
                </div>
                <div class="mb-4">
                    <x-ui.media label="Image" name="hero_media_name" value="{{ $workshop->hero_media_name ?? '' }}" allow_uploads="true" />
                </div>
                <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
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
                                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 transition hover:border-primary-color hover:bg-primary-color-light/10">
                                    <x-ui.checkbox
                                        name="category_ids[]"
                                        value="{{ $category->id }}"
                                        :checked="in_array((string) $category->id, $selectedCategoryIds, true)"
                                        label="{{ $category->name }}"
                                        label-hidden
                                        no-wrapper
                                    />
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-600 shadow-sm ring-1 ring-gray-200">
                                        <i class="{{ $category->iconClass() }}"></i>
                                    </span>
                                    <span class="font-medium">{{ $category->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Type" name="type" x-model="type" x-on:change="if (type !== 'physical') { selectedLocationId = '' } else { initLocationSelection() }">
                            <option value="physical">Physical</option>
                            <option value="online">Online</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <span x-show="type==='physical'">
                            <x-ui.select label="Location" name="location_id" x-model="selectedLocationId" x-bind:disabled="type !== 'physical'">
                                <x-slot name="labelRight">
                                    <button type="button" class="text-primary-color cursor-pointer hover:underline" x-on:click.prevent="openCreateLocation()">Create new location</button>
                                </x-slot>
                                <option value="">Select location</option>
                                <template x-for="location in locations" :key="location.id">
                                    <option :value="String(location.id)" :selected="String(selectedLocationId ?? '') === String(location.id)" x-text="location.name"></option>
                                </template>
                            </x-ui.select>
                        </span>
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
                        <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="closeCreateLocation()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <x-ui.input label="Name" name="new_location_name" x-model="newLocation.name" />
                        <x-ui.input label="Address" name="new_location_address" x-model="newLocation.address" />
                        <x-ui.input label="Location URL" name="new_location_url" x-model="newLocation.url" />
                        <x-ui.input label="Address URL" name="new_location_address_url" x-model="newLocation.address_url" />
                    </div>

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
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.input
                            type="datetime-local"
                            label="Start Date"
                            name="starts_at"
                            value="{{ $workshopStartValue }}"
                            onchange="updatedStartsAt()"
                            x-ref="startsAt"
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
                            x-ref="endsAt"
                            x-on:input="manualEndsAt = $event.target.value"
                            x-on:change="manualEndsAt = $event.target.value"
                            x-bind:value="manualEndsAt"
                        />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Status" name="status" x-model="status">
                            <option value="draft" {{ $workshopStatusForForm === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="scheduled" {{ $workshopStatusForForm === 'scheduled' ? 'selected' : '' }}>Opens Soon</option>
                            <option value="open" {{ $workshopStatusForForm === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="full" {{ $workshopStatusForForm === 'full' ? 'selected' : '' }}>Full</option>
                            <option value="closed" {{ $workshopStatusForForm === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="cancelled" {{ $workshopStatusForForm === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Publish Date" name="publish_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->publish_at ?? '') }}" onchange="updatedPublishAt()" />
                    </div>
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
                                </p>
                            </div>
                            <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeCancelWorkshopModal()" aria-label="Close cancel workshop modal">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="mt-5">
                            <label class="mb-1 block text-sm font-semibold text-gray-900" for="workshop-cancel-reason">Cancellation reason</label>
                            <textarea
                                id="workshop-cancel-reason"
                                rows="4"
                                x-model="workshopCancelReason"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-rose-300 focus:outline-none focus:ring-0"
                            ></textarea>
                            <p class="mt-1 text-xs text-gray-600">This text replaces the opening line in cancellation emails and records.</p>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-ui.button type="button" color="primary-outline" x-on:click="closeCancelWorkshopModal()">Keep Workshop</x-ui.button>
                            <x-ui.button type="button" color="danger" x-on:click="confirmCancelWorkshop()">Cancel Workshop</x-ui.button>
                        </div>
                    </div>
                </div>

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
                    <div class="flex-1">
                        <x-ui.input label="Hosted For" name="hosted_for" value="{{ old('hosted_for', $workshop->hosted_for ?? '') }}" info="Shown publicly for private workshops instead of the location." error="{{ $errors->first('hosted_for') }}" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.input label="Price" name="price" info="Leave blank to hide from public. Also supports Free, TBD or TBC" value="{{ $workshop->price ?? '' }}" />
                    </div>
                    <div class="flex-1">
                        <x-ui.input label="Ages" name="ages" info="Leave blank to hide from public" value="{{ $workshop->ages ?? '8+' }}" />
                    </div>
                </div>
                <x-ui.collapsible-section
                    :open="$earlyBirdSectionOpen"
                    title="Early Bird"
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
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Registration" name="registration" x-model="registration" onchange="document.getElementsByName('registration_data').forEach((e)=>e.value='')">
                            <option value="none" {{ (old('registration', $workshop->registration ?? '')) === 'none' ? 'selected' : '' }}>None</option>
                            <option value="tickets" {{ (old('registration', $workshop->registration ?? '')) === 'tickets' ? 'selected' : '' }}>Tickets</option>
                            <option value="interest" {{ (old('registration', $workshop->registration ?? '')) === 'interest' ? 'selected' : '' }}>Interest</option>
                            <option value="link" {{ (old('registration', $workshop->registration ?? '')) === 'link' ? 'selected' : '' }}>External Link</option>
                            <option value="email" {{ (old('registration', $workshop->registration ?? '')) === 'email' ? 'selected' : '' }}>External Email</option>
                            <option value="message" {{ (old('registration', $workshop->registration ?? '')) === 'message' ? 'selected' : '' }}>Custom Message</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <span x-show="registration==='tickets'">
                            <x-ui.input type="number" min="1" step="1" label="Max Tickets" name="max_tickets" x-model="maxTickets" value="{{ old('max_tickets', $workshop->max_tickets ?? '') }}" info="{{ $maxTicketsInfo }}" error="{{ $errors->first('max_tickets') }}" />
                        </span>
                        <span x-show="registration==='link'">
                            <x-ui.input label="Registration URL" name="registration_url" id="registration_url" value="{!! isset($workshop) ? $workshop->registration_data : '' !!}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <span x-show="registration==='email'">
                            <x-ui.input label="Registration Email" name="registration_email" id="registration_email" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <span x-show="registration==='message'">
                            <x-ui.input label="Registration Message" name="registration_message" id="registration_message" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <input type="hidden" name="registration_data" id="registration_data" value="{{ $workshop->registration_data ?? '' }}">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8" x-show="registration==='tickets'">
                    <div class="flex-1">
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
                    <div class="flex-1"></div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select
                            label="Pick List Template"
                            name="pick_list_template_mode"
                            x-model="pickListTemplateMode"
                            x-on:change="updatePickListTemplateSelection($event.target.value)"
                        >
                            <x-slot name="labelRight">
                                <a href="{{ route('admin.pick-list-template.index') }}" class="text-primary-color cursor-pointer hover:underline" target="_blank">Manage templates</a>
                            </x-slot>
                            @if($hasCustomPickList)
                                <option value="custom" @selected((string) $pickListTemplateMode === 'custom')>Custom</option>
                                <option value="" disabled>──────────</option>
                            @endif
                            <option value="" @selected((string) $pickListTemplateMode === '')>No template</option>
                            @foreach(($pickListTemplates ?? collect()) as $pickListTemplate)
                                <option value="{{ $pickListTemplate->id }}" @selected((string) $pickListTemplateMode !== 'custom' && (string) $pickListTemplateFieldValue === (string) $pickListTemplate->id)>{{ $pickListTemplate->name }}</option>
                            @endforeach
                        </x-ui.select>
                        @if(isset($workshop) && $workshop->pick_list_template_id)
                            <div class="mt-1 text-xs">
                                <a class="text-primary-color hover:underline" target="_blank" href="{{ route('admin.pick-list-template.edit', $workshop->pick_list_template_id) }}">Open selected template</a>
                            </div>
                        @endif
                        @if(isset($workshop) && $workshop->pick_list_template_id && ! $workshop->pick_list_is_customized)
                            <div class="mt-2 text-xs text-gray-500">
                                Active pick list follows the selected template until it is customised.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mb-4">
                    <x-ui.editor
                        label="Content"
                        name="content"
                        value="{!! $workshopContent !!}"></x-ui.editor>
                </div>
                <div class="mb-4">
                    <x-ui.filelist
                        label="Files"
                        name="files"
                        editor="true"
                        value="{!! isset($workshop) ? $workshop->files()->orderBy('name')->get() : '' !!}"></x-ui.filelist>
                </div>
                <div class="mb-4">
                    <x-ui.filelist
                        label="Private Admin Files"
                        info="Visible to admins only from workshop admin screens. Not shown on the public workshop page."
                        name="private_files"
                        editor="true"
                        value="{!! isset($workshop) ? $workshop->files('private')->orderBy('name')->get() : '' !!}"></x-ui.filelist>
                </div>
                <div class="flex justify-end gap-4 mt-8">
                    @if(isset($workshop) && ($workshop->registration === 'interest' || (int) ($workshop->interests_count ?? 0) > 0))
                    <x-ui.button color="primary-outline" href="{{ route('admin.workshop.interests', $workshop) }}">View Interests</x-ui.button>
                    @endif
                    @isset($workshop)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')">Delete</x-ui.button>
                    @endisset
                    <x-ui.button type="submit">{{ isset($workshop) ? 'Save' : 'Create' }}</x-ui.button>
                </div>
        </form>
    </x-container>
</x-layout>

<script>
    function updatedStartsAt() {
        const startsAt = document.getElementsByName('starts_at')[0].value;
        console.log(startsAt);

        const elemEndsAt = document.getElementsByName('ends_at')[0];
        if (elemEndsAt.value === '') {
            let endsAt = new Date(startsAt);
            endsAt.setHours(endsAt.getHours() + 1);
            document.getElementsByName('ends_at')[0].value = SM.toLocalISOString(endsAt);
        }

        let closesAt = new Date(startsAt);
        closesAt.setHours(closesAt.getHours() - 2);
        document.getElementsByName('closes_at')[0].value = SM.toLocalISOString(closesAt);
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
                    elem.addEventListener('change', function(event) {
                        registrationElem.value = event.target.value;
                    });
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
</script>
