<x-layout>
    <x-mast>Tickets</x-mast>

    <x-container x-data="{
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
                            labelClass="text-sm pt-0"
                            onchange="this.form.submit()"
                        />
                        <x-ui.checkbox
                            name="group_by_workshop"
                            value="1"
                            label="Group by workshop"
                            :checked="!empty($groupByWorkshop)"
                            :noWrapper="true"
                            :inline="true"
                            labelClass="text-sm pt-0"
                            onchange="this.form.submit()"
                        />
                    </div>
                </x-slot:left>
                <x-slot:right>
                    <div class="flex relative">
                        <input
                            class="bg-white flex-grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300"
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
                            $invoiceNumber = (string) ($ticket->invoice?->invoice_number ?? '-');
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
                                <div class="lg:hidden text-xs text-gray-600">{{ $invoiceNumber !== '-' ? 'Invoice #'.$invoiceNumber : '-' }}</div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div>{{ $attendee }}</div>
                                <div class="text-xs text-gray-600">{{ $ticket->email ?: '-' }}</div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $statusText }}</td>
                            <td class="hidden lg:table-cell">{{ $invoiceNumber !== '-' ? 'Invoice #'.$invoiceNumber : '-' }}</td>
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
                                        <form method="POST" action="{{ route('admin.ticket.cancel', $ticket) }}">
                                            @csrf
                                            <input type="hidden" name="process_square_refund" value="0">
                                            <button
                                                type="button"
                                                class="hover:text-amber-600"
                                                title="{{ $hasAnyPayment ? 'Cancel ticket (leave credit on account)' : 'Cancel ticket' }}"
                                                x-on:click="confirmSubmit($event, @js($hasAnyPayment ? 'Cancel this ticket and issue a tax adjustment note? This leaves credit on the customer account.' : 'Cancel this ticket?'), 'Cancel Ticket')"
                                            >
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
                                                    x-on:click="confirmSubmit($event, @js('Cancel this ticket and attempt refund now? A tax adjustment note will be created first.'.($hasSquarePayment ? '' : ' No Square payment is linked; refund may require manual processing.')), 'Cancel and Refund')"
                                                >
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
                        @php($previousWorkshopKey = $workshopKey)
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $tickets->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
