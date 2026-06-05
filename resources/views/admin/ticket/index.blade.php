<x-layout>
    <x-mast>Tickets</x-mast>

    <x-container
        x-data="{}"
        data-cancel-reason="{{ old('reason', 'The following ticket has been cancelled.') }}"
        x-init="SM.initTicketCancelModal($el.dataset.cancelReason)">
        <form method="GET" action="{{ route('admin.ticket.index') }}">
            <x-ui.toolbar>
                <x-slot:left>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <x-ui.checkbox
                            name="show_inactive"
                            value="1"
                            label="Show cancelled/refunded"
                            :checked="!empty($showInactive)"
                            :noWrapper="true"
                            :inline="true"
                            onchange="this.form.submit()"
                        />
                        <x-ui.checkbox
                            name="group_by_workshop"
                            value="1"
                            label="Group by workshop"
                            :checked="!empty($groupByWorkshop)"
                            :noWrapper="true"
                            :inline="true"
                            onchange="this.form.submit()"
                        />
                    </div>
                </x-slot:left>
                <x-slot:right>
                    <div class="flex relative">
                        <input
                            class="bg-white grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300"
                            autocomplete="off"
                            placeholder="Search Tickets"
                            type="text"
                            name="search"
                            value="{{ request()->get('search', '') }}"
                        />
                        <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                    </div>
                </x-slot:right>
            </x-ui.toolbar>
        </form>

        @if($tickets->isEmpty())
            <x-none-found item="tickets" search="{{ request()->get('search') }}" />
        @else
            @php
                $workshopCounts = $tickets->getCollection()
                    ->groupBy(fn ($ticket) => (string) ($ticket->workshop_id ?? 'none'))
                    ->map(fn ($group) => $group->count());
                $previousWorkshopKey = null;
            @endphp
            <x-ui.table>
                <x-slot:header>
                    <th>Ticket #</th>
                    <th>{{ !empty($groupByWorkshop) ? 'Details' : 'Workshop' }}</th>
                    <th class="hidden md:table-cell">Attendee</th>
                    <th class="hidden lg:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Invoice</th>
                    <th class="hidden md:table-cell">Purchased</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($tickets as $ticket)
                        @php
                            $workshopKey = (string) ($ticket->workshop_id ?? 'none');
                            $statusText = (string) ($ticket->customer_status_label ?? 'Reserved');
                            $workshopTitle = (string) ($ticket->workshop?->title ?? '-');
                            $workshopDate = $ticket->workshop?->starts_at?->format('M j, Y g:i a') ?? '-';
                            $workshopLocation = (string) ($ticket->workshop?->getLocationName() ?? '-');
                            $workshopRawPrice = trim((string) ($ticket->workshop?->price ?? ''));
                            $workshopPriceDisplay = 'Free';
                            if ($workshopRawPrice !== '' && $workshopRawPrice !== '0') {
                                $workshopNumericPrice = preg_replace('/[^0-9.]/', '', $workshopRawPrice);
                                $workshopPriceDisplay = is_string($workshopNumericPrice) && $workshopNumericPrice !== '' && is_numeric($workshopNumericPrice)
                                    ? '$'.number_format((float) $workshopNumericPrice, 2)
                                    : $workshopRawPrice;
                            }
                            $attendee = trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-';
                            $invoice = $ticket->invoice;
                            $invoiceNumber = (string) ($invoice?->invoice_number ?? '-');
                            $invoiceUrl = $invoice ? route('admin.invoice.edit', $invoice) : null;
                            $canOpenTicketPdf = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                            $isInactiveStatus = in_array((int) $ticket->status, [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED], true);
                            $hasSquarePayment = $ticket->invoice
                                ? $ticket->invoice->allocations
                                    ->contains(fn ($allocation) => strtolower((string) ($allocation->customerPayment->gateway_provider ?? '')) === 'square')
                                : false;
                            $hasAnyPayment = $ticket->invoice
                                ? $ticket->invoice->allocations
                                    ->contains(fn ($allocation) => $allocation->customerPayment !== null && (float) ($allocation->allocated_amount ?? 0) > 0)
                                : false;
                            $showSquareRefundOption = $hasSquarePayment && $hasAnyPayment;
                            $canCancel = in_array((int) $ticket->status, \App\Models\Ticket::activePurchasedStatuses(), true);
                        @endphp
                        @if(!empty($groupByWorkshop) && $workshopKey !== $previousWorkshopKey)
                            <tr style="background-color: rgb(254 249 195);">
                                <td colspan="7">
                                    <div class="font-semibold">{{ $workshopTitle }}</div>
                                    <div class="text-xs text-gray-600">{{ $workshopDate }} · {{ $workshopLocation }}</div>
                                    <div class="text-xs text-gray-600">Price: {{ $workshopPriceDisplay }}</div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        {{ (int) ($workshopCounts[$workshopKey] ?? 0) }} ticket{{ ((int) ($workshopCounts[$workshopKey] ?? 0) === 1) ? '' : 's' }}
                                        @if($ticket->workshop)
                                            · <a href="{{ route('admin.workshop.tickets', $ticket->workshop) }}" class="text-primary-color hover:underline">Manage workshop tickets</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                        <tr style="{{ $isInactiveStatus ? 'background-color: rgb(254 226 226);' : '' }}">
                            <td>
                                <div class="whitespace-nowrap">{{ !empty($groupByWorkshop) ? '↳ ' : '' }}{{ $ticket->reference_code ?: $ticket->id }}</div>
                                @if($ticket->isEarlyBirdTicket())
                                    <div class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Early bird</div>
                                @endif
                                <div class="lg:hidden text-xs text-gray-600 mt-1">{{ $statusText }}</div>
                            </td>
                            <td>
                                @if(empty($groupByWorkshop))
                                    <div>{{ $workshopTitle }}</div>
                                    <div class="text-xs text-gray-600">{{ $workshopDate }} · {{ $workshopLocation }}</div>
                                @else
                                    <div class="text-xs text-gray-600">Purchased: {{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                @endif
                                <div class="md:hidden text-xs text-gray-600 mt-1">{{ $attendee }} · {{ $ticket->email ?: '-' }}</div>
                                <div class="lg:hidden text-xs text-gray-600">
                                    @if($invoiceUrl)
                                        <a href="{{ $invoiceUrl }}" class="text-primary-color hover:underline">
                                            {{ $invoiceNumber }}
                                        </a>
                                    @else
                                        --
                                    @endif
                                </div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div>{{ $attendee }}</div>
                                <div class="text-xs text-gray-600">{{ $ticket->email ?: '-' }}</div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $statusText }}</td>
                            <td class="hidden lg:table-cell text-center">
                                @if($invoiceUrl)
                                    <a href="{{ $invoiceUrl }}" class="text-primary-color hover:underline">
                                        {{ $invoiceNumber }}
                                    </a>
                                @else
                                    --
                                @endif
                            </td>
                            <td class="hidden md:table-cell">{{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                            <td>
                                <div class="flex justify-center items-center gap-3 whitespace-nowrap">
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

                                    @if($ticket->workshop)
                                        <a href="{{ route('admin.workshop.tickets', $ticket->workshop) }}" class="hover:text-primary-color" title="Manage workshop tickets">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    @else
                                        <span class="text-gray-300" title="No workshop linked"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                                    @endif

                                    @if($canCancel)
                                        <button
                                            type="button"
                                            class="hover:text-amber-600"
                                            title="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}"
                                            x-on:click="SM.openTicketCancelModal(
                                                @js(route('admin.ticket.cancel', $ticket)),
                                                @js(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshopTitle),
                                                @js($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?'),
                                                'Cancel Ticket',
                                                @js($showSquareRefundOption),
                                                @js($showSquareRefundOption)
                                            )"
                                        >
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    @else
                                        <span class="text-gray-300" title="Ticket is not cancellable"><i class="fa-solid fa-ban"></i></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @php($previousWorkshopKey = $workshopKey)
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $tickets->appends(request()->query())->links() }}
        @endif

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
    </x-container>
</x-layout>
