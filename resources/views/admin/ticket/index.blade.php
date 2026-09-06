<x-layout>
    <x-mast>Tickets</x-mast>

    <x-container
        x-data="{}"
        data-cancel-reason="{{ old('reason', 'The following ticket has been cancelled.') }}"
        x-init="SM.initTicketCancelModal($el.dataset.cancelReason)">
        <x-ui.dynamic-list name="admin-ticket-index">

            <x-slot:presetActions>
                <form method="GET" action="{{ url()->current() }}">
                    <x-ui.query-inputs :values="request()->except(['group_by_workshop', 'page'])" />
                    <x-ui.checkbox name="group_by_workshop" value="1" label="Group by workshop" :checked="!empty($groupByWorkshop)" noWrapper inline onchange="this.form.requestSubmit()" />
                </form>
            </x-slot:presetActions>
        <x-ui.collection-controls class="my-5" />

        @if($tickets->isEmpty())
            <x-none-found item="tickets" search="{{ request()->get('search') }}" />
        @else
            @php
                $workshopCounts = $tickets->getCollection()
                    ->groupBy(fn ($ticket) => (string) ($ticket->workshop_id ?? 'none'))
                    ->map(fn ($group) => $group->count());
                $previousWorkshopKey = null;
            @endphp
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="reference_code" label="Ticket #" />
                    <th>{{ !empty($groupByWorkshop) ? 'Details' : 'Workshop' }}</th>
                    <x-ui.list-heading field="firstname" class="hidden md:table-cell" label="Attendee" />
                    <x-ui.list-heading class="hidden lg:table-cell text-center!" label="Status" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Invoice" />
                    <x-ui.list-heading field="created_at" class="hidden md:table-cell" label="Purchased" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                    <div class="text-xs text-gray-600"><x-ui.date-time>{{ $workshopDate }}</x-ui.date-time> · {{ $workshopLocation }}</div>
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
                                    <x-ui.badge color="amber" uppercase class="mt-1">Early bird</x-ui.badge>
                                @endif
                                <div class="lg:hidden text-xs text-gray-600 mt-1">{{ $statusText }}</div>
                            </td>
                            <td>
                                @if(empty($groupByWorkshop))
                                    <div>{{ $workshopTitle }}</div>
                                    <div class="text-xs text-gray-600"><x-ui.date-time>{{ $workshopDate }}</x-ui.date-time> · {{ $workshopLocation }}</div>
                                @else
                                    <div class="text-xs text-gray-600">Purchased: <x-ui.date-time>{{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
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
                            <td class="hidden lg:table-cell text-center!">{{ $statusText }}</td>
                            <td class="hidden lg:table-cell text-center">
                                @if($invoiceUrl)
                                    <a href="{{ $invoiceUrl }}" class="text-primary-color hover:underline">
                                        {{ $invoiceNumber }}
                                    </a>
                                @else
                                    --
                                @endif
                            </td>
                            <td class="hidden md:table-cell"><x-ui.date-time>{{ $ticket->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></td>
                            <td class="text-center!">
                                <x-ui.row-actions class="whitespace-nowrap">
                                    @if($canOpenTicketPdf)
                                        <x-ui.row-action label="Open Ticket PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('tickets.pdf', $ticket) }}" target="_blank" />
                                    @else
                                        <span class="text-gray-300" title="Ticket PDF unavailable for this status"><i class="fa-regular fa-file-pdf"></i></span>
                                    @endif

                                    @if($ticket->invoice_id)
                                        <x-ui.row-action label="Open Linked Invoice" icon="fa-solid fa-file-invoice-dollar" tone="neutral" href="{{ route('tickets.invoice.pdf', $ticket) }}" target="_blank" />
                                    @else
                                        <span class="text-gray-300" title="No linked invoice"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                    @endif

                                    @if($ticket->workshop)
                                        <x-ui.row-action label="Manage workshop tickets" icon="fa-solid fa-arrow-up-right-from-square" tone="neutral" href="{{ route('admin.workshop.tickets', $ticket->workshop) }}" />
                                    @else
                                        <span class="text-gray-300" title="No workshop linked"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                                    @endif

                                    @if($canCancel)
                                        <x-ui.row-action label="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}" icon="fa-solid fa-ban" tone="warning"
                                            type="button"
                                            x-on:click="SM.openTicketCancelModal(
                                                {{ \Illuminate\Support\Js::from(route('admin.ticket.cancel', $ticket)) }},
                                                {{ \Illuminate\Support\Js::from(($ticket->reference_code ?: '#'.$ticket->id).' - '.$workshopTitle) }},
                                                {{ \Illuminate\Support\Js::from($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?') }},
                                                'Cancel Ticket',
                                                {{ \Illuminate\Support\Js::from($showSquareRefundOption) }},
                                                {{ \Illuminate\Support\Js::from($showSquareRefundOption) }}
                                            )"
                                         />
                                    @else
                                        <span class="text-gray-300" title="Ticket is not cancellable"><i class="fa-solid fa-ban"></i></span>
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                        @php($previousWorkshopKey = $workshopKey)
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$tickets" />
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
                        <x-ui.textarea-control
                            id="cancel-reason"
                            name="reason"
                            rows="4"
                            x-model="$store.ticketCancelModal.reason"
                            required
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        ></x-ui.textarea-control>
                        <p class="mt-1 text-xs text-gray-600">This text replaces the opening line in the customer email.</p>
                    </div>

                    <label class="flex flex-col items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex gap-3">
                            <x-ui.checkbox bare small x-model="$store.ticketCancelModal.emailCustomer" class="mt-1" />
                            <span class="block text-sm font-semibold text-gray-900">Email customer about this cancellation</span>
                        </div>

                        <template x-if="$store.ticketCancelModal.showSquareRefund">
                            <label class="flex gap-3">
                                <x-ui.checkbox bare small x-model="$store.ticketCancelModal.processSquareRefund" class="mt-1" />
                                <span class="block text-sm font-semibold text-gray-900">Process Square refund</span>
                            </label>
                        </template>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button type="button" color="primary-outline" x-on:click="SM.closeTicketCancelModal()">Keep Ticket</x-ui.button>
                        <x-ui.button variant="plain"
                            type="submit"
                            class="inline-flex justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition focus-visible:outline-2 focus-visible:outline-offset-2"
                            x-bind:class="'bg-danger-color hover:bg-danger-color-dark focus-visible:outline-danger-color'">
                            <span x-text="$store.ticketCancelModal.submitLabel"></span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
