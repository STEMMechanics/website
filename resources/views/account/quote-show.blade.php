<x-layout>
    @if($isMagicAccess ?? false)
        <x-mast>Quote {{ $quote->quote_number }}</x-mast>
    @else
        <x-mast backRoute="account.quote.index" backTitle="My Quotes">Quote {{ $quote->quote_number }}</x-mast>
    @endif

<x-container class="mt-4 space-y-5">
    @php
        $quoteDueDate = $quoteDueDate ?? ($quote->expiresAt()?->format('M j, Y') ?? '-');
        $quoteHasExpired = (bool) ($quoteHasExpired ?? $quote->isExpired());
        $quoteStatusLabel = $quoteHasExpired && (string) $quote->status === \App\Models\Quote::STATUS_OPEN
            ? 'Expired'
            : $quote->statusLabel();
        $showSidebarContent = (auth()->check() && auth()->id() === $quote->user_id)
            || trim((string) ($quote->notes ?? '')) !== '';
    @endphp
    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
        <div class="grid gap-6 p-6 {{ $showSidebarContent ? 'lg:grid-cols-[minmax(0,1.4fr)]' : '' }}">
            <div class="space-y-5">
                <div class="flex flex-col flex-wrap items-start justify-between gap-4">
                    <div class="flex align-top justify-between w-full border-b pb-3 border-gray-200">
                        <div class="flex flex-wrap gap-12">
                            <div class="text-center">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</div>
                                <div class="mt-1 text-base font-semibold text-gray-950">{{ $quoteStatusLabel }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Quote date</div>
                                <div class="mt-1 text-base font-semibold text-gray-950">{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Due date</div>
                                <div class="mt-1 text-base font-semibold text-gray-950">{{ $quoteDueDate }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total <span class="text-xs font-normal normal-case">(inc GST)</span></div>
                                <div class="mt-1 text-base font-semibold text-gray-950">${{ number_format((float) $quote->total_amount, 2) }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="flex gap-2">
                                @auth
                                    @if(auth()->user()?->id === $quote->user_id)
                                        <div class="text-4xl text-gray-700">
                                            <a href="{{ route('account.quote.pdf', $quote) }}" class="text-primary-color hover:underline" target="_blank"><i class="fa-regular fa-file-pdf"></i></a>
                                        </div>
                                    @endif
                                @endauth

                                <div class="whitespace-nowrap rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-lg font-semibold uppercase tracking-wide text-gray-600">
                                    Quote {{ $quote->quote_number }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full justify-between my-3 gap-3">
                        <div class="flex-1">
                        @if(trim((string) ($quote->title ?? '')) !== '')
                            <div class="text-3xl font-semibold tracking-tight text-gray-950">
                                {{ $quote->title }}
                            </div>
                        @endif
                        @if(trim((string) ($quote->description ?? '')) !== '')
                            <div class="mt-3 max-w-3xl text-sm leading-6 text-gray-600">{!! nl2br(e((string) $quote->description)) !!}</div>
                        @endif
                        </div>
                        @if(trim((string) ($quote->notes ?? '')) !== '')
                            <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-4 min-w-xs">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</div>
                                <div class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">AABB{{ $quote->notes }}</div>
                            </div>
                        @endif
                    </div>


                    <div class="w-full">
                        @if($quote->canCustomerRespond())
                            <div class="flex flex-wrap gap-3">
                                <form method="POST" action="{{ $acceptUrl }}">
                                    @csrf
                                    <x-ui.button type="submit">Accept Quote</x-ui.button>
                                </form>
                                @if(!($isMagicAccess ?? false) && ($canAcceptAndPay ?? false))
                                    <form method="POST" action="{{ $acceptUrl }}">
                                        @csrf
                                        <input type="hidden" name="accept_and_pay" value="1">
                                        <x-ui.button type="submit" color="success">Accept &amp; Pay Invoice</x-ui.button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ $cancelUrl }}">
                                    @csrf
                                    <x-ui.button type="submit" color="danger">Cancel Quote</x-ui.button>
                                </form>
                            </div>
                        @elseif((string) $quote->status === \App\Models\Quote::STATUS_ACCEPTED)
                            <div class="text-sm text-emerald-700">
                                <i class="fa-solid fa-circle-check mr-2"></i>This quote has been accepted and an invoice
                                @if(($linkedInvoice ?? null) !== null)
                                    <a href="{{ $linkedInvoiceUrl }}" class="font-semibold underline hover:text-emerald-900">#{{ $linkedInvoice->invoice_number }}</a>
                                @else
                                    #-
                                @endif
                                has been generated.
                                @if(($linkedInvoice ?? null) !== null)
                                    @if((float) ($linkedInvoiceOutstanding ?? 0) > 0.0001)
                                        There is currently <span class="font-semibold">${{ number_format((float) $linkedInvoiceOutstanding, 2) }}</span> outstanding.
                                    @else
                                        There is currently no outstanding balance.
                                    @endif
                                    <div class="flex mt-3 gap-3">
                                        <a href="{{ $linkedInvoiceUrl }}" class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-gray-100">View Invoice</a>
                                        @if(!empty($linkedInvoicePayUrl))
                                            <a href="{{ $linkedInvoicePayUrl }}" class="rounded-md bg-primary-color px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-color-dark">Pay Invoice</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @elseif((string) $quote->status === \App\Models\Quote::STATUS_CANCELLED)
                            <div class="text-sm text-rose-700">
                                <i class="fa-solid fa-circle-xmark mr-2"></i>This quote has been cancelled and is no longer available. Please contact us if you need to discuss it.
                            </div>
                        @elseif($quoteHasExpired || (string) $quote->status === \App\Models\Quote::STATUS_EXPIRED)
                            <div class="text-sm text-amber-700">
                                <i class="fa-solid fa-triangle-exclamation mr-2"></i>This quote has expired and can no longer be accepted. Please contact us to review your options.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="border-b border-gray-200 px-6 py-4">
                <div class="text-lg font-semibold text-gray-950">Quote Items</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-600">
                            <th class="px-6 py-3 font-medium">Item</th>
                            <th class="px-6 py-3 font-medium text-right">Qty</th>
                            <th class="px-6 py-3 font-medium text-right">Unit <span class="text-xs font-normal">(ex GST)</span></th>
                            <th class="px-6 py-3 font-medium text-right">Subtotal <span class="text-xs font-normal">(ex GST)</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach((array) ($quote->line_items ?? []) as $item)
                            @php
                                $notes = trim((string) ($item['notes'] ?? ''));
                            @endphp
                            <tr>
                                <td class="px-6 py-4 align-top">
                                    <div class="font-medium text-gray-950">{{ (string) ($item['description'] ?? 'Item') }}</div>
                                    @if($notes !== '')
                                        <div class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-500">{{ $notes }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right align-top text-gray-700">{{ rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 2, '.', ''), '0'), '.') }}</td>
                                <td class="px-6 py-4 text-right align-top text-gray-700">${{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                                <td class="px-6 py-4 text-right align-top font-medium text-gray-950">${{ number_format((float) ($item['line_total'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 text-sm">
                        <tr>
                            <td colspan="3" class="px-6 py-2 text-right font-medium text-gray-600">Subtotal <span class="text-xs font-normal">(ex GST)</span></td>
                            <td class="px-6 py-2 text-right font-semibold text-gray-950">${{ number_format((float) $quote->subtotal_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-2 text-right font-medium text-gray-600">GST</td>
                            <td class="px-6 py-2 text-right font-semibold text-gray-950">${{ number_format((float) $quote->gst_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-2 text-right font-semibold text-gray-900 text-lg">Total <span class="text-xs font-normal">(inc GST)</span></td>
                            <td class="px-6 py-2 text-right font-semibold text-gray-950 text-lg">${{ number_format((float) $quote->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    </x-container>
</x-layout>
