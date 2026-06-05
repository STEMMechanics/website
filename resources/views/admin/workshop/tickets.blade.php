@php
    $defaultBulkEmailSubject = 'A message about the '.trim((string) ($workshop->title ?? 'workshop'));
    $createTicketModalOpen = old('manual_ticket_type') !== null || $errors->has('manual_ticket_type');
    $bulkEmailModalOpen = $errors->has('email_subject') || $errors->has('email_message');
    $smsModalOpen = old('sms_message') !== null || $errors->has('sms_message') || $errors->has('sms_recipient_ids');
    $emailTicketChecked = in_array((string) old('email_ticket', '1'), ['1', 'on', 'true'], true);
    $smsSelectedRecipientIds = collect(old('sms_recipient_ids', $smsDefaultRecipientIds ?? []))
        ->map(fn ($id) => (string) $id)
        ->filter(fn ($id) => $id !== '')
        ->values()
        ->all();
    $smsButtonEnabled = (bool) ($smsFlowConfigured ?? false) && (int) ($smsRecipientCount ?? 0) > 0;
    $smsButtonTitle = ! ($smsFlowConfigured ?? false)
        ? 'SMSFlow is not configured'
        : ((int) ($smsRecipientCount ?? 0) > 0 ? 'Text ticket contacts' : 'No ticket contacts with mobile numbers are available');
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
                bulkEmailOpen: @js($bulkEmailModalOpen),
                smsOpen: @js($smsModalOpen),
            }"
            data-cancel-reason="{{ old('reason', 'The following ticket has been cancelled.') }}"
            x-init="SM.initTicketCancelModal($el.dataset.cancelReason)">
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-lg font-semibold">{{ $workshop->title }}</div>
            <div class="flex gap-1 flex-col sm:flex-row sm:gap-6">
                @php
                    $baseTicketPriceAmount = (float) $workshop->baseTicketPriceAmount();
                    $earlyBirdPriceAmount = $workshop->earlyBirdPriceAmount();
                    $hasEarlyBirdPrice = $earlyBirdPriceAmount !== null && abs($baseTicketPriceAmount - $earlyBirdPriceAmount) > 0.0001;
                    $priceDisplay = $baseTicketPriceAmount > 0.0001 ? '$'.number_format($baseTicketPriceAmount, 2) : 'Free';
                    $earlyBirdPriceDisplay = $earlyBirdPriceAmount !== null && $earlyBirdPriceAmount > 0.0001
                        ? '$'.number_format($earlyBirdPriceAmount, 2)
                        : 'Free';
                @endphp
                <div class="text-sm text-gray-600">
                    <span class="font-semibold">Starts:</span> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}
                </div>
                <div class="text-sm text-gray-600">
                    <span class="font-semibold">Tickets:</span> {{ (int) ($activeTicketCount ?? 0) }} / {{ $workshop->max_tickets !== null ? (int) $workshop->max_tickets : 'Unlimited' }}
                </div>
                <div class="text-sm text-gray-600">
                    <span class="font-semibold">Price:</span>
                    <span>{{ $priceDisplay }}</span>
                    @if($hasEarlyBirdPrice)
                        <span>(Early bird price: {{ $earlyBirdPriceDisplay }})</span>
                    @endif
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
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                    x-on:click.prevent="smsOpen = true"
                    title="{{ $smsButtonTitle }}"
                    aria-label="{{ $smsButtonTitle }}"
                    @disabled(! $smsButtonEnabled)
                >
                    <i class="fa-solid fa-comment-sms"></i>
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
                $isEarlyBirdTicket = (bool) $ticket->isEarlyBirdTicket();
                $attendeeName = trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-';
                $attendeeMobile = formatPhoneNumber((string) ($ticket->phone ?? $ticket->user?->phone ?? '')) ?: '-';
                @endphp
                <section class="rounded-2xl border p-4 shadow-sm {{ $isInactiveStatus ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $ticket->reference_code ?: $ticket->id }}
                                @if($isEarlyBirdTicket)
                                    <x-ui.badge color="amber" size="xs" uppercase="true" data-early-bird-badge="true" class="ml-2">Early Bird</x-ui.badge>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-gray-700">{{ $attendeeName }}</div>
                            <div class="text-xs text-gray-500 break-all">{{ $ticket->email ?: '-' }}</div>
                            <div class="text-xs text-gray-500">{{ $attendeeMobile }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <x-ui.badge color="gray">{{ $statusText }}</x-ui.badge>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invoice</div>
                            <div class="mt-1 text-sm text-gray-700 text-left">
                                @if($invoice)
                                    <a href="{{ route('admin.invoice.edit', $invoice) }}" class="block text-left font-normal text-primary-color hover:underline">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                @else
                                    --
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Mobile</div>
                            <div class="mt-1 text-sm text-gray-700">{{ $attendeeMobile }}</div>
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
                                x-on:click="SM.openTicketCancelModal(
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
                $attendeeMobile = formatPhoneNumber((string) ($ticket->phone ?? $ticket->user?->phone ?? '')) ?: '-';
                $isEarlyBirdTicket = (bool) $ticket->isEarlyBirdTicket();
                @endphp
                <tr style="{{ in_array((int) $ticket->status, [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED], true) ? 'background-color: rgb(254 226 226);' : '' }}">
                    <td class="text-center!">
                        <div>{{ $ticket->reference_code ?: $ticket->id }}</div>
                        @if($isEarlyBirdTicket)
                            <x-ui.badge color="amber" size="xs" uppercase="true" data-early-bird-badge="true">Early Bird</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div>{{ trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-' }}</div>
                        <div class="text-xs text-gray-500">{{ $ticket->email ?: '-' }}</div>
                        <div class="text-xs text-gray-500">{{ $attendeeMobile }}</div>
                    </td>
                    <td class="text-center">
                        <span>{{ $statusText }}</span>
                    </td>
                    @if($showInvoiceColumn ?? false)
                    <td class="text-center">
                        @if($invoice)
                            <a href="{{ route('admin.invoice.edit', $invoice) }}" class="block text-center font-normal text-primary-color hover:underline">
                                {{ $invoice->invoice_number }}
                            </a>
                        @else
                            -
                        @endif
                    </td>
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
                                x-on:click="SM.openTicketCancelModal(
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
            x-show="$store.ticketCancelModal.open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-on:keydown.escape.window="SM.closeTicketCancelModal()">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="SM.closeTicketCancelModal()"></div>
            <div class="relative z-10 w-full max-w-xl rounded-xl border border-gray-200 bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900" x-text="$store.ticketCancelModal.submitLabel"></h3>
                <p class="mt-2 text-sm text-gray-700">
                    You are about to cancel
                    <span class="font-semibold" x-text="$store.ticketCancelModal.ticketLabel || 'this ticket'"></span>.
                </p>
                <p class="mt-2 text-sm text-gray-700" x-text="$store.ticketCancelModal.confirmationMessage"></p>

                <form method="POST" x-bind:action="$store.ticketCancelModal.formAction" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="process_square_refund" x-bind:value="$store.ticketCancelModal.processSquareRefund ? '1' : '0'">
                    <input type="hidden" name="email_customer" x-bind:value="$store.ticketCancelModal.emailCustomer ? '1' : '0'">

                    <div>
                        <label class="block text-sm font-semibold text-gray-900" for="cancel-reason">Cancellation message</label>
                        <textarea
                            id="cancel-reason"
                            name="reason"
                            rows="4"
                            x-model="$store.ticketCancelModal.reason"
                            required
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-600">This text replaces the opening line in the customer email.</p>
                    </div>

                    <label class="flex flex-col items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex gap-3">
                            <input type="checkbox" x-model="$store.ticketCancelModal.emailCustomer" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                            <span class="block text-sm font-semibold text-gray-900">Email customer about this cancellation</span>
                        </div>

                        <template x-if="$store.ticketCancelModal.showSquareRefund">
                            <label class="flex gap-3">
                                <input type="checkbox" x-model="$store.ticketCancelModal.processSquareRefund" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                                <span class="block text-sm font-semibold text-gray-900">Process Square refund</span>
                            </label>
                        </template>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button type="button" color="primary-outline" x-on:click="SM.closeTicketCancelModal()">Keep Ticket</x-ui.button>
                        <button
                            type="submit"
                            class="inline-flex justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                            x-bind:class="'bg-danger-color hover:bg-danger-color-dark focus-visible:outline-danger-color'">
                            <span x-text="$store.ticketCancelModal.submitLabel"></span>
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
            x-show="smsOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-on:keydown.escape.window="smsOpen = false">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="smsOpen = false"></div>
            <div class="relative z-10 w-full max-w-3xl rounded-xl bg-white shadow-xl border border-gray-200 p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Text Ticket Contacts</h3>
                        <p class="mt-1 text-sm text-gray-600">Only ticket holders with a mobile number are selectable.</p>
                    </div>
                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="smsOpen = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                @if(($smsRecipientCount ?? 0) === 0)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        No ticket holders with a mobile number are available for this workshop.
                    </div>
                @else
                <form method="POST" action="{{ route('admin.workshop.tickets.sms', $workshop) }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm pl-1" for="workshop-sms-message">Message</label>
                        <textarea
                            id="workshop-sms-message"
                            name="sms_message"
                            rows="6"
                            class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('sms_message') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                            required>{{ (string) old('sms_message', '') }}</textarea>
                        @if($errors->has('sms_message'))
                        <div class="text-xs text-red-600 ml-2 mt-1">{{ $errors->first('sms_message') }}</div>
                        @endif
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Recipients</div>
                                <div class="mt-1 text-xs text-gray-600">
                                    Sending to {{ count($smsSelectedRecipientIds) }} {{ count($smsSelectedRecipientIds) === 1 ? 'person' : 'people' }}.
                                </div>
                            </div>
                        </div>

                        @if($errors->has('sms_recipient_ids'))
                        <div class="mt-2 text-xs text-red-600">{{ $errors->first('sms_recipient_ids') }}</div>
                        @endif

                        <div class="mt-4 max-h-[28rem] space-y-2 overflow-auto pr-1">
                            @foreach($smsRecipients as $recipient)
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 {{ $recipient['can_message'] ? '' : 'opacity-60' }}">
                                    <input
                                        type="checkbox"
                                        name="sms_recipient_ids[]"
                                        value="{{ $recipient['ticket_id'] }}"
                                        @checked(in_array((string) $recipient['ticket_id'], $smsSelectedRecipientIds, true))
                                        @disabled(! $recipient['can_message'])
                                        class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color" />
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900">{{ $recipient['reference'] }} - {{ $recipient['name'] }}</div>
                                        <div class="text-xs text-gray-600">
                                            {{ $recipient['can_message'] ? $recipient['formatted_phone'] : 'No mobile number on file' }}
                                        </div>
                                        @if(($recipient['can_message'] ?? false) && count($recipient['ticket_ids'] ?? []) > 1)
                                            <div class="mt-0.5 text-xs text-gray-500">{{ count($recipient['ticket_ids']) }} tickets share this number</div>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-3">
                        <x-ui.button type="button" color="primary-outline" x-on:click="smsOpen = false">Cancel</x-ui.button>
                        <x-ui.button type="submit">Send SMS</x-ui.button>
                    </div>
                </form>
                @endif
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
