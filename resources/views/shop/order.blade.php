<x-layout title="Order {{ $order->order_number }}">
    @php
        $isQuoteRequested = (string) $order->status === \App\Models\StoreOrder::STATUS_QUOTE_REQUESTED;
        $hasDelayedItems = $order->items->contains(fn ($item) => $item->remainingDelayedQuantity() > 0);
        $isDigitalOnly = (bool) $order->contains_digital && ! (bool) $order->contains_physical;
        $showDownloads = $downloadableItems->isNotEmpty() && $isPaid;
        $noPaymentRequired = (float) $order->total_amount <= 0.0001;
        $documentLinks = is_array($receiptLinks ?? null) ? $receiptLinks : [];
        $canDownloadDocuments = (bool) ($canDownloadDocuments ?? false);
        $canViewAddressDetails = (bool) ($canViewAddressDetails ?? false);
        $refundReceiptLinksByInvoiceLineId = is_array($refundReceiptLinksByInvoiceLineId ?? null) ? $refundReceiptLinksByInvoiceLineId : [];
        $awaitingFulfilmentItems = collect($awaitingFulfilmentItems ?? [])->filter(fn ($item) => is_array($item))->values();
        $deliveryGroups = collect($deliveryGroups ?? [])->filter(fn ($group) => is_array($group))->values();
        $emailDocumentsActionUrl = trim((string) ($emailDocumentsActionUrl ?? ''));
        $hasDocumentLinks = trim((string) ($invoicePdfUrl ?? '')) !== '' || $documentLinks !== [];
        $showDocumentsCard = $hasDocumentLinks || $emailDocumentsActionUrl !== '';
        $orderItems = collect($orderItems ?? $order->items)->values();
        $pickupReadyForCollection = $order->usesPickup() && $order->isReadyForCollection();
        $pickupReadyForPartialCollection = $order->usesPickup() && ($order->isReadyForPartialCollection() || ($orderItems->contains(fn ($item) => (int) ($item->readyPickupQuantity() ?? 0) > 0) && ! $order->isReadyForCollection() && ! $order->isPartiallyCollected() && ! $order->isCollected()));
        $pickupPartiallyCollected = $order->usesPickup() && $order->isPartiallyCollected();
        $pickupIsCollected = $order->usesPickup() && $order->isCollected();
        $pickupHasReadyItems = $order->usesPickup() && $orderItems->contains(fn ($item) => (int) ($item->readyPickupQuantity() ?? 0) > 0);
        $awaitingSectionTitle = $order->usesPickup()
            ? ($pickupIsCollected ? 'Collected' : ($pickupPartiallyCollected ? 'Partially collected' : ($pickupReadyForPartialCollection ? 'Ready for partial collection' : ($pickupReadyForCollection ? 'Awaiting collection' : 'Preparing for collection'))))
            : 'Awaiting shipping';
        $pickupAwaitingSectionSubtitle = null;
        if ($order->usesPickup()) {
            if ($pickupIsCollected) {
                $pickupAwaitingSectionSubtitle = 'These items have been collected.';
            } elseif ($pickupPartiallyCollected) {
                $pickupAwaitingSectionSubtitle = 'Some items have been collected and the remainder are still to be collected.';
            } elseif ($pickupReadyForPartialCollection) {
                $pickupAwaitingSectionSubtitle = 'Some items are ready to collect while others are still being prepared or are expected to become available later.';
            } elseif ($pickupReadyForCollection) {
                $pickupAwaitingSectionSubtitle = 'These items are ready to collect or are expected to become available later.';
            } elseif ($pickupHasReadyItems) {
                $pickupAwaitingSectionSubtitle = 'Some items are ready to collect while others are still being prepared or are expected to become available later.';
            } else {
                $pickupAwaitingSectionSubtitle = 'These items are still being prepared or are expected to become available later.';
            }
        }
        $deliveryGroupsTitle = $order->usesPickup() ? 'Recorded collections' : 'Recorded shipments';
        $deliveryDetailsTitle = $isQuoteRequested
            ? 'Shipping details'
            : ($order->usesPickup() ? 'Collection details' : 'Shipping details');
        $deliveryLabel = $isQuoteRequested
            ? 'To be quoted'
            : ($order->contains_physical ? ($order->shipping_method ?: 'Shipping') : 'Digital order');
        $showPaymentPanel = ! $isDigitalOnly
            && ! $isPaid
            && $order->invoice instanceof \App\Models\Invoice
            && (string) $order->status !== \App\Models\StoreOrder::STATUS_CANCELLED
            && ! $isQuoteRequested;
    @endphp
    <x-mast :backRoute="$isAccountView ? 'account.order.index' : 'shop.index'" :backTitle="$isAccountView ? 'My Orders' : 'Store'">
        Order {{ $order->order_number }}
    </x-mast>

    <x-container class="mx-auto max-w-6xl py-8">
        <div class="grid gap-6 {{ $showPaymentPanel ? 'xl:grid-cols-[minmax(0,1.2fr),minmax(320px,0.8fr)]' : '' }}">
            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-sm uppercase tracking-[0.18em] text-gray-500">Order</div>
                            <h2 class="text-3xl font-bold text-gray-900">{{ $order->order_number }}</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if((string) $order->status === \App\Models\StoreOrder::STATUS_CANCELLED)
                                <span class="rounded-full bg-rose-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-rose-800">
                                    Cancelled
                                </span>
                            @elseif($isQuoteRequested)
                                <span class="rounded-full bg-sky-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-sky-800">
                                    Quote requested
                                </span>
                            @else
                                <span class="rounded-full {{ $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }} px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em]">
                                {{ $isPaid ? 'Paid' : 'Pending payment' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="{{ $isDigitalOnly ? 'mt-6 grid gap-3 md:grid-cols-3 xl:grid-cols-6' : 'mt-6 grid gap-3 md:grid-cols-2 lg:grid-cols-4' }}">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Order Status</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->statusLabel() }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Placed</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Invoice</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->invoice?->invoice_number ?? '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Delivery</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $deliveryLabel }}</div>
                        </div>
                    </div>

                    @if(!$isDigitalOnly)
                    <div class="grid gap-4 mt-4 md:grid-cols-2">
                        <div class="flex-1 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $deliveryDetailsTitle }}</div>
                            @if($canViewAddressDetails)
                                @if(!$order->usesPickup())
                                    <div class="mt-4 space-y-1 text-sm text-gray-700">
                                        @foreach($order->shippingAddressLines() as $line)
                                            <div>{{ $line }}</div>
                                        @endforeach
                                        @if(empty($order->shippingAddressLines()))
                                            <div>No shipping address recorded.</div>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <div class="mt-4 text-sm text-gray-600">
                                    Log in to view the saved address details for this order.
                                </div>
                            @endif
                        </div>

                        @if($order->contains_physical)
                            <div class="flex-1 grid gap-4">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Order summary</div>
                                    <div class="mt-4 space-y-3 text-sm text-gray-700">
                                        <div class="flex items-center justify-between gap-4">
                                            <span>Items</span>
                                            <span>${{ number_format((float) $order->subtotal_amount, 2) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-4">
                                            <span>Shipping</span>
                                            <span>{{ $isQuoteRequested ? '--' : '$'.number_format((float) $order->shipping_amount, 2) }}</span>
                                        </div>
                                        @if((float) $order->discount_amount > 0)
                                            <div class="flex items-center justify-between gap-4 text-emerald-700">
                                                <span>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</span>
                                                <span>- ${{ number_format((float) $order->discount_amount, 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-4 border-t border-gray-300 pt-4 text-xs flex items-center justify-between gap-4 text-gray-500">
                                        <span>GST included</span>
                                        <span>${{ number_format((float) $order->gst_amount, 2) }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-4">
                                        <span class="text-lg font-bold text-gray-900">Total</span>
                                        <span class="text-2xl font-bold text-gray-900">{{ $isQuoteRequested ? '--' : '$'.number_format((float) $order->total_amount, 2) }}</span>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between gap-4 border-t border-gray-200 pt-3">
                                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Outstanding</span>
                                        <span class="text-sm font-semibold text-gray-900">{{ $isQuoteRequested ? '--' : '$'.number_format((float) ($order->invoice?->outstandingAmount() ?? 0), 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif

                    @if(!$noPaymentRequired && $showDocumentsCard)
                        <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Invoices / Receipts</div>
                                    <div class="mt-1 text-sm text-gray-600">
                                        @if($canDownloadDocuments)
                                            Keep a copy of your tax invoice and payment records for this order.
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 {{ !$canDownloadDocuments && $emailDocumentsActionUrl !== '' ? '' : 'grid gap-3 md:grid-cols-2 xl:grid-cols-3' }}">
                                @if($canDownloadDocuments && trim((string) ($invoicePdfUrl ?? '')) !== '')
                                    <a
                                        href="{{ $invoicePdfUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="group rounded-2xl border border-gray-200 bg-white px-4 py-4 transition hover:border-sky-300 hover:bg-sky-50/60"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                                                <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900 group-hover:text-sky-900">Download Tax Invoice</div>
                                                <div class="mt-1 text-xs text-gray-500">PDF including any issued tax adjustment notes.</div>
                                            </div>
                                        </div>
                                    </a>
                                @endif

                                @foreach($canDownloadDocuments ? $documentLinks : [] as $receiptLink)
                                    <a
                                        href="{{ $receiptLink['download_url'] }}"
                                        class="group rounded-2xl border border-gray-200 bg-white px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/60"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900 group-hover:text-emerald-900">{{ $receiptLink['title'] }}</div>
                                                @if(trim((string) ($receiptLink['meta'] ?? '')) !== '')
                                                    <div class="mt-1 text-xs text-gray-500">{{ $receiptLink['meta'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @endforeach

                                @if(!$canDownloadDocuments && $emailDocumentsActionUrl !== '')
                                    <form method="POST" action="{{ $emailDocumentsActionUrl }}" class="rounded-2xl border border-dashed border-gray-300 bg-white/80 px-4 py-4 flex items-center gap-4">
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}" />
                                        <div class="mt-1 text-xs text-gray-500">Log in to view the invoices and receipts for this order. We can also send the tax invoice and any available receipts to the email address on this order.</div>
                                        <button
                                            type="submit"
                                            class="inline-flex whitespace-nowrap items-center justify-center rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700"
                                        >
                                            Email Documents to Order Owner
                                        </button>
                                    </form>
                                @elseif($documentLinks === [])
                                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white/80 px-4 py-4 text-xs text-gray-600">
                                        <div class="flex items-start gap-3">
                                            <span class="shrink-0 mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-700">
                                                <i class="fa-solid fa-file-circle-question text-sm" aria-hidden="true"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="mt-1 text-xs text-gray-500">
                                                    @if($noPaymentRequired)
                                                        No payment receipt was created because no payment was required for this order.
                                                    @elseif(!$isPaid)
                                                        A payment receipt will appear here after the order has been paid.
                                                    @else
                                                        Payment receipts are not available for this order yet.
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>

                @if($showDownloads && $isDigitalOnly)
                    <section class="rounded-3xl border border-emerald-300 bg-emerald-50 p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-2xl font-bold text-emerald-950">Downloads</h2>
                                <p class="mt-1 text-sm text-emerald-900">
                                    {{ $isAccountView ? 'Open your files directly from this order.' : 'Verify Email to Download. Each unlocked download link expires after 15 minutes.' }}
                                </p>
                            </div>
                            <span class="rounded-full bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">
                                {{ $downloadableItems->sum(fn ($item) => $item->downloads->count()) }} file{{ $downloadableItems->sum(fn ($item) => $item->downloads->count()) === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="mt-5 space-y-4">
                            @foreach($downloadableItems as $item)
                                <div class="rounded-2xl border border-emerald-200 bg-white px-4 py-4">
                                    <div class="font-semibold text-gray-900">{{ $item->displayTitle() }}</div>
                                    <div class="mt-3 space-y-3">
                                        @foreach($item->downloads as $download)
                                            @php
                                                $downloadTitle = $download->title ?: ($download->media?->title ?: $download->media_name);
                                            @endphp
                                            <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ $downloadTitle }}</div>
                                                    <div class="text-xs text-gray-500">{{ $download->media?->file_type ?? 'Download file' }}</div>
                                                </div>
                                                <div class="flex flex-col items-start gap-2 sm:items-end">
                                                    @unless($isAccountView)
                                                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Verify Email to Download</div>
                                                    @endunless
                                                    <x-ui.button
                                                        type="link"
                                                        href="{{ $isAccountView ? route('account.order.download', ['storeOrder' => $order, 'storeOrderItemDownload' => $download]) : route('shop.order.tracking.download', ['accessToken' => $accessToken, 'storeOrderItemDownload' => $download]) }}"
                                                        class="px-5! py-2.5! whitespace-nowrap"
                                                    >
                                                        {{ $isAccountView ? 'Download '.$downloadTitle : 'Unlock '.$downloadTitle }}
                                                    </x-ui.button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(trim((string) ($order->public_notes ?? '')) !== '')
                    <section class="rounded-3xl border border-sky-200 bg-sky-50 p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-sky-950 mb-3">Order Updates</h2>
                        <div class="text-sm leading-7 text-sky-900">{!! nl2br(e((string) $order->public_notes)) !!}</div>
                    </section>
                @endif

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Items in this order</h2>
{{--                            <p class="mt-1 text-sm text-gray-600">Everything included on this order, with pricing and any cancellation adjustments.</p>--}}
                        </div>
                    </div>
                    <div class="space-y-4">
                        @foreach($orderItems as $index => $item)
                            @php
                                $cancelledTotal = $item->cancelledQuantity();
                                $refundLinks = $cancelledTotal > 0
                                    ? collect($refundReceiptLinksByInvoiceLineId[(int) ($item->invoice_line_id ?? 0)] ?? [])
                                        ->filter(fn ($link) => is_array($link))
                                        ->values()
                                    : collect();
                            @endphp
                            <div class="rounded-2xl border border-gray-200 bg-gray-50/70 px-4 py-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-sky-200 bg-sky-100 text-lg font-bold text-sky-800 shadow-sm">
                                        {{ $index + 1 }}
                                    </div>
                                    @php
                                        $itemSku = trim((string) ($item->variant_sku ?: $item->product_sku ?: $item->variant?->sku ?: $item->product?->sku));
                                    @endphp
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-gray-900">{{ $item->displayTitle() }}</div>
                                        @if($itemSku !== '')
                                            <div class="mt-1 text-xs text-gray-500">SKU {{ $itemSku }}</div>
                                        @endif
                                        @if($item->downloads->isEmpty())
                                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                                Qty {{ $item->quantity }}
                                            </div>
                                        @endif
                                        @if($item->downloads->isNotEmpty() && ! $isDigitalOnly)
                                            <div class="mt-3">
{{--                                                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Downloads</div>--}}
                                                @if($isPaid)
                                                    <ul class="ml-4">
                                                        @foreach($item->downloads as $download)
                                                            <li class="flex items-center"><i class="fa-regular fa-file mr-2" aria-hidden="true"></i><a
                                                                href="{{ $isAccountView ? route('account.order.download', ['storeOrder' => $order, 'storeOrderItemDownload' => $download]) : route('shop.order.tracking.download', ['accessToken' => $accessToken, 'storeOrderItemDownload' => $download]) }}"
                                                                class="text-sm text-sky-600 hover:text-sky-900 hover:underline"
                                                            >

                                                                <span>{{ $download->title ?: ($download->media?->title ?: $download->media_name) }}</span>
                                                            </a>
                                                                <span class="ml-2 text-xs text-gray-500">{{ ($download->media && $download->media->size !== null) ? '- ' . \App\Helpers::bytesToString((int) $download->media->size) : '' }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <div class="mt-2 text-sm text-gray-500">Downloads unlock after payment is completed.</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900">{{ (float)$item->line_total_amount === 0.0 ? 'Free' : '$' . number_format((float) $item->line_total_amount, 2) }}</div>
                                        @if($item->line_total_amount === 0.0)
                                            <div class="text-xs text-gray-500">GST incl. ${{ number_format((float) $item->line_gst_amount, 2) }}</div>
                                        @endif
                                    </div>
                                </div>

                                @if($cancelledTotal > 0)
                                    <div class="flex justify-between">
                                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.14em]">
                                            @if($cancelledTotal > 0)
                                                <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-800">Cancelled qty {{ $cancelledTotal }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2 items-center">
                                            @if($canDownloadDocuments && $refundLinks->isNotEmpty())
                                                @foreach($refundLinks as $refundLink)
                                                    <a
                                                            href="{{ $refundLink['download_url'] }}"
                                                            class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-800 transition hover:border-rose-300 hover:bg-rose-50"
                                                    >
                                                        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                                                        <span>{{ $refundLink['title'] }}</span>
                                                    </a>
                                                @endforeach
                                            @elseif($refundLinks->isNotEmpty())
                                                <div class="text-xs text-gray-500">Log in to view refund receipts</div>
                                           @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                @if($order->contains_physical && $deliveryGroups->isNotEmpty())
                    <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">{{ $deliveryGroupsTitle }}</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $order->usesPickup()
                                        ? 'Collections that have already been recorded for this order.'
                                        : 'Shipments that have already been sent, including courier and tracking details.' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            @foreach($deliveryGroups as $deliveryGroup)
                                <div class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-4">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="flex flex-col">
                                            <div class="font-semibold text-emerald-950">{{ $deliveryGroup['label'] }}</div>
                                            @if(trim((string) ($deliveryGroup['meta'] ?? '')) !== '')
                                                <div class="text-sm text-emerald-900">{{ $deliveryGroup['meta'] }}</div>
                                            @endif
                                            @if(trim((string) ($deliveryGroup['arrival_detail'] ?? '')) !== '')
                                                <div class="text-sm text-emerald-900">{{ $deliveryGroup['arrival_detail'] }}</div>
                                            @endif
                                            @if($order->usesPickup())
                                                <div class="text-sm text-emerald-900">Collection recorded.</div>
                                                @if(trim((string) ($deliveryGroup['notes'] ?? '')) !== '')
                                                    <div class="text-sm text-emerald-900">{{ $deliveryGroup['notes'] }}</div>
                                                @endif
                                            @else
                                                @if(trim((string) ($deliveryGroup['tracking_number'] ?? '')) !== '')
                                                    @if(trim((string) ($deliveryGroup['tracking_url'] ?? '')) !== '')
                                                        <div class="text-sm text-emerald-900">Tracking: <a
                                                                    href="{{ $deliveryGroup['tracking_url'] }}"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="text-sky-700 transition hover:text-sky-900"
                                                            >{{ $deliveryGroup['tracking_number'] }}</a></div>
                                                    @else
                                                        <div class="text-sm text-emerald-900">Tracking: {{ $deliveryGroup['tracking_number'] }}</div>
                                                    @endif
                                                @else
                                                    <div class="text-sm text-emerald-900">Tracking not available</div>
                                                @endif
                                                @if(trim((string) ($deliveryGroup['notes'] ?? '')) !== '')
                                                    <div class="text-sm text-emerald-900">{{ $deliveryGroup['notes'] }}</div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">
                                            {{ $order->usesPickup() ? 'Items in this collection' : 'Items in this delivery' }}
                                        </div>
                                        <ul class="mt-1 space-y-2">
                                            @foreach(($deliveryGroup['items'] ?? []) as $deliveryItem)
                                                <li class="flex items-center gap-3 text-sm text-gray-800">
                                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-white text-sm font-bold text-emerald-900">
                                                        {{ (int) ($deliveryItem['number'] ?? 0) }}
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        @php
                                                            $itemSku = $deliveryItem['sku'] ?? '';
                                                        @endphp
                                                        <div class="font-medium text-gray-900">{{ $deliveryItem['title'] ?? 'Item' }}  <span class="ml-2 font-normal text-xs text-gray-500">{{ $itemSku !== '' ? '['.$itemSku.']' : '' }}</span></div>
                                                    </div>
                                                    <span class="font-semibold whitespace-nowrap">x {{ (int) ($deliveryItem['quantity'] ?? 0) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($order->contains_physical && ! $isQuoteRequested && ! $pickupIsCollected && $awaitingFulfilmentItems->isNotEmpty())
                    <section class="rounded-3xl border border-amber-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">{{ $awaitingSectionTitle }}</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    @if($order->usesPickup())
                                        {{ $pickupAwaitingSectionSubtitle }}
                                    @else
                                        These items have not shipped yet. Expected shipping dates are shown where available.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach($awaitingFulfilmentItems as $awaitingItem)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-4">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-amber-300 bg-white text-lg font-bold text-amber-900 shadow-sm">
                                            {{ (int) ($awaitingItem['number'] ?? 0) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @php
                                            $itemSku = $awaitingItem['sku'] ?? '';
                                            @endphp
                                            <div class="font-semibold text-gray-900">{{ $awaitingItem['title'] }} <span class="ml-2 font-normal text-xs text-gray-500">{{ $itemSku !== '' ? '['.$itemSku.']' : '' }}</span></div>
                                            @if(trim((string) ($awaitingItem['detail'] ?? '')) !== '')
                                                <div class="mt-1 text-sm text-amber-900">{{ $awaitingItem['detail'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($showDownloads && ! $isDigitalOnly)
                    <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Downloads</h2>
                        @unless($isAccountView)
                            <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                                Guest downloads require the order email first. Each unlocked download link expires after 15 minutes.
                            </div>
                        @endunless
                        <div class="space-y-4">
                            @foreach($downloadableItems as $item)
                                <div class="rounded-2xl border border-gray-200 p-4">
                                    <div class="font-semibold text-gray-900 mb-3">{{ $item->displayTitle() }}</div>
                                    <div class="space-y-3">
                                        @foreach($item->downloads as $download)
                                            <div class="flex items-center justify-between gap-4 rounded-2xl bg-gray-50 px-4 py-3">
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ $download->title ?: ($download->media?->title ?: $download->media_name) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $download->media?->file_type ?? 'Download file' }}</div>
                                                </div>
                                                <x-ui.button
                                                    type="link"
                                                    href="{{ $isAccountView ? route('account.order.download', ['storeOrder' => $order, 'storeOrderItemDownload' => $download]) : route('shop.order.tracking.download', ['accessToken' => $accessToken, 'storeOrderItemDownload' => $download]) }}"
                                                    class="px-5!"
                                                >
                                                    {{ $isAccountView ? 'Download' : 'Verify Email to Download' }}
                                                </x-ui.button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

            </div>

            @if($showPaymentPanel)
            <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Complete Payment</h2>

                    @if(!$squareEnabled)
                        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            Card payments are currently unavailable.
                        </div>
                    @else
                        <form method="POST" action="{{ $payActionUrl }}" x-data="shopOrderPayment({
                                    squareEnabled: @js($squareEnabled),
                                    squareApplicationId: @js($squareApplicationId),
                                    squareLocationId: @js($squareLocationId),
                                    squareEnvironment: @js($squareEnvironment),
                                  })" x-on:submit.prevent="submitForm($event)">
                            @csrf

                            <div x-init="initSquareCard()">
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="block text-sm">Card Details</label>
                                    <a href="https://squareup.com/au/en" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                        Secure payment by Square
                                    </a>
                                </div>
                                <div class="relative">
                                    <div x-ref="squareCardContainer" class="min-h-22 bg-white transition" x-bind:class="{ 'pointer-events-none opacity-60': isSubmitting || isCardLoading }"></div>
                                    <div x-show="isCardLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-white/80">
                                        <img src="{{ asset('loading.gif') }}" alt="Loading card form" width="56" height="56" />
                                    </div>
                                </div>
                                <input type="hidden" name="source_id" x-model="sourceId" x-ref="sourceIdInput">
                                <div x-show="errorMessage" class="mt-2 text-xs text-red-600" x-text="errorMessage"></div>
                                @error('source_id')
                                    <div class="mt-2 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-5 text-right">
                                <x-ui.button type="submit" x-bind:disabled="isSubmitting || isCardLoading" class="w-full sm:w-auto">
                                    <span x-show="!isSubmitting">Pay ${{ number_format((float) ($order->invoice?->outstandingAmount() ?? 0), 2) }}</span>
                                    <span x-show="isSubmitting" x-cloak>Processing...</span>
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                </section>
            </aside>
            @endif
        </div>
    </x-container>
</x-layout>

@if($squareEnabled)
<script src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
@endif
<script>
    function shopOrderPayment(config) {
        return {
            squareEnabled: Boolean(config.squareEnabled),
            squareApplicationId: config.squareApplicationId || '',
            squareLocationId: config.squareLocationId || '',
            squareCard: null,
            sourceId: '',
            errorMessage: '',
            isSubmitting: false,
            isCardLoading: false,

            canUseCreditCard() {
                return this.squareEnabled && this.squareApplicationId !== '' && this.squareLocationId !== '';
            },

            async initSquareCard() {
                if (!this.canUseCreditCard()) {
                    this.errorMessage = 'Credit card payments are not available right now.';
                    return false;
                }

                this.isCardLoading = true;
                const ready = await this.waitForSquareSdk();
                if (!ready) {
                    this.errorMessage = 'Square SDK did not load.';
                    this.isCardLoading = false;
                    return false;
                }

                if (this.squareCard) {
                    this.isCardLoading = false;
                    return true;
                }

                try {
                    const payments = window.Square.payments(this.squareApplicationId, this.squareLocationId);
                    this.squareCard = await payments.card();
                    await this.squareCard.attach(this.$refs.squareCardContainer);
                    this.isCardLoading = false;
                    return true;
                } catch (e) {
                    this.errorMessage = e?.message || 'Unable to load card payment form.';
                    this.isCardLoading = false;
                    return false;
                }
            },

            async submitForm(event) {
                if (this.isSubmitting) {
                    return;
                }
                this.errorMessage = '';
                this.isSubmitting = true;
                const ready = await this.initSquareCard();
                if (!ready) {
                    this.isSubmitting = false;
                    return;
                }

                const result = await this.squareCard.tokenize();

                if (result.status !== 'OK') {
                    this.errorMessage = result.errors?.[0]?.message || 'Unable to tokenize card.';
                    this.isSubmitting = false;
                    return;
                }

                this.sourceId = result.token;
                const form = event.target.tagName === 'FORM' ? event.target : event.target.closest('form');
                if (!form) {
                    this.errorMessage = 'Unable to submit payment form.';
                    this.isSubmitting = false;
                    return;
                }

                const sourceIdInput = form.querySelector('input[name="source_id"]');
                if (sourceIdInput) {
                    sourceIdInput.value = this.sourceId || '';
                }

                form.submit();
            },

            async waitForSquareSdk() {
                if (window.Square?.payments) {
                    return true;
                }

                for (let attempt = 0; attempt < 50; attempt++) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                    if (window.Square?.payments) {
                        return true;
                    }
                }

                return false;
            },
        }
    }
</script>
