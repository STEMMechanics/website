@php
    $defaultBulkEmailSubject = 'A message about the '.trim((string) ($workshop->title ?? 'workshop'));
    $createTicketModalOpen = old('manual_ticket_type') !== null || $errors->has('manual_ticket_type');
    $bulkEmailModalOpen = $errors->has('email_subject') || $errors->has('email_message');
    $emailTicketChecked = in_array((string) old('email_ticket', '1'), ['1', 'on', 'true'], true);
@endphp

<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Tickets</x-mast>

    <x-container>
        <div
            x-data="{
                createTicketOpen: @js($createTicketModalOpen),
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
                cancelModalOpen: false,
                cancelFormAction: '',
                cancelTicketLabel: '',
                cancelConfirmationMessage: '',
                cancelSubmitLabel: 'Cancel Ticket',
                cancelProcessSquareRefund: false,
                cancelShowSquareRefund: false,
                cancelEmailCustomer: true,
                cancelReasonDefault: @js(old('reason', 'The following ticket has been cancelled.')),
                cancelReason: @js(old('reason', 'The following ticket has been cancelled.')),
                openCancelModal(action, label, message, submitLabel = 'Cancel Ticket', showSquareRefund = false, processSquareRefund = false) {
                    this.cancelFormAction = action;
                    this.cancelTicketLabel = label;
                    this.cancelConfirmationMessage = message;
                    this.cancelSubmitLabel = submitLabel;
                    this.cancelShowSquareRefund = Boolean(showSquareRefund);
                    this.cancelProcessSquareRefund = Boolean(processSquareRefund);
                    this.cancelEmailCustomer = true;
                    this.cancelReason = this.cancelReasonDefault;
                    this.cancelModalOpen = true;
                },
                closeCancelModal() {
                    this.cancelModalOpen = false;
                    this.cancelFormAction = '';
                    this.cancelTicketLabel = '';
                    this.cancelConfirmationMessage = '';
                    this.cancelSubmitLabel = 'Cancel Ticket';
                    this.cancelProcessSquareRefund = false;
                    this.cancelShowSquareRefund = false;
                    this.cancelEmailCustomer = true;
                    this.cancelReason = this.cancelReasonDefault;
                },
                bulkEmailOpen: @js($bulkEmailModalOpen),
            }">
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-lg font-semibold">{{ $workshop->title }}</div>
            <div class="flex gap-1 flex-col sm:flex-row sm:gap-6">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold">Starts:</span> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}
                </div>
                <div class="text-sm text-gray-600">
                    <span class="font-semibold">Tickets:</span> {{ (int) ($activeTicketCount ?? 0) }} / {{ $workshop->max_tickets !== null ? (int) $workshop->max_tickets : 'Unlimited' }}
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
                    <span class="font-semibold">Price:</span> {{ $priceDisplay }}
                </div>
                <div class="text-sm text-gray-600">
                    <span class="font-semibold">Available:</span> {{ $availableTicketCount !== null ? (int) $availableTicketCount : 'Unlimited' }}
                </div>
            </div>
        </div>

        <div class="my-4 flex flex-col gap-3 md:flex-row md:items-center">
            <div class="flex flex-1 flex-wrap gap-2">
                <x-ui.button type="button" x-on:click.prevent="createTicketOpen = true">Create Ticket</x-ui.button>
                <a
                    href="{{ route('admin.workshop.tickets.pdf', $workshop) }}"
                    target="_blank"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                    title="Ticket Roll PDF"
                    aria-label="Ticket Roll PDF"
                >
                    <i class="fa-regular fa-file-pdf"></i>
                </a>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                    x-on:click.prevent="bulkEmailOpen = true"
                    title="Email Ticket Contacts"
                    aria-label="Email Ticket Contacts"
                >
                    <i class="fa-regular fa-envelope"></i>
                </button>
            </div>
            <div class="w-full md:w-auto md:min-w-[18rem]">
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
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 hover:text-amber-900"
                                title="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}"
                                x-on:click="openCancelModal(
                                    @js(route('admin.ticket.cancel', $ticket)),
                                    @js(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshop->title),
                                    @js($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?'),
                                    'Cancel Ticket',
                                    @js($hasSquarePayment && $hasAnyPayment),
                                    @js($hasSquarePayment && $hasAnyPayment)
                                )">
                                <i class="fa-solid fa-ban"></i>
                                Cancel
                            </button>
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
                            <button
                                type="button"
                                class="hover:text-amber-600"
                                title="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}"
                                x-on:click="openCancelModal(
                                    @js(route('admin.ticket.cancel', $ticket)),
                                    @js(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshop->title),
                                    @js($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?'),
                                    'Cancel Ticket',
                                    @js($hasSquarePayment && $hasAnyPayment),
                                    @js($hasSquarePayment && $hasAnyPayment)
                                )">
                                <i class="fa-solid fa-ban"></i>
                            </button>
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
            x-show="createTicketOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-on:keydown.escape.window="createTicketOpen = false">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="createTicketOpen = false"></div>
            <div class="relative z-10 w-full max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Create Ticket</h3>
                        <p class="mt-1 text-sm text-gray-600">Create a free ticket or a reserved pay-at-door ticket for this workshop.</p>
                    </div>
                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="createTicketOpen = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.workshop.tickets.store', $workshop) }}" class="mt-4 space-y-4">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.select name="manual_ticket_type" label="Ticket Type">
                            <option value="free" @selected(old('manual_ticket_type', 'free') === 'free')>Free ticket</option>
                            <option value="reserve" @selected(old('manual_ticket_type') === 'reserve')>Reserve ticket (Pay at Door)</option>
                        </x-ui.select>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                            Free tickets are created as active tickets with no invoice. Reserved tickets are created as pay-at-door tickets and generate an invoice.
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input label="First Name" name="firstname" value="{{ old('firstname') }}" />
                        <x-ui.input label="Surname" name="surname" value="{{ old('surname') }}" />
                        <x-ui.input type="email" label="Email" name="email" value="{{ old('email') }}" info="If this email matches an existing user, the ticket is linked to their account automatically." />
                        <x-ui.input label="Phone" name="phone" value="{{ old('phone') }}" />
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <input type="hidden" name="email_ticket" value="0" />
                        <x-ui.checkbox
                            name="email_ticket"
                            value="1"
                            label="Email ticket to this email address"
                            checked="{{ $emailTicketChecked }}"
                            small
                            noWrapper />
                        <div class="mt-2 text-xs text-gray-600">Includes the ticket PDF and the invoice when one was created.</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button type="button" color="primary-outline" x-on:click="createTicketOpen = false">Cancel</x-ui.button>
                        <x-ui.button type="submit">Create Ticket</x-ui.button>
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
            <div class="relative z-10 w-full max-w-xl rounded-xl border border-gray-200 bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900" x-text="cancelSubmitLabel"></h3>
                <p class="mt-2 text-sm text-gray-700">
                    You are about to cancel
                    <span class="font-semibold" x-text="cancelTicketLabel || 'this ticket'"></span>.
                </p>
                <p class="mt-2 text-sm text-gray-700" x-text="cancelConfirmationMessage"></p>

                <form method="POST" x-bind:action="cancelFormAction" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="process_square_refund" x-bind:value="cancelProcessSquareRefund ? '1' : '0'">
                    <input type="hidden" name="email_customer" x-bind:value="cancelEmailCustomer ? '1' : '0'">

                    <div>
                        <label class="block text-sm font-semibold text-gray-900" for="cancel-reason">Cancellation message</label>
                        <textarea
                            id="cancel-reason"
                            name="reason"
                            rows="4"
                            x-model="cancelReason"
                            required
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-600">This text replaces the opening line in the customer email.</p>
                    </div>

                    <label class="flex flex-col items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex gap-3">
                            <input type="checkbox" x-model="cancelEmailCustomer" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                            <span class="block text-sm font-semibold text-gray-900">Email customer about this cancellation</span>
                        </div>

                        <template x-if="cancelShowSquareRefund">
                            <label class="flex gap-3">
                                <input type="checkbox" x-model="cancelProcessSquareRefund" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                                <span class="block text-sm font-semibold text-gray-900">Process Square refund</span>
                            </label>
                        </template>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button type="button" color="primary-outline" x-on:click="closeCancelModal()">Keep Ticket</x-ui.button>
                        <button
                            type="submit"
                            class="inline-flex justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                            x-bind:class="'bg-danger-color hover:bg-danger-color-dark focus-visible:outline-danger-color'">
                            <span x-text="cancelSubmitLabel"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

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
        </div>
    </x-container>
</x-layout>
