<x-layout>
    <x-mast>{{ $pageTitle ?? 'My Tickets' }}</x-mast>

    <x-container x-data="{
        cancelModalOpen: false,
        cancelFormAction: '',
        cancelTicketLabel: '',
        editModalOpen: false,
        editFormAction: '',
        editTicketLabel: '',
        editFirstname: '',
        editSurname: '',
        editEmail: '',
        editPhone: '',
        openCancelModal(action, label) {
            this.cancelFormAction = action;
            this.cancelTicketLabel = label;
            this.cancelModalOpen = true;
        },
        openEditModal(action, label, details) {
            this.editFormAction = action;
            this.editTicketLabel = label;
            this.editFirstname = details.firstname || '';
            this.editSurname = details.surname || '';
            this.editEmail = details.email || '';
            this.editPhone = details.phone || '';
            this.editModalOpen = true;
        },
        closeEditModal() {
            this.editModalOpen = false;
            this.editFormAction = '';
            this.editTicketLabel = '';
            this.editFirstname = '';
            this.editSurname = '';
            this.editEmail = '';
            this.editPhone = '';
        },
        closeCancelModal() {
            this.cancelModalOpen = false;
            this.cancelFormAction = '';
            this.cancelTicketLabel = '';
        },
        showCancelledRefunded: false
    }">
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.checkbox
                    label="Show cancelled/refunded"
                    :noWrapper="true"
                    :inline="true"
                    labelClass="text-sm pt-0"
                    x-model="showCancelledRefunded" />
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($tickets->isEmpty())
        <x-none-found item="tickets" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table>
            <x-slot:header>
                <th class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;">Ticket #</th>
                <th>Workshop Details</th>
                <th class="hidden md:table-cell">Status</th>
                <th class="hidden lg:table-cell">Purchased At</th>
                <th class="hidden lg:table-cell">Ticket Holder Details</th>
                <th>Actions</th>
            </x-slot:header>
            <x-slot:body>
                @foreach ($tickets as $ticket)
                @php
                $workshopTitle = (string) ($ticket->workshop?->title ?? '-');
                $workshopDate = $ticket->workshop?->starts_at?->format('M j, Y g:i a') ?? '-';
                $workshopLocation = (string) ($ticket->workshop?->getLocationName() ?? '-');
                $ticketHolderName = trim(($ticket->firstname ?? '').' '.($ticket->surname ?? '')) ?: '-';
                $ticketHolderEmail = trim((string) ($ticket->email ?? ''));
                $ticketHolderPhone = trim((string) ($ticket->phone ?? ''));
                $contactParts = array_values(array_filter([$ticketHolderEmail, $ticketHolderPhone], fn ($value) => $value !== ''));
                $ticketHolderContact = count($contactParts) > 0 ? implode(' - ', $contactParts) : '-';
                $isCancellableStatus = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                $canEditHolderDetails = (bool) (auth()->user()?->isAdmin() ?? false)
                || ((string) ($ticket->user_id ?? '') !== '' && (string) ($ticket->user_id ?? '') === (string) (auth()->id() ?? ''));
                $startsAt = $ticket->workshop?->starts_at;
                $canCancel = $isCancellableStatus && $startsAt && $startsAt->gt(now()->addHours(2));
                $canOpenTicketPdf = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                $isCancelledOrReissued = in_array((int) $ticket->status, [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED], true);
                @endphp
                <tr
                    x-show="showCancelledRefunded || !{{ $isCancelledOrReissued ? 'true' : 'false' }}"
                    style=" {{ $isCancelledOrReissued ? 'background-color: rgb(254 226 226);' : '' }}">
                    <td>
                        <div>{{ $ticket->reference_code ?: $ticket->id }}</div>
                        <div class="md:hidden text-xs text-gray-600 whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;">{{ $ticket->customer_status_label }}</div>
                    </td>
                    <td>
                        <div>{{ $workshopTitle }}</div>
                        <div class="text-xs text-gray-600">{{ $workshopDate }} - {{ $workshopLocation }}</div>
                        <div class="lg:hidden text-xs text-gray-600 mt-1">Purchased: {{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                        <div class="lg:hidden text-xs text-gray-600">{{ $ticketHolderName }} - {{ $ticketHolderContact }}</div>
                    </td>
                    <td class="hidden md:table-cell">
                        <div class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;">{{ $ticket->customer_status_label }}</div>
                    </td>
                    <td class="hidden lg:table-cell">{{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                    <td class="hidden lg:table-cell">
                        <div>{{ $ticketHolderName }}</div>
                        <div class="text-xs text-gray-600">{{ $ticketHolderContact }}</div>
                    </td>
                    <td>
                        <div class="flex justify-center gap-3 whitespace-nowrap">
                            @if($canOpenTicketPdf)
                            <a href="{{ route('account.ticket.pdf', $ticket) }}" target="_blank" class="hover:text-primary-color" title="Open Ticket PDF"><i class="fa-regular fa-file-pdf"></i></a>
                            @else
                            <span class="text-gray-300" title="Ticket PDF unavailable for this status"><i class="fa-regular fa-file-pdf"></i></span>
                            @endif
                            @if($ticket->invoice_id)
                            <a href="{{ route('account.ticket.invoice.pdf', $ticket) }}" target="_blank" class="hover:text-primary-color" title="Open Linked Invoice"><i class="fa-solid fa-file-invoice-dollar"></i></a>
                            @else
                            <span class="text-gray-300" title="No linked invoice"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                            @endif
                            @if(in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true))
                            @if($canEditHolderDetails)
                            <button
                                type="button"
                                class="hover:text-primary-color"
                                title="Edit attendee details"
                                x-on:click="openEditModal(
                                                    @js(route('account.ticket.attendee.update', $ticket)),
                                                    @js(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshopTitle),
                                                    {
                                                        firstname: @js((string) ($ticket->firstname ?? '')),
                                                        surname: @js((string) ($ticket->surname ?? '')),
                                                        email: @js((string) ($ticket->email ?? '')),
                                                        phone: @js((string) ($ticket->phone ?? ''))
                                                    }
                                                )">
                                <i class="fa-solid fa-user-pen"></i>
                            </button>
                            @else
                            <button
                                type="button"
                                class="hover:text-primary-color"
                                title="Edit attendee details"
                                x-on:click="SM.notice('Not permitted', 'Only the ticket purchaser can update holder details.', 'warning')">
                                <i class="fa-solid fa-user-pen"></i>
                            </button>
                            @endif
                            @else
                            <span class="text-gray-300" title="Ticket is not editable"><i class="fa-solid fa-user-pen"></i></span>
                            @endif
                            @if($canCancel)
                            <button
                                type="button"
                                class="hover:text-red-600"
                                title="Cancel ticket"
                                x-on:click="openCancelModal(@js(route('account.ticket.cancel', $ticket)), @js(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshopTitle))">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            @else
                            <span class="text-gray-300" title="Ticket can only be cancelled up to 2 hours before start time"><i class="fa-solid fa-ban"></i></span>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>

        {{ $tickets->appends(request()->query())->links() }}
        @endif

        <div
            x-show="editModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-on:keydown.escape.window="closeEditModal()">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="closeEditModal()"></div>
            <div class="relative z-10 w-full max-w-xl rounded-xl bg-white shadow-xl border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900">Update Attendee Details</h3>
                <p class="mt-2 text-sm text-gray-700">
                    Updating
                    <span class="font-semibold" x-text="editTicketLabel || 'this ticket'"></span>
                    will cancel the original ticket and issue a reissued replacement.
                </p>

                <form method="POST" x-bind:action="editFormAction" class="mt-6 space-y-3">
                    @csrf
                    <x-ui.input name="firstname" label="First Name" x-model="editFirstname" required />
                    <x-ui.input name="surname" label="Surname" x-model="editSurname" required />
                    <x-ui.input name="email" type="email" label="Email" x-model="editEmail" required />
                    <x-ui.input name="phone" label="Phone" x-model="editPhone" required />
                    <div class="pt-2 flex justify-end gap-3">
                        <x-ui.button type="button" color="primary-outline" x-on:click="closeEditModal()">Cancel</x-ui.button>
                        <x-ui.button type="submit">Reissue Ticket</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        <div
            x-show="cancelModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-on:keydown.escape.window="closeCancelModal()">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="closeCancelModal()"></div>
            <div class="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900">Cancel Ticket?</h3>
                <p class="mt-2 text-sm text-gray-700">
                    You are about to cancel
                    <span class="font-semibold" x-text="cancelTicketLabel || 'this ticket'"></span>.
                </p>
                <p class="mt-2 text-sm text-gray-700">
                    This will release the spot back to the workshop. If payment was made, a refund or account credit may be applied.
                </p>

                <form method="POST" x-bind:action="cancelFormAction" class="mt-6 flex justify-end gap-3">
                    @csrf
                    <x-ui.button type="button" color="primary-outline" x-on:click="closeCancelModal()">Keep Ticket</x-ui.button>
                    <x-ui.button type="submit" color="danger">Yes, Cancel Ticket</x-ui.button>
                </form>
            </div>
        </div>
    </x-container>
</x-layout>
