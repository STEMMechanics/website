@php
    $isTicketedWorkshop = $workshop->registration === 'tickets';
    $showCancelledTickets = (bool) ($showCancelledTickets ?? false);
    $cancelledTickets = $cancelledTickets ?? collect();
    $ticketSearch = trim((string) request()->query('search', ''));
    $attendanceTickets = collect($activeTickets ?? []);
    if ($showCancelledTickets) {
        $attendanceTickets = $attendanceTickets->merge($cancelledTickets);
    }
    $attendanceTickets = $attendanceTickets
        ->sortBy(function ($ticket): string {
            $first = strtolower(trim((string) ($ticket->firstname ?? '')));
            $last = strtolower(trim((string) ($ticket->surname ?? '')));
            $id = str_pad((string) ((int) ($ticket->id ?? 0)), 10, '0', STR_PAD_LEFT);

            return $first.'|'.$last.'|'.$id;
        })
        ->values();
    $seedEntries = old('entries');
    $ticketPaymentRows = is_array($ticketPaymentRows ?? null) ? $ticketPaymentRows : [];
    $availableEftposPayments = is_array($availableEftposPayments ?? null) ? $availableEftposPayments : [];
    $paymentMethodLabels = is_array($paymentMethodLabels ?? null) ? $paymentMethodLabels : [];
    $paymentMethodOptions = collect($paymentMethodLabels)
        ->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => (string) $label])
        ->values()
        ->all();
    $defaultPaymentMethod = array_key_first($paymentMethodLabels) ?: \App\Models\Payment::PAYMENT_METHOD_EFTPOS;
    $defaultReceivedAt = now()->format('Y-m-d\TH:i');
    $rawOldPaymentLines = old('payments');
    if (! is_array($rawOldPaymentLines)) {
        $rawOldPaymentLines = [];
    }
    $oldPaymentLines = collect($rawOldPaymentLines)
        ->filter(fn ($row) => is_array($row))
        ->map(function (array $row) use ($defaultPaymentMethod, $defaultReceivedAt): array {
            return [
                'method' => trim((string) ($row['method'] ?? $defaultPaymentMethod)) ?: $defaultPaymentMethod,
                'amount' => trim((string) ($row['amount'] ?? '')),
                'received_on' => trim((string) ($row['received_on'] ?? $defaultReceivedAt)) ?: $defaultReceivedAt,
                'reference' => trim((string) ($row['reference'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        })
        ->values()
        ->all();
    $oldPaymentTicketIds = collect(old('ticket_ids', []))
        ->map(fn ($id): int => (int) $id)
        ->filter(fn (int $id): bool => $id > 0)
        ->values()
        ->all();
    $oldExistingPaymentIds = collect(old('existing_payment_ids', []))
        ->map(fn ($id): int => (int) $id)
        ->filter(fn (int $id): bool => $id > 0)
        ->values()
        ->all();
    $oldAttendedTicketIds = collect(old('attended_ticket_ids', []))
        ->map(fn ($id): int => (int) $id)
        ->filter(fn (int $id): bool => $id > 0)
        ->values()
        ->all();
    $hasPaymentErrors = collect($errors->keys())->contains(function (string $key): bool {
        return str_starts_with($key, 'ticket_ids')
            || str_starts_with($key, 'attended_ticket_ids')
            || str_starts_with($key, 'existing_payment_ids')
            || str_starts_with($key, 'payments')
            || $key === 'mark_attended';
    });
    $ticketAttendanceState = collect($activeTickets ?? [])
        ->mapWithKeys(fn ($ticket): array => [(string) $ticket->id => (bool) $ticket->attended_at])
        ->all();
    $ticketInvoiceGroups = collect($attendanceTickets ?? [])
        ->groupBy(function ($ticket): string {
            $invoiceId = (int) ($ticket->invoice_id ?? 0);

            return $invoiceId > 0 ? 'invoice-'.$invoiceId : 'ticket-'.$ticket->id;
        })
        ->values();

    if (! is_array($seedEntries)) {
        $seedEntries = $dropIns->map(function ($entry) {
            $childName = trim((string) ($entry->child_name ?? ''));
            if ($childName === '') {
                $childName = trim((string) (($entry->firstname ?? '').' '.($entry->surname ?? '')));
            }

            return [
                'id' => (int) $entry->id,
                'child_name' => $childName,
                'guardian_name' => (string) ($entry->guardian_name ?? ''),
                'email' => (string) ($entry->email ?? ''),
                'phone' => (string) ($entry->phone ?? ''),
                'media_consent' => (bool) ($entry->media_consent ?? false),
            ];
        })->values()->all();
    }
@endphp

<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Attendance</x-mast>

    <x-container>
        <x-ui.toolbar class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <x-slot:left>
                <div class="flex flex-col">
                    <div class="text-lg font-semibold mb-2">{{ $workshop->title }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Starts:</span> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Location:</span> {{ $workshop->getLocationName() }}</div>
                </div>
            </x-slot:left>
            <x-slot:right>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button color="outline" href="{{ route('admin.workshop.attendance.csv', $workshop) }}">Export CSV</x-ui.button>
                    @if($isTicketedWorkshop)
                        <x-ui.button color="outline" href="{{ route('admin.workshop.tickets', $workshop) }}">View Tickets</x-ui.button>
                    @else
                        <x-ui.button href="{{ route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]) }}">Kiosk Sign-In Mode</x-ui.button>
                    @endif
                </div>
            </x-slot:right>
        </x-ui.toolbar>

        <div class="mb-6">
            <x-ui.filelist
                label="Private Admin Files"
                value="{!! $workshop->files('private')->orderBy('name')->get() !!}" />
        </div>

        @if($isTicketedWorkshop)
            <div
                class="rounded-lg border border-gray-200 p-4 mb-6"
                x-data="{
                    tickets: @js($ticketPaymentRows),
                    ticketAttendanceSaveUrl: @js(route('admin.workshop.attendance.tickets', $workshop)),
                    csrfToken: @js(csrf_token()),
                    ticketAttendance: @js($ticketAttendanceState),
                    ticketAttendanceSaving: false,
                    ticketAttendanceError: '',
                    ticketAttendanceSaveTimer: null,
                    ticketAttendanceSaveQueued: false,
                    lastAttendanceSavedAtDisplay: null,
                    paymentMethodOptions: @js($paymentMethodOptions),
                    availableEftposPayments: @js($availableEftposPayments),
                    eftposPaymentsRefreshUrl: @js(route('admin.workshop.attendance.eftpos-payments', $workshop)),
                    eftposPaymentsLoading: false,
                    eftposPaymentsError: '',
                    eftposPaymentsLastRefreshedAt: null,
                    compPaymentMethod: 'comp',
                    selectedPaymentTicketIds: @js($oldPaymentTicketIds),
                    selectedExistingPaymentIds: @js($oldExistingPaymentIds),
                    paymentAttendanceByTicketId: {},
                    paymentLines: [],
                    emailReceiptChecked: false,
                    paymentModalOpen: {{ $hasPaymentErrors ? 'true' : 'false' }},
                    nextPaymentLineId: 1,
                    cancelTicketsUrl: @js(route('admin.ticket.cancel.bulk')),
                    cancelModalOpen: false,
                    cancelModalTicketIds: [],
                    cancelModalSelection: {},
                    cancelEmailCustomer: true,
                    cancelModalSubmitting: false,
                    cancelModalError: '',
                    normalizeTicketId(value) {
                        const parsed = Number.parseInt(value, 10);
                        if (!Number.isFinite(parsed) || parsed <= 0) {
                            return null;
                        }

                        return parsed;
                    },
                    toNumber(value) {
                        const parsed = Number.parseFloat(String(value ?? '').trim().replace(/,/g, '.'));
                        if (!Number.isFinite(parsed) || parsed < 0) {
                            return 0;
                        }

                        return parsed;
                    },
                    normalizeMoney(value) {
                        const raw = String(value ?? '').trim().replace(/,/g, '.');
                        if (raw === '') {
                            return '';
                        }

                        const parsed = Number.parseFloat(raw);
                        if (!Number.isFinite(parsed) || parsed < 0) {
                            return '';
                        }

                        return parsed.toFixed(2);
                    },
                    normalizePaymentLineAmount(index) {
                        if (!Array.isArray(this.paymentLines) || !this.paymentLines[index]) {
                            return;
                        }

                        this.paymentLines[index].amount = this.normalizeMoney(this.paymentLines[index].amount);
                    },
                    waivedPaymentLineAmount() {
                        return Math.max(0, this.selectedOutstandingTotal() - this.selectedExistingPaymentsTotal());
                    },
                    applyWaivedPaymentLineAmount(index) {
                        if (!Array.isArray(this.paymentLines) || !this.paymentLines[index]) {
                            return;
                        }

                        this.paymentLines[index].amount = this.waivedPaymentLineAmount().toFixed(2);
                    },
                    buildEftposPaymentsRefreshUrl() {
                        const url = new URL(this.eftposPaymentsRefreshUrl, window.location.origin);
                        this.selectedPaymentTicketIds.forEach((ticketId) => {
                            const normalizedTicketId = this.normalizeTicketId(ticketId);
                            if (normalizedTicketId !== null) {
                                url.searchParams.append('ticket_ids[]', String(normalizedTicketId));
                            }
                        });

                        return url;
                    },
                    async refreshAvailableEftposPayments() {
                        if (this.eftposPaymentsLoading) {
                            return;
                        }

                        this.eftposPaymentsLoading = true;
                        this.eftposPaymentsError = '';

                        try {
                            const response = await fetch(this.buildEftposPaymentsRefreshUrl().toString(), {
                                cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            const payload = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                throw new Error(String(payload?.message || 'Could not refresh EFTPOS transactions.'));
                            }

                            this.availableEftposPayments = Array.isArray(payload?.availableEftposPayments)
                                ? payload.availableEftposPayments
                                : [];
                            this.eftposPaymentsLastRefreshedAt = String(payload?.refreshedAtDisplay || '').trim() || null;
                        } catch (error) {
                            this.eftposPaymentsError = error?.message || 'Could not refresh EFTPOS transactions.';
                        } finally {
                            this.eftposPaymentsLoading = false;
                        }
                    },
                    normalizeUserId(value) {
                        const normalized = String(value ?? '').trim();
                        return normalized !== '' ? normalized : null;
                    },
                    ticketsForInvoice(invoiceId) {
                        const normalizedInvoiceId = this.normalizeTicketId(invoiceId);
                        if (normalizedInvoiceId === null) {
                            return [];
                        }

                        return this.tickets
                            .filter((ticket) => this.normalizeTicketId(ticket.invoice_id) === normalizedInvoiceId);
                    },
                    openCancelTicketModal(ticketId, invoiceId) {
                        const normalizedTicketId = this.normalizeTicketId(ticketId);
                        if (normalizedTicketId === null) {
                            return;
                        }

                        const candidates = (() => {
                            const invoiceTickets = this.ticketsForInvoice(invoiceId)
                                .map((ticket) => this.normalizeTicketId(ticket.id))
                                .filter((id) => id !== null);
                            if (invoiceTickets.length > 1) {
                                return Array.from(new Set(invoiceTickets));
                            }

                            return [normalizedTicketId];
                        })();

                        this.cancelModalTicketIds = candidates;
                        this.cancelModalSelection = {};
                        candidates.forEach((id) => {
                            this.cancelModalSelection[String(id)] = id === normalizedTicketId;
                        });
                        this.cancelEmailCustomer = true;
                        this.cancelModalError = '';
                        this.cancelModalOpen = true;
                    },
                    cancelModalTickets() {
                        const selectedSet = new Set(this.cancelModalTicketIds);

                        return this.tickets
                            .filter((ticket) => selectedSet.has(this.normalizeTicketId(ticket.id)));
                    },
                    selectedCancelTicketIds() {
                        return this.cancelModalTicketIds
                            .filter((ticketId) => Boolean(this.cancelModalSelection[String(ticketId)]));
                    },
                    closeCancelModal() {
                        if (this.cancelModalSubmitting) {
                            return;
                        }

                        this.cancelModalOpen = false;
                        this.cancelModalTicketIds = [];
                        this.cancelModalSelection = {};
                        this.cancelEmailCustomer = true;
                        this.cancelModalError = '';
                    },
                    async submitCancelTickets(ticketIds = null) {
                        const selectedIds = Array.isArray(ticketIds)
                            ? ticketIds.map((id) => this.normalizeTicketId(id)).filter((id) => id !== null)
                            : this.selectedCancelTicketIds().map((id) => this.normalizeTicketId(id)).filter((id) => id !== null);
                        if (selectedIds.length === 0) {
                            this.cancelModalError = 'Select at least one ticket to cancel.';
                            return;
                        }

                        if (this.cancelModalSubmitting) {
                            return;
                        }

                        this.cancelModalSubmitting = true;
                        this.cancelModalError = '';

                        const formData = new FormData();
                        formData.append('_token', this.csrfToken);
                        formData.append('process_square_refund', '1');
                        formData.append('email_customer', this.cancelEmailCustomer ? '1' : '0');
                        selectedIds.forEach((id) => {
                            formData.append('ticket_ids[]', String(id));
                        });

                        try {
                            const response = await fetch(this.cancelTicketsUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });
                            const payload = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                throw new Error(String(payload?.message || 'Could not cancel selected tickets.'));
                            }

                            this.cancelModalOpen = false;
                            window.location.reload();
                        } catch (error) {
                            this.cancelModalError = error?.message || 'Could not cancel selected tickets.';
                        } finally {
                            this.cancelModalSubmitting = false;
                        }
                    },
                    newPaymentLine(payload = {}) {
                        return {
                            uid: this.nextPaymentLineId++,
                            method: String(payload.method || @js($defaultPaymentMethod)),
                            amount: String(payload.amount || ''),
                            received_on: String(payload.received_on || @js($defaultReceivedAt)),
                            reference: String(payload.reference || ''),
                            notes: String(payload.notes || ''),
                        };
                    },
                    resetPaymentLines(lines = null) {
                        const source = Array.isArray(lines) ? lines : [];
                        if (source.length === 0) {
                            this.paymentLines = [this.newPaymentLine()];

                            return;
                        }

                        this.paymentLines = source.map((line) => this.newPaymentLine(line));
                        this.syncWaivedPaymentLineAmounts();
                    },
                    addPaymentLine() {
                        this.paymentLines.push(this.newPaymentLine());
                    },
                    removePaymentLine(index) {
                        this.paymentLines.splice(index, 1);
                        if (this.paymentLines.length === 0) {
                            this.paymentLines = [this.newPaymentLine()];
                        }
                    },
                    ticketAttendanceIds() {
                        return Object.entries(this.ticketAttendance)
                            .filter(([, attended]) => Boolean(attended))
                            .map(([id]) => this.normalizeTicketId(id))
                            .filter((id) => id !== null);
                    },
                    attendanceStateFromIds(attendedIds = []) {
                        const attendedSet = new Set(
                            (Array.isArray(attendedIds) ? attendedIds : [])
                                .map((id) => this.normalizeTicketId(id))
                                .filter((id) => id !== null)
                        );
                        const state = {};

                        this.tickets.forEach((ticket) => {
                            const ticketId = this.normalizeTicketId(ticket.id);
                            if (ticketId === null) {
                                return;
                            }

                            state[ticketId] = attendedSet.has(ticketId);
                        });

                        return state;
                    },
                    initTicketAttendance() {
                        this.ticketAttendance = this.attendanceStateFromIds(this.ticketAttendanceIds());
                    },
                    toggleTicketAttendance(ticketId, attended) {
                        const normalizedId = this.normalizeTicketId(ticketId);
                        if (normalizedId === null) {
                            return;
                        }

                        this.ticketAttendance[normalizedId] = Boolean(attended);
                        this.scheduleTicketAttendanceSave();
                    },
                    scheduleTicketAttendanceSave() {
                        this.ticketAttendanceError = '';
                        this.ticketAttendanceSaveQueued = true;

                        if (this.ticketAttendanceSaveTimer) {
                            window.clearTimeout(this.ticketAttendanceSaveTimer);
                        }

                        this.ticketAttendanceSaveTimer = window.setTimeout(() => {
                            this.saveTicketAttendance();
                        }, 400);
                    },
                    async saveTicketAttendance() {
                        if (this.ticketAttendanceSaveTimer) {
                            window.clearTimeout(this.ticketAttendanceSaveTimer);
                            this.ticketAttendanceSaveTimer = null;
                        }

                        if (this.ticketAttendanceSaving) {
                            return;
                        }

                        this.ticketAttendanceSaving = true;
                        this.ticketAttendanceSaveQueued = false;

                        const attendedIds = this.ticketAttendanceIds();
                        const formData = new FormData();
                        formData.append('_token', this.csrfToken);
                        attendedIds.forEach((ticketId) => {
                            formData.append('attended_ticket_ids[]', String(ticketId));
                        });

                        try {
                            const response = await fetch(this.ticketAttendanceSaveUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });
                            const payload = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                throw new Error(String(payload?.message || 'Could not save ticket attendance.'));
                            }

                            const savedIds = Array.isArray(payload?.attended_ticket_ids)
                                ? payload.attended_ticket_ids
                                : attendedIds;
                            this.ticketAttendance = this.attendanceStateFromIds(savedIds);
                            this.lastAttendanceSavedAtDisplay = String(payload?.saved_at_display || '').trim() || null;
                        } catch (error) {
                            this.ticketAttendanceError = error?.message || 'Could not save ticket attendance.';
                        } finally {
                            this.ticketAttendanceSaving = false;
                            if (this.ticketAttendanceSaveQueued) {
                                this.scheduleTicketAttendanceSave();
                            }
                        }
                    },
                    selectedPaymentTickets() {
                        const selectedSet = new Set(
                            this.selectedPaymentTicketIds
                                .map((id) => this.normalizeTicketId(id))
                                .filter((id) => id !== null)
                        );

                        return this.tickets.filter((ticket) => selectedSet.has(this.normalizeTicketId(ticket.id)));
                    },
                    selectedAttendedTicketIds() {
                        return this.selectedPaymentTicketIds
                            .map((id) => this.normalizeTicketId(id))
                            .filter((id) => id !== null)
                            .filter((id) => Boolean(this.paymentAttendanceByTicketId[String(id)]));
                    },
                    selectedAttendedTicketTotal() {
                        const attendedSet = new Set(this.selectedAttendedTicketIds());

                        return this.selectedPaymentTickets().reduce((sum, ticket) => {
                            const ticketId = this.normalizeTicketId(ticket.id);
                            if (ticketId === null || !attendedSet.has(ticketId)) {
                                return sum;
                            }

                            return sum + this.toNumber(ticket.ticket_price);
                        }, 0);
                    },
                    selectedInvoiceSummary() {
                        const grouped = {};
                        this.selectedPaymentTickets().forEach((ticket) => {
                            const invoiceId = this.normalizeTicketId(ticket.invoice_id);
                            if (invoiceId === null) {
                                return;
                            }
                            if (!grouped[invoiceId]) {
                                grouped[invoiceId] = {
                                    id: invoiceId,
                                    number: ticket.invoice_number || ('#' + invoiceId),
                                    outstanding: this.toNumber(ticket.invoice_outstanding),
                                };
                            }
                        });

                        return Object.values(grouped);
                    },
                    selectedPaymentInvoiceUserIds() {
                        return Array.from(new Set(
                            this.selectedPaymentTickets()
                                .map((ticket) => this.normalizeUserId(ticket.invoice_user_id))
                                .filter((userId) => userId !== null)
                        ));
                    },
                    selectedOutstandingTotal() {
                        return this.selectedInvoiceSummary().reduce((sum, invoice) => {
                            return sum + this.toNumber(invoice.outstanding);
                        }, 0);
                    },
                    visibleExistingEftposPayments() {
                        const selectedUserIds = this.selectedPaymentInvoiceUserIds();
                        if (selectedUserIds.length === 0) {
                            return this.availableEftposPayments;
                        }

                        const allowedUsers = new Set(selectedUserIds);
                        return this.availableEftposPayments.filter((payment) => {
                            const paymentUserId = this.normalizeUserId(payment.user_id);
                            return paymentUserId === null || allowedUsers.has(paymentUserId);
                        });
                    },
                    selectedExistingPayments() {
                        const selectedSet = new Set(
                            this.selectedExistingPaymentIds
                                .map((id) => this.normalizeTicketId(id))
                                .filter((id) => id !== null)
                        );

                        return this.availableEftposPayments.filter((payment) => {
                            return selectedSet.has(this.normalizeTicketId(payment.id));
                        });
                    },
                    selectedExistingPaymentsTotal() {
                        return this.selectedExistingPayments().reduce((sum, payment) => {
                            return sum + this.toNumber(payment.available_amount);
                        }, 0);
                    },
                    isExistingPaymentSelected(paymentId) {
                        const normalizedPaymentId = this.normalizeTicketId(paymentId);
                        if (normalizedPaymentId === null) {
                            return false;
                        }

                        return this.selectedExistingPaymentIds.some((id) => this.normalizeTicketId(id) === normalizedPaymentId);
                    },
                    toggleExistingPayment(paymentId, selected) {
                        const normalizedPaymentId = this.normalizeTicketId(paymentId);
                        if (normalizedPaymentId === null) {
                            return;
                        }

                        const nextIds = this.selectedExistingPaymentIds
                            .map((id) => this.normalizeTicketId(id))
                            .filter((id) => id !== null && id !== normalizedPaymentId);
                        if (selected) {
                            nextIds.push(normalizedPaymentId);
                        }

                        this.selectedExistingPaymentIds = Array.from(new Set(nextIds));
                        this.syncWaivedPaymentLineAmounts();
                    },
                    paymentLinesTotal() {
                        return this.paymentLines.reduce((sum, line) => {
                            return sum + this.toNumber(line.amount);
                        }, 0);
                    },
                    hasCompPaymentLine() {
                        return Array.isArray(this.paymentLines)
                            && this.paymentLines.some((line) => String(line.method || '') === this.compPaymentMethod);
                    },
                    syncWaivedPaymentLineAmounts() {
                        if (!Array.isArray(this.paymentLines)) {
                            return;
                        }

                        this.paymentLines.forEach((line) => {
                            if (String(line.method || '') === this.compPaymentMethod) {
                                line.amount = this.waivedPaymentLineAmount().toFixed(2);
                            }
                        });
                    },
                    paymentSourcesTotal() {
                        return this.selectedExistingPaymentsTotal() + this.paymentLinesTotal();
                    },
                    recommendedPaymentAmount() {
                        const attendedTotal = this.selectedAttendedTicketTotal();
                        const outstanding = this.selectedOutstandingTotal();

                        return Math.max(0, Math.min(attendedTotal, outstanding) - this.selectedExistingPaymentsTotal());
                    },
                    seedPaymentAttendance(attendedTicketIds = null) {
                        const selectedIds = this.selectedPaymentTicketIds
                            .map((id) => this.normalizeTicketId(id))
                            .filter((id) => id !== null);
                        const selectedSet = new Set(selectedIds);
                        let explicitAttendedSet = null;

                        if (Array.isArray(attendedTicketIds)) {
                            explicitAttendedSet = new Set(
                                attendedTicketIds
                                    .map((id) => this.normalizeTicketId(id))
                                    .filter((id) => id !== null)
                            );
                        }

                        const nextState = {};
                        selectedSet.forEach((ticketId) => {
                            if (explicitAttendedSet !== null) {
                                nextState[String(ticketId)] = explicitAttendedSet.has(ticketId);

                                return;
                            }

                            nextState[String(ticketId)] = true;
                        });

                        this.paymentAttendanceByTicketId = nextState;
                        this.syncWaivedPaymentLineAmounts();
                    },
                    togglePaymentAttendance(ticketId, attended) {
                        const normalizedId = this.normalizeTicketId(ticketId);
                        if (normalizedId === null) {
                            return;
                        }
                        this.paymentAttendanceByTicketId[String(normalizedId)] = Boolean(attended);
                        this.syncWaivedPaymentLineAmounts();
                    },
                    remainingBalance() {
                        return Math.max(0, this.selectedOutstandingTotal() - this.paymentSourcesTotal());
                    },
                    openPaymentModal(ticketIds) {
                        const requested = Array.isArray(ticketIds)
                            ? ticketIds.map((id) => this.normalizeTicketId(id)).filter((id) => id !== null)
                            : [];
                        const selectedSet = new Set(requested);
                        const payableIds = this.tickets
                            .filter((ticket) => selectedSet.has(this.normalizeTicketId(ticket.id)))
                            .filter((ticket) => this.toNumber(ticket.invoice_outstanding) > 0.0001)
                            .map((ticket) => this.normalizeTicketId(ticket.id))
                            .filter((id) => id !== null);

                        this.selectedPaymentTicketIds = Array.from(new Set(payableIds));
                        if (this.selectedPaymentTicketIds.length === 0) {
                            return;
                        }

                        this.selectedExistingPaymentIds = [];
                        this.resetPaymentLines();
                        this.seedPaymentAttendance();
                        this.emailReceiptChecked = false;

                        this.paymentModalOpen = true;
                        void this.refreshAvailableEftposPayments();
                    },
                    openPaymentModalForInvoice(invoiceId) {
                        const normalizedInvoiceId = this.normalizeTicketId(invoiceId);
                        if (normalizedInvoiceId === null) {
                            return;
                        }

                        const ticketIds = this.tickets
                            .filter((ticket) => this.normalizeTicketId(ticket.invoice_id) === normalizedInvoiceId)
                            .map((ticket) => this.normalizeTicketId(ticket.id))
                            .filter((id) => id !== null);
                        this.openPaymentModal(ticketIds);
                    },
                }"
                x-init="
                    initTicketAttendance();
                    if ({{ $hasPaymentErrors ? 'true' : 'false' }}) {
                        resetPaymentLines(@js($oldPaymentLines));
                        seedPaymentAttendance(@js($oldAttendedTicketIds));
                        emailReceiptChecked = {{ old('email_receipt') ? 'true' : 'false' }};
                    } else {
                        resetPaymentLines();
                    }
                    if (paymentModalOpen) {
                        void refreshAvailableEftposPayments();
                    }
                "
                x-on:keydown.escape.window="
                    if (paymentModalOpen) { paymentModalOpen = false; }
                    if (cancelModalOpen) { closeCancelModal(); }
                "
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold mb-3">Ticketed Attendance</h2>
                    <form method="GET" action="{{ route('admin.workshop.attendance', $workshop) }}" class="mb-4 w-full lg:max-w-2xl">
                        <x-ui.checkbox
                            name="show_cancelled"
                            value="1"
                            label="Show cancelled tickets"
                            :checked="$showCancelledTickets"
                            :noWrapper="true"
                            :inline="true"
                            onchange="this.form.submit()"
                        />
                        <div class="mt-2 flex relative">
                            <input
                                class="bg-white grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300"
                                autocomplete="off"
                                placeholder="Find Ticket or Person"
                                type="text"
                                name="search"
                                value="{{ $ticketSearch }}"
                            />
                            <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                        </div>
                    </form>
                </div>
                @if($attendanceTickets->isEmpty())
                    <p class="text-sm text-gray-600">No tickets found{{ $ticketSearch !== '' ? ' for this search.' : '.' }}</p>
                @else
                    @if($hasPaymentErrors)
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <div class="font-semibold">Could not save ticket payment</div>
                            <div class="mt-1">{{ $errors->first('ticket_ids') ?: $errors->first('attended_ticket_ids') ?: $errors->first('existing_payment_ids') ?: $errors->first('payments') ?: $errors->first('payments.*.amount') ?: $errors->first('payments.*.method') }}</div>
                        </div>
                    @endif
                    <div>
                        <div class="space-y-4 lg:hidden">
                            @foreach($attendanceTickets as $ticket)
                                @php
                                    $attendeeName = trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-';
                                    $invoiceMeta = (int) ($ticket->invoice_id ?? 0) > 0 ? ($attendanceInvoiceMeta[(int) $ticket->invoice_id] ?? null) : null;
                                    $invoiceOutstanding = (float) ($invoiceMeta['outstanding'] ?? 0);
                                    $isCancelledTicket = (int) $ticket->status === \App\Models\Ticket::STATUS_CANCELLED;
                                    $canCancelTicket = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                                    $canRecordPayment = $canCancelTicket && $invoiceMeta !== null && $invoiceOutstanding > 0.0001;
                                @endphp
                                <section class="rounded-2xl border border-gray-200 p-4 shadow-sm {{ $isCancelledTicket ? 'bg-red-50' : 'bg-white' }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 {{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">{{ $ticket->reference_code ?: $ticket->id }}</div>
                                            <div class="mt-1 text-sm text-gray-700 {{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">{{ $attendeeName }}</div>
                                        </div>
                                        @if($canCancelTicket)
                                            <x-ui.checkbox
                                                :id="'attended-ticket-mobile-'.$ticket->id"
                                                label="Attended"
                                                :small="true"
                                                :inline="true"
                                                :noWrapper="true"
                                                x-model="ticketAttendance[{{ (int) $ticket->id }}]"
                                                x-on:change="toggleTicketAttendance({{ (int) $ticket->id }}, $event.target.checked)" />
                                        @else
                                            <x-ui.badge color="danger" size="sm">Cancelled</x-ui.badge>
                                        @endif
                                    </div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contact</div>
                                            <div class="mt-1 text-sm text-gray-700 {{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">{{ $ticket->email ?: '-' }}</div>
                                            <div class="text-xs text-gray-500 {{ $isCancelledTicket ? 'line-through text-gray-500' : '' }}">{{ $ticket->phone ?: '-' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</div>
                                            <div class="mt-1 text-sm text-gray-700">{{ $ticket->customer_status_label }}</div>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invoice</div>
                                            @if($ticket->invoice)
                                                <a href="{{ route('admin.invoice.edit', $ticket->invoice) }}" class="mt-1 inline-flex text-sm text-primary-color hover:underline {{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">
                                                    {{ $invoiceMeta['number'] ?? ($ticket->invoice->invoice_number ?: '#'.$ticket->invoice->id) }}
                                                </a>
                                                @if($invoiceMeta)
                                                    <div class="text-xs text-gray-500">Outstanding ${{ number_format($invoiceOutstanding, 2) }}</div>
                                                @endif
                                            @else
                                                <div class="mt-1 text-sm text-gray-500">No linked invoice</div>
                                            @endif
                                        </div>
                                        <div class="sm:text-right">
                                            @if($canRecordPayment)
                                                <x-ui.button type="button" color="outline" class="w-full sm:w-auto" x-on:click="openPaymentModalForInvoice({{ (int) $ticket->invoice_id }})">Record Payment</x-ui.button>
                                            @elseif($invoiceMeta)
                                                <x-ui.badge color="success" size="sm">Paid</x-ui.badge>
                                            @endif
                                            @if($canCancelTicket)
                                                <x-ui.button
                                                    type="button"
                                                    color="danger-outline"
                                                    class="mt-2 w-full sm:w-auto"
                                                    x-on:click="openCancelTicketModal({{ (int) $ticket->id }}, {{ (int) ($ticket->invoice_id ?? 0) }})">
                                                    Cancel Ticket
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                        </div>

                        <div class="hidden lg:block">
                        <x-ui.table>
                            <x-slot:header>
                                <th>Attended</th>
                                <th>Ticket Ref</th>
                                <th>Attendee</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Invoice</th>
                                <th>Payment</th>
                                <th>Ticket</th>
                            </x-slot:header>
                            <x-slot:body>
                                @foreach($ticketInvoiceGroups as $ticketGroup)
                                    @php
                                        $groupTickets = $ticketGroup
                                            ->sortBy(function ($ticket): int {
                                                return (int) $ticket->status === \App\Models\Ticket::STATUS_CANCELLED ? 1 : 0;
                                            })
                                            ->values();
                                        $groupFirstTicket = $groupTickets->first();
                                        $groupInvoiceId = (int) ($groupFirstTicket->invoice_id ?? 0);
                                        $groupInvoiceMeta = $groupInvoiceId > 0 ? ($attendanceInvoiceMeta[$groupInvoiceId] ?? null) : null;
                                        $groupInvoiceOutstanding = (float) ($groupInvoiceMeta['outstanding'] ?? 0);
                                        $groupCanRecordPayment = $groupInvoiceMeta !== null && $groupInvoiceOutstanding > 0.0001;
                                        $groupRowspan = $groupTickets->count();
                                    @endphp
                                    @foreach($groupTickets as $ticket)
                                        @php
                                            $canCancelTicket = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                                            $isCancelledTicket = (int) $ticket->status === \App\Models\Ticket::STATUS_CANCELLED;
                                        @endphp
                                        <tr class="{{ $isCancelledTicket ? 'bg-red-50' : '' }}">
                                            <td>
                                                @if($canCancelTicket)
                                                    <x-ui.checkbox
                                                        :id="'attended-ticket-'.$ticket->id"
                                                        label="Attended"
                                                        :small="true"
                                                        :inline="true"
                                                        :noWrapper="true"
                                                        :labelHidden="true"
                                                        x-model="ticketAttendance[{{ (int) $ticket->id }}]"
                                                        x-on:change="toggleTicketAttendance({{ (int) $ticket->id }}, $event.target.checked)" />
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="{{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">{{ $ticket->reference_code ?: $ticket->id }}</td>
                                            <td class="{{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">{{ trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-' }}</td>
                                            <td>
                                                <div class="{{ $isCancelledTicket ? 'line-through text-gray-600' : '' }}">{{ $ticket->email ?: '-' }}</div>
                                                <div class="text-xs text-gray-500 {{ $isCancelledTicket ? 'line-through text-gray-500' : '' }}">{{ $ticket->phone ?: '-' }}</div>
                                            </td>
                                            <td>{{ $ticket->customer_status_label }}</td>
                                            @if($loop->first)
                                                <td rowspan="{{ $groupRowspan }}">
                                                    @if($groupFirstTicket->invoice)
                                                        <a href="{{ route('admin.invoice.edit', $groupFirstTicket->invoice) }}" class="text-primary-color hover:underline">{{ $groupInvoiceMeta['number'] ?? ($groupFirstTicket->invoice->invoice_number ?: '#'.$groupFirstTicket->invoice->id) }}</a>
                                                        @if($groupInvoiceMeta)
                                                            <div class="text-xs text-gray-500">Outstanding ${{ number_format($groupInvoiceOutstanding, 2) }}</div>
                                                        @endif
                                                        @if($groupRowspan > 1)
                                                            <div class="text-xs text-gray-500">{{ $groupRowspan }} tickets</div>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-500">No linked invoice</span>
                                                    @endif
                                                </td>
                                                <td rowspan="{{ $groupRowspan }}" class="text-center">
                                                    @if($groupCanRecordPayment)
                                                        <x-ui.button type="button" color="outline" class="px-3! py-1.5! text-xs" x-on:click="openPaymentModalForInvoice({{ $groupInvoiceId }})">Record</x-ui.button>
                                                    @elseif($groupInvoiceMeta)
                                                        <x-ui.badge color="success">Paid</x-ui.badge>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>
                                                @if($canCancelTicket)
                                                    <x-ui.button
                                                        type="button"
                                                        color="danger-outline"
                                                        class="px-2.5! py-1! text-xs"
                                                        x-on:click="openCancelTicketModal({{ (int) $ticket->id }}, {{ (int) ($ticket->invoice_id ?? 0) }})">
                                                        Cancel
                                                    </x-ui.button>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </x-slot:body>
                        </x-ui.table>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                            <div class="text-xs text-gray-500" x-show="ticketAttendanceSaving">Saving attendance...</div>
                            <div class="text-xs text-red-600" x-show="ticketAttendanceError" x-text="ticketAttendanceError"></div>
                            <div class="text-xs text-gray-500" x-show="lastAttendanceSavedAtDisplay && !ticketAttendanceSaving" x-text="'Saved ' + lastAttendanceSavedAtDisplay"></div>
                        </div>
                    </div>

                    <div
                        x-cloak
                        x-show="cancelModalOpen"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                        x-on:click.self="closeCancelModal()"
                    >
                        <div class="w-full max-w-2xl rounded-3xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Cancel Tickets</h3>
                                    <p class="mt-1 text-sm text-gray-600">Select which tickets on this invoice should be cancelled now. Refund processing will be attempted automatically.</p>
                                </div>
                                <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="closeCancelModal()">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </button>
                            </div>

                            <div class="mt-4 space-y-2">
                                <template x-for="ticket in cancelModalTickets()" :key="`cancel-ticket-${ticket.id}`">
                                    <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2">
                                        <span class="inline-flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                class="h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                                x-bind:checked="Boolean(cancelModalSelection[String(ticket.id)])"
                                                x-on:change="cancelModalSelection[String(ticket.id)] = $event.target.checked"
                                            >
                                            <span class="text-sm text-gray-700">
                                                <span class="font-semibold" x-text="ticket.reference"></span>
                                                <span class="ml-1" x-text="ticket.attendee"></span>
                                            </span>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-900" x-text="'$' + Number(ticket.ticket_price || 0).toFixed(2)"></span>
                                    </label>
                                </template>
                            </div>

                            <label class="mt-4 flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                <input
                                    type="checkbox"
                                    class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                    x-model="cancelEmailCustomer"
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">Email customer about this cancellation</span>
                                    <span class="mt-1 block text-xs text-gray-500">Enabled by default. This sends the cancellation notice and any related cancellation documents or refund receipts.</span>
                                </span>
                            </label>

                            <div class="mt-4 text-xs text-red-600" x-show="cancelModalError" x-text="cancelModalError"></div>

                            <div class="mt-6 flex justify-end gap-2">
                                <x-ui.button type="button" color="outline" x-on:click="closeCancelModal()">Close</x-ui.button>
                                <x-ui.button type="button" x-bind:disabled="cancelModalSubmitting" x-on:click="submitCancelTickets()">
                                    <span x-show="!cancelModalSubmitting">Cancel Selected</span>
                                    <span x-show="cancelModalSubmitting" class="inline-flex items-center gap-2">
                                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                                        <span>Cancelling...</span>
                                    </span>
                                </x-ui.button>
                            </div>
                        </div>
                    </div>

                    <div
                        x-cloak
                        x-show="paymentModalOpen"
                        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"
                        x-on:click.self="paymentModalOpen = false"
                    >
                        <div class="flex min-h-full items-start justify-center py-4 sm:items-center">
                        <div class="w-full max-w-5xl max-h-[calc(100vh-2rem)] overflow-y-auto rounded-3xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Record Ticket Payment</h3>
                            <p class="mt-1 text-sm text-gray-600">Create one or more payment entries and allocate them to the selected tickets' invoice balance.</p>
                        </div>
                        <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="paymentModalOpen = false">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    @if($hasPaymentErrors)
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <div class="font-semibold">Could not save ticket payment</div>
                            <div class="mt-1">{{ $errors->first('ticket_ids') ?: $errors->first('attended_ticket_ids') ?: $errors->first('existing_payment_ids') ?: $errors->first('payments') ?: $errors->first('payments.*.amount') ?: $errors->first('payments.*.method') }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.workshop.attendance.payments', $workshop) }}" class="mt-5">
                        @csrf

                                <template x-for="ticketId in selectedPaymentTicketIds" :key="`payment-ticket-${ticketId}`">
                                    <input type="hidden" name="ticket_ids[]" x-bind:value="ticketId">
                                </template>
                                <template x-for="paymentId in selectedExistingPaymentIds" :key="`existing-payment-${paymentId}`">
                                    <input type="hidden" name="existing_payment_ids[]" x-bind:value="paymentId">
                                </template>
                                <template x-for="ticketId in selectedAttendedTicketIds()" :key="`attended-ticket-${ticketId}`">
                                    <input type="hidden" name="attended_ticket_ids[]" x-bind:value="ticketId">
                                </template>
                                <input type="hidden" name="sync_attendance" value="1">

                                <div class="grid gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Selected Tickets</div>
                                        <div class="mt-1 text-sm text-gray-900" x-text="selectedPaymentTickets().length"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attended Ticket Total</div>
                                        <div class="mt-1 text-sm text-gray-900" x-text="'$' + selectedAttendedTicketTotal().toFixed(2)"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Outstanding</div>
                                        <div
                                            class="mt-1 text-sm text-gray-900"
                                            x-bind:class="remainingBalance() > 0.0001 ? 'font-bold text-red-600' : ''"
                                            x-text="'$' + remainingBalance().toFixed(2)"
                                        ></div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invoices in Selection</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <template x-for="invoice in selectedInvoiceSummary()" :key="`invoice-summary-${invoice.id}`">
                                            <x-ui.badge color="gray" size="xs">
                                                <span x-text="invoice.number"></span>
                                                <span class="ml-1 text-gray-500" x-text="'($' + Number(invoice.outstanding || 0).toFixed(2) + ')'"></span>
                                            </x-ui.badge>
                                        </template>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ticket Attendance and Price</div>
                                    <div class="mt-2 space-y-2">
                                        <template x-for="ticket in selectedPaymentTickets()" :key="`selected-ticket-${ticket.id}`">
                                            <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2">
                                                <span class="inline-flex items-center gap-3">
                                                    <input
                                                        type="checkbox"
                                                        class="h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                                        x-bind:checked="Boolean(paymentAttendanceByTicketId[String(ticket.id)])"
                                                        x-on:change="togglePaymentAttendance(ticket.id, $event.target.checked)"
                                                    >
                                                    <span class="text-sm text-gray-700">
                                                        <span class="font-semibold" x-text="ticket.reference"></span>
                                                        <span class="ml-1" x-text="ticket.attendee"></span>
                                                    </span>
                                                </span>
                                                <span class="text-sm font-semibold text-gray-900" x-text="'$' + Number(ticket.ticket_price || 0).toFixed(2)"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Available Unlinked EFTPOS Transactions</div>
                                        <div class="flex items-center gap-3">
                                            <div class="text-xs text-gray-500" x-text="selectedExistingPayments().length > 0 ? (selectedExistingPayments().length + ' selected') : 'Optional'"></div>
                                            <button
                                                type="button"
                                                class="text-xs font-semibold text-primary-color hover:underline disabled:cursor-not-allowed disabled:text-gray-400"
                                                x-bind:disabled="eftposPaymentsLoading"
                                                x-on:click="refreshAvailableEftposPayments()"
                                            >
                                                <span x-show="!eftposPaymentsLoading">Refresh</span>
                                                <span x-show="eftposPaymentsLoading" class="inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-circle-notch animate-spin"></i>
                                                    <span>Refreshing</span>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2 rounded-2xl border border-gray-200 bg-white">
                                        <template x-if="eftposPaymentsLoading">
                                            <div class="px-4 py-3 text-sm text-gray-500">Refreshing EFTPOS transactions...</div>
                                        </template>
                                        <template x-if="!eftposPaymentsLoading && visibleExistingEftposPayments().length === 0">
                                            <div class="px-4 py-3 text-sm text-gray-500">No unallocated EFTPOS transactions are available for this ticket selection.</div>
                                        </template>
                                        <div class="max-h-72 space-y-2 overflow-y-auto p-3" x-show="!eftposPaymentsLoading && visibleExistingEftposPayments().length > 0">
                                            <template x-for="payment in visibleExistingEftposPayments()" :key="`existing-eftpos-${payment.id}`">
                                                <label class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 px-3 py-3 transition hover:border-gray-300 hover:bg-gray-50">
                                                    <span class="inline-flex items-start gap-3">
                                                        <input
                                                            type="checkbox"
                                                            class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                                            x-bind:checked="isExistingPaymentSelected(payment.id)"
                                                            x-on:change="toggleExistingPayment(payment.id, $event.target.checked)"
                                                        >
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-semibold text-gray-900" x-text="'Payment #' + payment.id"></span>
                                                            <span class="mt-0.5 block text-xs text-gray-500" x-text="payment.received_on_display"></span>
                                                            <span class="mt-1 block text-xs text-gray-600" x-text="payment.customer_name || 'Unassigned EFTPOS transaction'"></span>
                                                            <span class="mt-1 block text-xs text-gray-500" x-show="payment.reference" x-text="'Ref: ' + payment.reference"></span>
                                                            <span class="mt-1 block truncate text-xs text-gray-500" x-show="payment.square_payment_id" x-text="'Square: ' + payment.square_payment_id"></span>
                                                        </span>
                                                    </span>
                                                    <span class="shrink-0 text-right">
                                                        <span class="block text-sm font-semibold text-gray-900" x-text="'$' + Number(payment.available_amount || 0).toFixed(2)"></span>
                                                        <span class="mt-2 inline-flex gap-3 text-xs">
                                                            <a class="text-primary-color hover:underline" x-bind:href="payment.payment_edit_url">Open</a>
                                                            <a
                                                                class="text-primary-color hover:underline"
                                                                x-show="payment.square_receipt_url"
                                                                x-bind:href="payment.square_receipt_url"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >Receipt</a>
                                                        </span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs text-red-600" x-show="eftposPaymentsError" x-text="eftposPaymentsError"></div>
                                    <div class="mt-1 text-xs text-gray-500" x-show="eftposPaymentsLastRefreshedAt" x-text="'Last refreshed ' + eftposPaymentsLastRefreshedAt"></div>
                                </div>

                                <div class="mt-5 space-y-3">
                                    <template x-for="(line, index) in paymentLines" :key="line.uid">
                                        <div class="rounded-2xl border border-gray-200 p-4">
                                            <div class="grid gap-3 lg:grid-cols-12">
                                                <div class="lg:col-span-2">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Method</label>
                                                    <x-ui.select
                                                        label="Method"
                                                        name=""
                                                        noLabel="true"
                                                        class="mb-0"
                                                        x-model="line.method"
                                                        x-bind:name="`payments[${index}][method]`"
                                                        x-on:change="if (line.method === compPaymentMethod) { applyWaivedPaymentLineAmount(index); }">
                                                        <template x-for="option in paymentMethodOptions" :key="option.value">
                                                            <option x-bind:value="option.value" x-text="option.label"></option>
                                                        </template>
                                                    </x-ui.select>
                                                </div>
                                                <div class="lg:col-span-2">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Amount</label>
                                                    <input type="text" inputmode="decimal" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" x-model="line.amount" x-bind:name="`payments[${index}][amount]`" x-bind:readonly="line.method === compPaymentMethod" x-on:blur="normalizePaymentLineAmount(index)">
                                                </div>
                                                <div class="lg:col-span-3">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Received</label>
                                                    <input type="datetime-local" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" x-model="line.received_on" x-bind:name="`payments[${index}][received_on]`">
                                                </div>
                                                <div class="lg:col-span-3">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Reference</label>
                                                    <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" x-model="line.reference" x-bind:name="`payments[${index}][reference]`">
                                                </div>
                                                <div class="lg:col-span-2">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</label>
                                                    <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0" x-model="line.notes" x-bind:name="`payments[${index}][notes]`">
                                                </div>
                                            </div>
                                            <div class="mt-3 flex justify-end">
                                                <button type="button" class="text-sm text-red-600 hover:text-red-700" x-on:click="removePaymentLine(index)">Remove line</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-4">
                                    <x-ui.button type="button" color="outline" x-on:click="addPaymentLine()">Add Split Payment Line</x-ui.button>
                                    <div class="mt-2 text-xs text-gray-500" x-show="!hasCompPaymentLine()" x-text="'Remaining to record now: $' + remainingBalance().toFixed(2)"></div>
                                </div>

                                <label class="mt-5 flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        name="email_receipt"
                                        value="1"
                                        class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                        x-model="emailReceiptChecked"
                                    >
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900">Email receipt to customer</span>
                                        <span class="mt-1 block text-xs text-gray-500">Optional. A receipt email will only be sent if this is checked and one clear recipient email can be resolved.</span>
                                    </span>
                                </label>

                                <div class="mt-6 flex justify-end gap-2">
                                    <x-ui.button type="button" color="outline" x-on:click="paymentModalOpen = false">Cancel</x-ui.button>
                                    <x-ui.button type="submit">Save Payment</x-ui.button>
                                </div>
                            </form>
                        </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="">
            <form method="POST" action="{{ route('admin.workshop.attendance.dropin.sync', $workshop) }}" x-data="{
                entries: @js($seedEntries),
                submitting: false,
                isDesktop: false,
                newBlankEntry() {
                    return {
                        id: 0,
                        child_name: '',
                        guardian_name: '',
                        email: '',
                        phone: '',
                        media_consent: false,
                    };
                },
                isBlankEntry(entry) {
                    return String(entry?.child_name || '').trim() === ''
                        && String(entry?.guardian_name || '').trim() === ''
                        && String(entry?.email || '').trim() === ''
                        && String(entry?.phone || '').trim() === ''
                        && !Boolean(entry?.media_consent);
                },
                hasSingleTrailingBlank() {
                    if (this.entries.length === 0) {
                        return false;
                    }
                    const blankCount = this.entries.filter((entry) => this.isBlankEntry(entry)).length;
                    return blankCount === 1 && this.isBlankEntry(this.entries[this.entries.length - 1]);
                },
                ensureSingleTrailingBlank() {
                    const nonBlank = this.entries.filter((entry) => !this.isBlankEntry(entry));
                    this.entries = [...nonBlank, this.newBlankEntry()];
                },
                handleRowChange(index) {
                    const isLast = index === (this.entries.length - 1);
                    if (isLast && !this.isBlankEntry(this.entries[index])) {
                        this.entries.push(this.newBlankEntry());
                        return;
                    }
                    if (!this.hasSingleTrailingBlank()) {
                        this.ensureSingleTrailingBlank();
                    }
                },
                removeEntry(index) {
                    this.entries.splice(index, 1);
                    this.ensureSingleTrailingBlank();
                },
                addEntry() {
                    if (!this.hasSingleTrailingBlank()) {
                        this.ensureSingleTrailingBlank();
                    }
                },
                syncViewport() {
                    this.isDesktop = window.innerWidth >= 1024;
                },
            }" x-init="ensureSingleTrailingBlank(); syncViewport(); window.addEventListener('resize', () => syncViewport())" x-on:submit="submitting = true">
                @csrf
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">{{ $isTicketedWorkshop ? 'Drop-In Attendance' : 'Attendance Records' }}</h2>
                </div>

                <div class="space-y-4 lg:hidden">
                    <template x-for="(entry, index) in entries" :key="`mobile-${index}`">
                        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-900" x-text="entry.child_name || entry.guardian_name || `Entry ${index + 1}`"></h3>
                                <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeEntry(index)" title="Delete row">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>

                            <input type="hidden" x-bind:name="!isDesktop ? `entries[${index}][id]` : null" x-model="entry.id">

                            <div class="mt-4 space-y-3">
                                <x-ui.input
                                    label="Child Name"
                                    name="mobile_child_name_placeholder"
                                    fieldClasses="mt-1"
                                    x-model="entry.child_name"
                                    x-bind:name="!isDesktop ? `entries[${index}][child_name]` : null"
                                    x-on:input="entry.child_name = $event.target.value; handleRowChange(index)"
                                    x-on:change="entry.child_name = $event.target.value; handleRowChange(index)" />
                                <x-ui.input
                                    label="Parent/Guardian"
                                    name="mobile_guardian_name_placeholder"
                                    fieldClasses="mt-1"
                                    x-model="entry.guardian_name"
                                    x-bind:name="!isDesktop ? `entries[${index}][guardian_name]` : null"
                                    x-on:input="entry.guardian_name = $event.target.value; handleRowChange(index)"
                                    x-on:change="entry.guardian_name = $event.target.value; handleRowChange(index)" />
                                <x-ui.input
                                    type="email"
                                    label="Email"
                                    name="mobile_email_placeholder"
                                    fieldClasses="mt-1"
                                    x-model="entry.email"
                                    x-bind:name="!isDesktop ? `entries[${index}][email]` : null"
                                    x-on:input="entry.email = $event.target.value; handleRowChange(index)"
                                    x-on:change="entry.email = $event.target.value; handleRowChange(index)" />
                                <x-ui.input
                                    label="Phone"
                                    name="mobile_phone_placeholder"
                                    fieldClasses="mt-1"
                                    x-model="entry.phone"
                                    x-bind:name="!isDesktop ? `entries[${index}][phone]` : null"
                                    x-on:input="entry.phone = $event.target.value; handleRowChange(index)"
                                    x-on:change="entry.phone = $event.target.value; handleRowChange(index)" />
                                <div>
                                    <div class="mt-8 mb-4 flex items-center gap-3">
                                        <input type="hidden" x-bind:name="!isDesktop ? `entries[${index}][media_consent]` : null" value="0">
                                        <input type="checkbox"
                                               class="h-6 w-6 rounded border-gray-400 text-primary-color focus:ring-primary-color"
                                               x-bind:name="!isDesktop ? `entries[${index}][media_consent]` : null"
                                               value="1"
                                               x-model="entry.media_consent"
                                               x-on:change="entry.media_consent = $event.target.checked; handleRowChange(index)">
                                        <span class="text-sm text-gray-600">Media Consent</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>

                <div class="hidden overflow-x-auto rounded-lg border border-gray-300 lg:block">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 rounded-md">
                            <tr>
                                <th class="text-sm text-left px-4 py-2 border-b border-gray-300">Child Name</th>
                                <th class="text-sm text-left px-4 py-2 border-b border-gray-300">Parent/Guardian</th>
                                <th class="text-sm text-left px-4 py-2 border-b border-gray-300">Email</th>
                                <th class="text-sm text-left px-4 py-2 border-b border-gray-300">Phone</th>
                                <th class="text-sm text-left px-4 py-2 border-b border-gray-300">Media</th>
                                <th class="text-sm text-left px-4 py-2 border-b border-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-b last:border-b-0">
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-bind:name="isDesktop ? `entries[${index}][id]` : null" x-model="entry.id">
                                        <x-ui.input
                                            name="child_name_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.child_name"
                                            x-bind:name="isDesktop ? `entries[${index}][child_name]` : null"
                                            x-on:input="entry.child_name = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.child_name = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            name="guardian_name_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.guardian_name"
                                            x-bind:name="isDesktop ? `entries[${index}][guardian_name]` : null"
                                            x-on:input="entry.guardian_name = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.guardian_name = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            type="email"
                                            name="email_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.email"
                                            x-bind:name="isDesktop ? `entries[${index}][email]` : null"
                                            x-on:input="entry.email = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.email = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            name="phone_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.phone"
                                            x-bind:name="isDesktop ? `entries[${index}][phone]` : null"
                                            x-on:input="entry.phone = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.phone = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-middle text-center">
                                        <input type="hidden" x-bind:name="isDesktop ? `entries[${index}][media_consent]` : null" value="0">
                                        <input type="checkbox"
                                               class="h-5 w-5 mt-1 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                               x-bind:name="isDesktop ? `entries[${index}][media_consent]` : null"
                                               value="1"
                                               x-model="entry.media_consent"
                                               x-on:change="entry.media_consent = $event.target.checked; handleRowChange(index)">
                                    </td>
                                    <td class="p-2 align-middle text-center">
                                        <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeEntry(index)" title="Delete row">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <x-ui.button type="submit" x-bind:disabled="submitting">
                        <span x-show="!submitting">Save</span>
                        <span x-show="submitting" class="inline-flex items-center gap-2">
                            <i class="fa-solid fa-circle-notch animate-spin"></i>
                            <span>Saving...</span>
                        </span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-container>
</x-layout>
