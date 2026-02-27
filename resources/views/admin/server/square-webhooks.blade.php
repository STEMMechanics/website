<x-layout>
    <x-mast>Square Webhooks</x-mast>

    <x-container>
        <div class="flex flex-col lg:flex-row my-4 items-end gap-3 lg:gap-4">
            <form method="POST" action="{{ route('admin.server.square-webhooks.sync') }}" class="w-full lg:w-auto">
                @csrf
                <input type="hidden" name="only_unlinked" value="1">
                @if(request()->filled('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if(request()->filled('event_type'))
                    <input type="hidden" name="event_type" value="{{ request('event_type') }}">
                @endif
                <x-ui.button type="submit" color="outline">Sync Stored Events</x-ui.button>
            </form>
            <form method="GET" action="{{ route('admin.server.square-webhooks') }}" class="w-full lg:flex-1 flex flex-col sm:flex-row items-end gap-3 sm:gap-4">
                <div class="w-full sm:w-64">
                    <x-ui.select label="Event Type" name="event_type">
                        <option value="">All event types</option>
                        @foreach($eventTypes as $eventType)
                            <option value="{{ $eventType }}" {{ request('event_type') === $eventType ? 'selected' : '' }}>{{ $eventType }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="w-full sm:w-40 mb-4">
                    <x-ui.button type="submit" color="outline">Filter</x-ui.button>
                </div>
            </form>
            <div class="w-full lg:flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($events->isEmpty())
            <x-none-found item="square webhook events" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>ID</th>
                    <th>Details</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden lg:table-cell">Event ID</th>
                    <th class="hidden md:table-cell">Payment</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($events as $event)
                        <tr>
                            <td class="whitespace-nowrap">{{ $event->id }}</td>
                            <td>
                                <div>{{ $event->processed_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                <div class="md:hidden text-xs text-gray-600 mt-1">{{ $event->event_type ?: '-' }}</div>
                                <div class="lg:hidden text-xs font-mono text-gray-600">{{ $event->event_id }}</div>
                                @if($event->customerPayment)
                                    <div class="md:hidden text-xs mt-1">
                                        Payment:
                                        <a href="{{ route('admin.payment.edit', $event->customerPayment) }}" class="text-primary-color hover:underline">#{{ $event->customerPayment->id }}</a>
                                    </div>
                                @elseif($event->payment_id)
                                    <div class="md:hidden text-xs mt-1">Payment: #{{ $event->payment_id }}</div>
                                @endif
                            </td>
                            <td class="hidden md:table-cell">{{ $event->event_type ?: '-' }}</td>
                            <td class="hidden lg:table-cell text-xs font-mono">{{ $event->event_id }}</td>
                            <td class="hidden md:table-cell">
                                @if($event->customerPayment)
                                    <a href="{{ route('admin.payment.edit', $event->customerPayment) }}" class="text-primary-color hover:underline">#{{ $event->customerPayment->id }}</a>
                                @elseif($event->payment_id)
                                    #{{ $event->payment_id }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    <a href="{{ route('admin.server.square-webhooks.show', $event) }}" class="hover:text-primary-color" title="View event">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $events->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
