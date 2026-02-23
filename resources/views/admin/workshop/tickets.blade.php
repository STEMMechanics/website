<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Tickets</x-mast>

    <x-container x-data="{
        editModalOpen: false,
        editFormAction: '',
        editTicketLabel: '',
        editFirstname: '',
        editSurname: '',
        editEmail: '',
        editPhone: '',
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
        confirmSubmit(event, message, buttonLabel = 'Confirm') {
            event.preventDefault();
            const form = event.target?.closest('form');
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (window.SM && typeof window.SM.confirm === 'function') {
                window.SM.confirm('Confirm action', message, buttonLabel, (isConfirmed) => {
                    if (isConfirmed) {
                        form.submit();
                    }
                });
            }
        }
    }">
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-lg font-semibold">{{ $workshop->title }}</div>
            <div class="text-sm text-gray-600">
                Starts: {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}
            </div>
            <div class="text-sm text-gray-600">
                Tickets: {{ (int) ($activeTicketCount ?? 0) }} / {{ $workshop->max_tickets !== null ? (int) $workshop->max_tickets : 'Unlimited' }}
            </div>
            <div class="text-sm text-gray-600">
                @php
                $rawPrice = trim((string) ($workshop->price ?? ''));
                $priceDisplay = 'Free';
                if ($rawPrice !== '' && $rawPrice !== '0') {
                $numericPrice = preg_replace('/[^0-9.]/', '', $rawPrice);
                $priceDisplay = is_string($numericPrice) && $numericPrice !== '' && is_numeric($numericPrice)
                ? '$'.number_format((float) $numericPrice, 2)
                : $rawPrice;
                }
                @endphp
                Price: {{ $priceDisplay }}
            </div>
        </div>

        <div class="flex my-4 items-center gap-2">
            <div class="flex-1 flex gap-2">
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.attendance', $workshop) }}">Attendance</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.tickets.pdf', $workshop) }}" target="_blank">Ticket Roll PDF</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search Tickets" />
            </div>
        </div>

        @if($tickets->isEmpty())
        <x-none-found item="tickets" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table>
            <x-slot:header>
                <th>Ticket #</th>
                <th>Attendee</th>
                <th>Status</th>
                @if($showInvoiceColumn ?? false)
                <th>Invoice</th>
                @endif
                <th>Actions</th>
            </x-slot:header>
            <x-slot:body>
                @foreach($tickets as $ticket)
                @php
                $statusText = ucwords(str_replace('-', ' ', (string) ($ticket->status_label ?? '')));
                $invoice = $ticket->invoice;
                $hasSquarePayment = $invoice
                ? $invoice->allocations
                ->contains(fn ($allocation) => strtolower((string) ($allocation->customerPayment->gateway_provider ?? '')) === 'square')
                : false;
                $hasAnyPayment = $invoice
                ? $invoice->allocations
                ->contains(fn ($allocation) => $allocation->customerPayment !== null && (float) ($allocation->allocated_amount ?? 0) > 0)
                : false;
                $canCancel = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                $canOpenTicketPdf = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                @endphp
                <tr style="{{ in_array((int) $ticket->status, [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED], true) ? 'background-color: rgb(254 226 226);' : '' }}">
                    <td>{{ $ticket->reference_code ?: $ticket->id }}</td>
                    <td>
                        <div>{{ trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-' }}</div>
                        <div class="text-xs text-gray-500">{{ $ticket->email ?: '-' }}</div>
                    </td>
                    <td>{{ $statusText }}</td>
                    @if($showInvoiceColumn ?? false)
                    <td>{{ $invoice?->invoice_number ?? '-' }}</td>
                    @endif
                    <td>
                        <div class="flex justify-center items-center gap-3">
                            @if($canOpenTicketPdf)
                            <a href="{{ route('tickets.pdf', $ticket) }}" target="_blank" class="hover:text-primary-color" title="Open Ticket PDF">
                                <i class="fa-regular fa-file-pdf"></i>
                            </a>
                            @else
                            <span class="text-gray-300" title="Ticket PDF unavailable for this status"><i class="fa-regular fa-file-pdf"></i></span>
                            @endif
                            @if($ticket->invoice_id)
                            <a href="{{ route('tickets.invoice.pdf', $ticket) }}" target="_blank" class="hover:text-primary-color" title="Open Linked Invoice">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </a>
                            @else
                            <span class="text-gray-300" title="No linked invoice"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                            @endif
                            @if(in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true))
                            <button
                                type="button"
                                class="hover:text-primary-color"
                                title="Edit attendee details"
                                x-on:click="openEditModal(
                                                @js(route('tickets.attendee.update', $ticket)),
                                                @js(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshop->title),
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
                            <span class="text-gray-300" title="Ticket is not editable"><i class="fa-solid fa-user-pen"></i></span>
                            @endif

                            @if($canCancel)
                            <form method="POST" action="{{ route('admin.ticket.cancel', $ticket) }}">
                                @csrf
                                <input type="hidden" name="process_square_refund" value="0">
                                <button
                                    type="button"
                                    class="hover:text-amber-600"
                                    title="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}"
                                    x-on:click="confirmSubmit($event, @js($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?'), 'Cancel Ticket')">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                            </form>

                            @if($hasAnyPayment)
                            <form method="POST" action="{{ route('admin.ticket.cancel', $ticket) }}">
                                @csrf
                                <input type="hidden" name="process_square_refund" value="1">
                                <button
                                    type="button"
                                    class="hover:text-red-600"
                                    title="Cancel and refund now"
                                    x-on:click="confirmSubmit($event, @js('Cancel this ticket and attempt refund now? A tax adjustment note will be created first.'.($hasSquarePayment ? '' : ' No Square payment is linked; refund may require manual processing.')), 'Cancel and Refund')">
                                    <i class="fa-solid fa-money-bill-transfer"></i>
                                </button>
                            </form>
                            @endif
                            @else
                            <span class="text-gray-300" title="Ticket is not cancellable"><i class="fa-solid fa-ban"></i></span>
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
    </x-container>
</x-layout>
