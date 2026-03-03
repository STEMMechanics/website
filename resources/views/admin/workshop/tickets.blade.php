@php
    $defaultBulkEmailSubject = 'A message about the '.trim((string) ($workshop->title ?? 'workshop'));
@endphp

<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Tickets</x-mast>

    <x-container
        x-data="{
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
        bulkEmailOpen: false,
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
    }"
        x-init="{{ ($errors->has('email_subject') || $errors->has('email_message')) ? 'bulkEmailOpen = true' : '' }}">
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

        <div class="my-4 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex flex-1 flex-wrap gap-2">
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.attendance', $workshop) }}">Attendance</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.pick-list', $workshop) }}">Pick List</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.tickets.pdf', $workshop) }}" target="_blank">Ticket Roll PDF</x-ui.button>
                <x-ui.button type="button" color="outline" x-on:click.prevent="bulkEmailOpen = true">Email Ticket Contacts</x-ui.button>
            </div>
            <div class="w-full lg:w-auto lg:min-w-[18rem] lg:flex-1">
                <x-ui.search name="search" label="Search Tickets" class="w-full" />
            </div>
        </div>

        @if($tickets->isEmpty())
        <x-none-found item="tickets" search="{{ request()->get('search') }}" />
        @else
        <div class="space-y-4 lg:hidden">
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
                $isInactiveStatus = in_array((int) $ticket->status, [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED], true);
                $attendeeName = trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-';
                @endphp
                <section class="rounded-2xl border p-4 shadow-sm {{ $isInactiveStatus ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $ticket->reference_code ?: $ticket->id }}</div>
                            <div class="mt-1 text-sm text-gray-700">{{ $attendeeName }}</div>
                            <div class="text-xs text-gray-500 break-all">{{ $ticket->email ?: '-' }}</div>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">{{ $statusText }}</span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invoice</div>
                            <div class="mt-1 text-sm text-gray-700">{{ $invoice?->invoice_number ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</div>
                            <div class="mt-1 text-sm text-gray-700">{{ $ticket->phone ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if($canOpenTicketPdf)
                            <a href="{{ route('tickets.pdf', $ticket) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:text-primary-color" title="Open Ticket PDF">
                                <i class="fa-regular fa-file-pdf"></i>
                                Ticket PDF
                            </a>
                        @endif

                        @if($ticket->invoice_id)
                            <a href="{{ route('tickets.invoice.pdf', $ticket) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:text-primary-color" title="Open Linked Invoice">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                                Invoice
                            </a>
                        @endif

                        @if(in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true))
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:text-primary-color"
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
                                Reissue
                            </button>
                        @endif
                    </div>

                    @if($canCancel)
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.ticket.cancel', $ticket) }}">
                                @csrf
                                <input type="hidden" name="process_square_refund" value="0">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 hover:text-amber-900"
                                    title="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}"
                                    x-on:click="confirmSubmit($event, @js($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?'), 'Cancel Ticket')">
                                    <i class="fa-solid fa-ban"></i>
                                    Cancel
                                </button>
                            </form>

                            @if($hasAnyPayment)
                                <form method="POST" action="{{ route('admin.ticket.cancel', $ticket) }}">
                                    @csrf
                                    <input type="hidden" name="process_square_refund" value="1">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 hover:text-red-800"
                                        title="Cancel and refund now"
                                        x-on:click="confirmSubmit($event, @js('Cancel this ticket and attempt refund now? A tax adjustment note will be created first.'.($hasSquarePayment ? '' : ' No Square payment is linked; refund may require manual processing.')), 'Cancel and Refund')">
                                        <i class="fa-solid fa-money-bill-transfer"></i>
                                        Refund now
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <div class="hidden lg:block">
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
        </div>

        {{ $tickets->appends(request()->query())->links() }}
        @endif

        <div
            x-show="bulkEmailOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-on:keydown.escape.window="bulkEmailOpen = false">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="bulkEmailOpen = false"></div>
            <div class="relative z-10 w-full max-w-2xl rounded-xl bg-white shadow-xl border border-gray-200 p-6">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Email Ticket Contacts</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="bulkEmailOpen = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.workshop.tickets.email', $workshop) }}" class="mt-4 space-y-3">
                    @csrf
                    <x-ui.input
                        name="email_subject"
                        label="Subject"
                        value="{{ (string) old('email_subject', $defaultBulkEmailSubject) }}"
                        required />
                    @if($errors->has('email_subject'))
                    <div class="text-xs text-red-600 ml-2 mt-1">{{ $errors->first('email_subject') }}</div>
                    @endif

                    <label class="block text-sm pl-1" for="workshop-bulk-email-message">Message</label>
                    <textarea
                        id="workshop-bulk-email-message"
                        name="email_message"
                        rows="10"
                        class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('email_message') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                        required>{{ (string) old('email_message', '') }}</textarea>
                    @if($errors->has('email_message'))
                    <div class="text-xs text-red-600 ml-2 mt-1">{{ $errors->first('email_message') }}</div>
                    @endif

                    <div class="pt-2 flex justify-between items-center gap-3">
                        <div class="text-xs text-gray-600">
                            Sending to {{ (int) ($bulkEmailRecipientCount ?? 0) }} {{ (int) ($bulkEmailRecipientCount ?? 0) === 1 ? 'person' : 'people' }}.
                        </div>
                        <div class="flex justify-end gap-3">
                            <x-ui.button type="button" color="primary-outline" x-on:click="bulkEmailOpen = false">Cancel</x-ui.button>
                            <x-ui.button type="submit">Send Email</x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

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
