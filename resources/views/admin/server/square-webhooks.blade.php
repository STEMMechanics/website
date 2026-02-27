<x-layout>
    <x-mast>Square Webhooks</x-mast>

    <x-container x-data="{
        ignoreOpen: false,
        ignoreAction: '',
        ignoreSquarePaymentId: '',
        reasonCode: '',
        reasonOther: '',
        openIgnore(actionUrl, squarePaymentId) {
            this.ignoreAction = actionUrl;
            this.ignoreSquarePaymentId = squarePaymentId || '';
            this.reasonCode = '';
            this.reasonOther = '';
            this.ignoreOpen = true;
        },
        closeIgnore() {
            this.ignoreOpen = false;
        },
        canSubmitIgnore() {
            if (!this.reasonCode) return false;
            if (this.reasonCode !== 'other') return true;
            return this.reasonOther.trim().length > 0;
        }
    }">
        <x-ui.toolbar>
            <x-slot:left>
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
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot>
        </x-ui.toolbar>

        @if($events->isEmpty())
            <x-none-found item="square webhook events" search="{{ request()->get('search') }}" />
        @else
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif
            @php
                $groupedEvents = $events->groupBy(function ($event) {
                    $squarePaymentId = trim((string) ($event->square_payment_id ?? ''));

                    return $squarePaymentId !== '' ? 'pid:'.$squarePaymentId : 'event:'.$event->id;
                })->values();
            @endphp
            <div class="text-right mb-4">
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
            </div>
            <x-ui.table>
                <x-slot:header>
                    <th>ID</th>
                    <th>Details</th>
                    <th class="hidden md:table-cell">Amount</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden lg:table-cell">Square Payment ID</th>
                    <th class="hidden md:table-cell">Payment</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($groupedEvents as $eventGroup)
                        @php
                            /** @var \App\Models\SquareWebhookEvent $event */
                            $event = $eventGroup->first();
                            $childEvents = $eventGroup->slice(1)->values();
                            $groupCount = $eventGroup->count();
                            $squarePaymentId = trim((string) ($event->square_payment_id ?? ''));
                            $isIgnored = (bool) ($event->is_ignored ?? false);
                            $amountCents = is_numeric($event->amount_cents ?? null) ? (int) $event->amount_cents : null;
                            $amountCurrency = trim((string) ($event->amount_currency ?? ''));
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                #{{ $event->id }}
                                @if($groupCount > 1)
                                    <div class="text-xs text-gray-600 mt-1">{{ $groupCount }} related events</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $event->processed_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                <div class="md:hidden text-xs text-gray-600 mt-1">{{ $event->event_type ?: '-' }}</div>
                                @if($squarePaymentId !== '')
                                    <div class="lg:hidden text-xs font-mono text-gray-600">{{ $squarePaymentId }}</div>
                                @endif
                                @if($isIgnored)
                                    <div class="text-xs mt-1 text-amber-700">Ignored Square payment</div>
                                @endif
                                @if($event->customerPayment)
                                    <div class="md:hidden text-xs mt-1">
                                        Payment:
                                        <a href="{{ route('admin.payment.edit', $event->customerPayment) }}" class="text-primary-color hover:underline">#{{ $event->customerPayment->id }}</a>
                                    </div>
                                @elseif($event->payment_id)
                                    <div class="md:hidden text-xs mt-1">Payment: #{{ $event->payment_id }}</div>
                                @endif
                            </td>
                            <td class="hidden md:table-cell">
                                @if($amountCents !== null)
                                    {{ $amountCents < 0 ? '-' : '' }}${{ number_format(abs($amountCents) / 100, 2) }}{{ $amountCurrency !== '' ? ' '.$amountCurrency : '' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="hidden md:table-cell">
                                {{ $event->event_type ?: '-' }}
                            </td>
                            <td class="hidden lg:table-cell text-xs font-mono">{{ $squarePaymentId !== '' ? $squarePaymentId : '-' }}</td>
                            <td class="hidden md:table-cell">
                                @if($isIgnored)
                                    <span class="text-amber-700">Ignored</span>
                                @elseif($event->customerPayment)
                                    <a href="{{ route('admin.payment.edit', $event->customerPayment) }}" class="text-primary-color hover:underline">#{{ $event->customerPayment->id }}</a>
                                @elseif($event->payment_id)
                                    #{{ $event->payment_id }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    @if($squarePaymentId !== '')
                                        @if($isIgnored)
                                            <form method="POST" action="{{ route('admin.server.square-webhooks.unignore', $event) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="hover:text-primary-color" title="Remove ignore rule">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        @else
                                            <button
                                                type="button"
                                                class="hover:text-primary-color"
                                                title="Ignore this Square payment ID"
                                                x-on:click.prevent="openIgnore(@js(route('admin.server.square-webhooks.ignore', $event)), @js($squarePaymentId))">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        @endif
                                    @endif
                                    <a href="{{ route('admin.server.square-webhooks.show', $event) }}" class="hover:text-primary-color" title="View event">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @foreach($childEvents as $childEvent)
                            @php
                                $childAmountCents = is_numeric($childEvent->amount_cents ?? null) ? (int) $childEvent->amount_cents : null;
                                $childAmountCurrency = trim((string) ($childEvent->amount_currency ?? ''));
                            @endphp
                            <tr class="bg-gray-50">
                                <td class="whitespace-nowrap">
                                    <span class="text-gray-600 pl-5 inline-block">↳ #{{ $childEvent->id }}</span>
                                </td>
                                <td>
                                    <div>{{ $childEvent->processed_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                    <div class="text-xs text-gray-600 mt-1">{{ $childEvent->event_type ?: '-' }}</div>
                                    <div class="text-xs font-mono text-gray-600">{{ $childEvent->event_id ?: '-' }}</div>
                                </td>
                                <td class="hidden md:table-cell">
                                    @if($childAmountCents !== null)
                                        {{ $childAmountCents < 0 ? '-' : '' }}${{ number_format(abs($childAmountCents) / 100, 2) }}{{ $childAmountCurrency !== '' ? ' '.$childAmountCurrency : '' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">{{ $childEvent->event_type ?: '-' }}</td>
                                <td class="hidden lg:table-cell text-xs font-mono">{{ $squarePaymentId !== '' ? $squarePaymentId : '-' }}</td>
                                <td class="hidden md:table-cell">
                                    @if((bool) ($childEvent->is_ignored ?? false))
                                        <span class="text-amber-700">Ignored</span>
                                    @elseif($childEvent->customerPayment)
                                        <a href="{{ route('admin.payment.edit', $childEvent->customerPayment) }}" class="text-primary-color hover:underline">#{{ $childEvent->customerPayment->id }}</a>
                                    @elseif($childEvent->payment_id)
                                        #{{ $childEvent->payment_id }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <div class="flex justify-center gap-3 whitespace-nowrap">
                                        <a href="{{ route('admin.server.square-webhooks.show', $childEvent) }}" class="hover:text-primary-color" title="View event">
                                            <i class="fa-regular fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div
                x-show="ignoreOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:keydown.escape.window="closeIgnore()">
                <div class="w-full max-w-lg rounded-lg border border-gray-200 bg-white p-4 shadow-xl">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-bold">Ignore Square Payment</h3>
                        <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="closeIgnore()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-700 mb-3">
                        This will prevent future syncs from creating/linking payment records for
                        <span class="font-mono" x-text="ignoreSquarePaymentId || '-'"></span>.
                    </p>
                    <form method="POST" x-bind:action="ignoreAction">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-sm pl-1">Reason</label>
                            <select name="reason_code" class="bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="reasonCode">
                                <option value="" selected>Select reason</option>
                                @foreach(($ignoreReasonOptions ?? []) as $reasonKey => $reasonLabel)
                                    <option value="{{ $reasonKey }}">{{ $reasonLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4" x-show="reasonCode === 'other'" x-cloak>
                            <label class="block text-sm pl-1">Other Reason</label>
                            <textarea name="reason_other" x-model="reasonOther" rows="3" class="bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300"></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button"
                                    class="border border-gray-300 bg-white text-gray-800 whitespace-nowrap text-center justify-center rounded-md px-6 py-2 text-sm font-semibold leading-6 shadow-sm hover:bg-gray-50 transition"
                                    x-on:click.prevent="closeIgnore()">Cancel</button>
                            <button type="submit"
                                    class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white whitespace-nowrap text-center justify-center rounded-md px-8 py-2 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition disabled:cursor-not-allowed disabled:opacity-50"
                                    x-bind:disabled="!canSubmitIgnore()">
                                Ignore Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-container>
</x-layout>
