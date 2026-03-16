<x-layout>
    @php
        $shippingBreakdown = $order->shippingBreakdown();
        $itemActionMeta = $order->items
            ->mapWithKeys(function ($item) use ($order) {
                $remainingAvailable = $item->remainingAvailableQuantity();
                $remainingDelayed = $item->remainingDelayedQuantity();

                return [
                    (string) $item->id => [
                        'id' => (int) $item->id,
                        'title' => $item->displayTitle(),
                        'ordered_quantity' => max(0, (int) $item->quantity),
                        'cancelled_quantity' => $item->cancelledQuantity(),
                        'tracked_quantity' => $item->trackedQuantity(),
                        'reserved_quantity' => $item->reservedInventory(),
                        'remaining_available' => $remainingAvailable,
                        'remaining_delayed' => $remainingDelayed,
                        'can_track' => $order->contains_physical && ! $order->usesPickup() && ! $item->isDigital() && ($remainingAvailable > 0 || $remainingDelayed > 0),
                    ],
                ];
            })
            ->all();
        $initialPendingActions = [];
        $pendingActionsOld = old('item_actions_json');
        if (is_string($pendingActionsOld) && trim($pendingActionsOld) !== '') {
            try {
                $decodedPendingActions = json_decode($pendingActionsOld, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decodedPendingActions)) {
                    $initialPendingActions = $decodedPendingActions;
                }
            } catch (\JsonException) {
                $initialPendingActions = [];
            }
        }
        $orderEditorConfig = \Illuminate\Support\Js::from([
            'items' => $itemActionMeta,
            'initialActions' => $initialPendingActions,
        ]);
    @endphp
    <script>
        window.shopAdminOrderEditor = function (config) {
            const normalizedInitialActions = Array.isArray(config?.initialActions) ? config.initialActions : [];

            return {
                items: config?.items || {},
                pendingActions: normalizedInitialActions.map((action, index) => ({
                    client_id: `restored-${index + 1}`,
                    type: String(action?.type || ''),
                    item_id: Number(action?.item_id || 0),
                    available_quantity: Number(action?.available_quantity || 0),
                    delayed_quantity: Number(action?.delayed_quantity || 0),
                    reason: String(action?.reason || ''),
                    shipment_type: String(action?.shipment_type || ''),
                    quantity: Number(action?.quantity || 0),
                    carrier: String(action?.carrier || ''),
                    tracking_number: String(action?.tracking_number || ''),
                    tracking_url: String(action?.tracking_url || ''),
                    notes: String(action?.notes || ''),
                    dispatched_at: String(action?.dispatched_at || ''),
                })),
                itemUi: {},
                nextClientId: normalizedInitialActions.length + 1,

                init() {
                    Object.keys(this.items || {}).forEach((itemId) => {
                        this.ensureItemUi(itemId);
                    });
                },

                ensureItemUi(itemId) {
                    const key = String(itemId);
                    if (!this.itemUi[key]) {
                        this.itemUi[key] = {
                            cancelOpen: false,
                            trackingOpen: false,
                            cancelError: '',
                            trackingError: '',
                        };
                    }

                    return this.itemUi[key];
                },

                itemMeta(itemId) {
                    return this.items?.[String(itemId)] || {};
                },

                openCancel(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.cancelError = '';
                    ui.cancelOpen = true;
                },

                openTracking(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.trackingError = '';
                    ui.trackingOpen = true;
                },

                closeCancel(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.cancelOpen = false;
                },

                closeTracking(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.trackingOpen = false;
                },

                pendingCancelAvailable(itemId) {
                    return this.pendingActions
                        .filter((action) => action.type === 'cancel' && Number(action.item_id) === Number(itemId))
                        .reduce((carry, action) => carry + Number(action.available_quantity || 0), 0);
                },

                pendingCancelDelayed(itemId) {
                    return this.pendingActions
                        .filter((action) => action.type === 'cancel' && Number(action.item_id) === Number(itemId))
                        .reduce((carry, action) => carry + Number(action.delayed_quantity || 0), 0);
                },

                pendingTrackedAvailable(itemId) {
                    return this.pendingActions
                        .filter((action) => action.type === 'tracking' && Number(action.item_id) === Number(itemId) && String(action.shipment_type) === 'available')
                        .reduce((carry, action) => carry + Number(action.quantity || 0), 0);
                },

                pendingTrackedDelayed(itemId) {
                    return this.pendingActions
                        .filter((action) => action.type === 'tracking' && Number(action.item_id) === Number(itemId) && String(action.shipment_type) === 'delayed')
                        .reduce((carry, action) => carry + Number(action.quantity || 0), 0);
                },

                remainingAvailable(itemId) {
                    const base = Number(this.items?.[String(itemId)]?.remaining_available || 0);
                    return Math.max(0, base - this.pendingCancelAvailable(itemId) - this.pendingTrackedAvailable(itemId));
                },

                remainingDelayed(itemId) {
                    const base = Number(this.items?.[String(itemId)]?.remaining_delayed || 0);
                    return Math.max(0, base - this.pendingCancelDelayed(itemId) - this.pendingTrackedDelayed(itemId));
                },

                reservedQuantity(itemId) {
                    const base = Number(this.itemMeta(itemId).reserved_quantity || 0);
                    return Math.max(0, base - this.pendingCancelAvailable(itemId) - this.pendingTrackedAvailable(itemId));
                },

                cancelledQuantity(itemId) {
                    const base = Number(this.itemMeta(itemId).cancelled_quantity || 0);
                    return base + this.pendingCancelAvailable(itemId) + this.pendingCancelDelayed(itemId);
                },

                dispatchedQuantity(itemId) {
                    const base = Number(this.itemMeta(itemId).tracked_quantity || 0);
                    return base + this.pendingTrackedAvailable(itemId) + this.pendingTrackedDelayed(itemId);
                },

                openQuantity(itemId) {
                    return this.remainingAvailable(itemId) + this.remainingDelayed(itemId);
                },

                canCancel(itemId) {
                    return this.openQuantity(itemId) > 0;
                },

                canTrack(itemId) {
                    return Boolean(this.itemMeta(itemId).can_track) && this.openQuantity(itemId) > 0;
                },

                hasPendingActions() {
                    return this.pendingActions.length > 0;
                },

                pendingCancellationCount() {
                    return this.pendingActions.filter((action) => action.type === 'cancel').length;
                },

                pendingTrackingCount() {
                    return this.pendingActions.filter((action) => action.type === 'tracking').length;
                },

                pendingActionCountForItem(itemId) {
                    return this.pendingActions.filter((action) => Number(action.item_id) === Number(itemId)).length;
                },

                pendingActionsForItem(itemId) {
                    return this.pendingActions.filter((action) => Number(action.item_id) === Number(itemId));
                },

                pendingSummaryLabel(itemId) {
                    const cancelCount = this.pendingActions.filter((action) => action.type === 'cancel' && Number(action.item_id) === Number(itemId)).length;
                    const trackingCount = this.pendingActions.filter((action) => action.type === 'tracking' && Number(action.item_id) === Number(itemId)).length;
                    const parts = [];

                    if (cancelCount > 0) {
                        parts.push(`${cancelCount} cancellation${cancelCount === 1 ? '' : 's'}`);
                    }

                    if (trackingCount > 0) {
                        parts.push(`${trackingCount} tracking entr${trackingCount === 1 ? 'y' : 'ies'}`);
                    }

                    return parts.join(' staged, ');
                },

                cancellationBreakdown(itemId, requestedQuantity) {
                    const quantity = Math.max(0, Number(requestedQuantity || 0));
                    const delayedQuantity = Math.min(quantity, this.remainingDelayed(itemId));
                    const availableQuantity = Math.max(0, quantity - delayedQuantity);

                    return {
                        quantity,
                        delayed_quantity: delayedQuantity,
                        available_quantity: availableQuantity,
                    };
                },

                stageCancelAction(itemId, form) {
                    const ui = this.ensureItemUi(itemId);
                    const formData = new FormData(form);
                    const requestedQuantity = Math.max(0, Number(formData.get('quantity') || 0));
                    const reason = String(formData.get('reason') || '').trim();
                    const breakdown = this.cancellationBreakdown(itemId, requestedQuantity);
                    const availableQuantity = breakdown.available_quantity;
                    const delayedQuantity = breakdown.delayed_quantity;

                    if (requestedQuantity <= 0) {
                        ui.cancelError = 'Stage at least one quantity to cancel.';
                        return;
                    }

                    if (requestedQuantity > this.openQuantity(itemId)) {
                        ui.cancelError = 'Cancellation quantity exceeds what is still open after other staged actions.';
                        return;
                    }

                    if (reason === '') {
                        ui.cancelError = 'Enter a reason before staging this cancellation.';
                        return;
                    }

                    this.pendingActions.push({
                        client_id: `queued-${this.nextClientId++}`,
                        type: 'cancel',
                        item_id: Number(itemId),
                        quantity: requestedQuantity,
                        available_quantity: availableQuantity,
                        delayed_quantity: delayedQuantity,
                        reason,
                        shipment_type: '',
                        carrier: '',
                        tracking_number: '',
                        tracking_url: '',
                        notes: '',
                        dispatched_at: '',
                    });

                    ui.cancelError = '';
                    ui.cancelOpen = false;
                    form.reset();
                },

                stageTrackingAction(itemId, form) {
                    const ui = this.ensureItemUi(itemId);
                    const formData = new FormData(form);
                    const shipmentType = String(formData.get('shipment_type') || 'available');
                    const quantity = Math.max(0, Number(formData.get('quantity') || 0));
                    const remaining = shipmentType === 'delayed'
                        ? this.remainingDelayed(itemId)
                        : this.remainingAvailable(itemId);
                    const trackingUrl = String(formData.get('tracking_url') || '').trim();

                    if (quantity <= 0) {
                        ui.trackingError = 'Stage at least one item to dispatch.';
                        return;
                    }

                    if (quantity > remaining) {
                        ui.trackingError = 'Tracking quantity exceeds what is still open after other staged actions.';
                        return;
                    }

                    if (trackingUrl !== '') {
                        try {
                            new URL(trackingUrl);
                        } catch (error) {
                            ui.trackingError = 'Enter a valid tracking link before staging this dispatch.';
                            return;
                        }
                    }

                    this.pendingActions.push({
                        client_id: `queued-${this.nextClientId++}`,
                        type: 'tracking',
                        item_id: Number(itemId),
                        available_quantity: 0,
                        delayed_quantity: 0,
                        reason: '',
                        shipment_type: shipmentType,
                        quantity,
                        carrier: String(formData.get('carrier') || '').trim(),
                        tracking_number: String(formData.get('tracking_number') || '').trim(),
                        tracking_url: trackingUrl,
                        notes: String(formData.get('notes') || '').trim(),
                        dispatched_at: String(formData.get('dispatched_at') || '').trim(),
                    });

                    ui.trackingError = '';
                    ui.trackingOpen = false;
                    form.reset();
                },

                removePendingAction(clientId) {
                    this.pendingActions = this.pendingActions.filter((action) => action.client_id !== clientId);
                },

                clearPendingActions() {
                    this.pendingActions = [];
                    Object.keys(this.itemUi || {}).forEach((itemId) => {
                        const ui = this.ensureItemUi(itemId);
                        ui.cancelError = '';
                        ui.trackingError = '';
                    });
                },

                actionSummary(action) {
                    const item = this.items?.[String(action.item_id)];
                    const title = String(item?.title || 'Order item');

                    if (String(action.type) === 'cancel') {
                        const parts = [];
                        if (Number(action.available_quantity || 0) > 0) {
                            parts.push(`reserved ${Number(action.available_quantity)}`);
                        }
                        if (Number(action.delayed_quantity || 0) > 0) {
                            parts.push(`backorder ${Number(action.delayed_quantity)}`);
                        }

                        const quantity = Number(action.quantity || 0);
                        return `${title} · Cancel ${quantity > 0 ? `${quantity} unit${quantity === 1 ? '' : 's'}` : parts.join(' / ').trim()}`.trim();
                    }

                    const shipmentLabel = String(action.shipment_type) === 'delayed' ? 'Backorder' : 'Reserved';
                    return `${title} · Ship ${Number(action.quantity || 0)} ${shipmentLabel.toLowerCase()} item${Number(action.quantity || 0) === 1 ? '' : 's'}`;
                },

                actionDetail(action) {
                    if (String(action.type) === 'cancel') {
                        const parts = [];
                        if (Number(action.delayed_quantity || 0) > 0) {
                            parts.push(`Backorder ${Number(action.delayed_quantity)}`);
                        }
                        if (Number(action.available_quantity || 0) > 0) {
                            parts.push(`Reserved ${Number(action.available_quantity)}`);
                        }

                        return [parts.join(' | '), String(action.reason || '')]
                            .filter((part) => part !== '')
                            .join(' | ');
                    }

                    return [
                        String(action.carrier || '').trim(),
                        String(action.tracking_number || '').trim() !== '' ? `Tracking ${String(action.tracking_number).trim()}` : '',
                        String(action.dispatched_at || '').trim(),
                        String(action.notes || '').trim(),
                    ].filter((part) => part !== '').join(' | ');
                },

                actionsJson() {
                    return JSON.stringify(this.pendingActions.map(({ client_id, ...action }) => action));
                },
            };
        };
    </script>
    <x-mast backRoute="admin.shop.order.index" backTitle="Store Orders">Order {{ $order->order_number }}</x-mast>

    <x-container class="py-8">
        <div class="space-y-6" x-data="window.shopAdminOrderEditor({{ $orderEditorConfig }})">
            <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-sm uppercase tracking-[0.18em] text-gray-500">Store Order</div>
                        <h2 class="text-3xl font-bold text-gray-900">{{ $order->order_number }}</h2>
                        <div class="mt-2 text-sm text-gray-600">{{ $order->statusLabel() }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($order->invoice)
                            <x-ui.button type="link" href="{{ route('admin.invoice.edit', $order->invoice) }}" color="outline">Open Invoice</x-ui.button>
                        @endif
                        @if($order->items->first()?->product)
                            <x-ui.button type="link" href="{{ route('shop.product.show', $order->items->first()->product) }}" color="outline">View Product</x-ui.button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.15fr),minmax(0,0.8fr),minmax(0,0.95fr)]">
                    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Order Snapshot</div>
                        <div class="mt-4 space-y-3 text-sm text-gray-700">
                            <div class="flex items-center justify-between gap-4"><span>Invoice</span><span class="font-semibold">{{ $order->invoice?->invoice_number ?? '-' }}</span></div>
                            <div class="flex items-center justify-between gap-4"><span>Placed</span><span class="font-semibold">{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</span></div>
                            <div class="flex items-center justify-between gap-4"><span>Paid</span><span class="font-semibold">{{ $order->paid_at?->format('M j, Y g:i a') ?? 'No' }}</span></div>
                            <div class="flex items-center justify-between gap-4"><span>Complete</span><span class="font-semibold">{{ $order->fulfilled_at?->format('M j, Y g:i a') ?? 'No' }}</span></div>
                            <div class="flex items-center justify-between gap-4"><span>Contains</span><span class="font-semibold">{{ $order->contains_physical ? 'Physical' : 'Digital' }}{{ $order->contains_digital && $order->contains_physical ? ' + Digital' : '' }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Customer</div>
                        <div class="mt-4 space-y-2 text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">{{ $order->billing_name ?: '-' }}</div>
                            <div>{{ $order->billing_email ?: '-' }}</div>
                            <div>{{ $order->billing_phone ?: '-' }}</div>
                            @if($order->billing_company)
                                <div>{{ $order->billing_company }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $order->contains_physical ? 'Delivery' : 'Fulfilment' }}</div>
                        <div class="mt-4 space-y-3 text-sm text-gray-700">
                            @if($order->contains_physical)
                                <div>
                                    <div class="font-semibold text-gray-900">{{ $order->shipping_method ?: 'Shipping' }}</div>
                                    @if($order->usesPickup())
                                        <div class="mt-1">Customer will be contacted for collection.</div>
                                    @endif
                                    @if($order->shipping_package_summary)
                                        <div class="mt-1">{{ $order->shipping_package_summary }}</div>
                                    @endif
                                </div>
                                @if(!$order->usesPickup())
                                    <div class="space-y-1">
                                        @foreach($order->shippingAddressLines() as $line)
                                            <div>{{ $line }}</div>
                                        @endforeach
                                    </div>
                                @endif
                                @if(!empty($shippingBreakdown['shipments']))
                                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                                        @include('shop.partials.shipping-breakdown', [
                                            'shipments' => $shippingBreakdown['shipments'],
                                        ])
                                    </div>
                                @endif
                            @else
                                <div>Digital fulfilment only. No shipping workflow is attached to this order.</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Items</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">${{ number_format((float) $order->subtotal_amount, 2) }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Shipping</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">${{ number_format((float) $order->shipping_amount, 2) }}</div>
                        @if((float) $order->discount_amount > 0)
                            <div class="mt-1 text-xs text-emerald-700">Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }} - ${{ number_format((float) $order->discount_amount, 2) }}</div>
                        @endif
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">GST Included</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">${{ number_format((float) $order->gst_amount, 2) }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Outstanding</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">${{ number_format((float) ($order->invoice?->outstandingAmount() ?? 0), 2) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Order total ${{ number_format((float) $order->total_amount, 2) }}</div>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr),minmax(340px,0.8fr)]">
                <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Items</h2>
                    <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Item actions are staged here first. When you save the order, reserved stock is released, one consolidated tax adjustment note is created for the queued cancellations, and any linked Square refund is attempted once for the combined amount.
                    </div>
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            @php
                                $cancelBag = $errors->getBag('cancelItem_'.$item->id);
                                $trackingBag = $errors->getBag('trackingItem_'.$item->id);
                                $orderedQty = max(0, (int) $item->quantity);
                                $availableTotal = $item->availableQuantityTotal();
                                $delayedTotal = $item->delayedQuantityTotal();
                                $remainingAvailable = $item->remainingAvailableQuantity();
                                $remainingDelayed = $item->remainingDelayedQuantity();
                                $remainingTotal = $item->remainingFulfillableQuantity();
                                $cancelledTotal = $item->cancelledQuantity();
                                $trackedTotal = $item->trackedQuantity();
                                $dispatchedTotal = $trackedTotal;
                                $reservedQty = $item->reservedInventory();
                                $canTrack = $order->contains_physical && !$order->usesPickup() && !$item->isDigital() && ($remainingAvailable > 0 || $remainingDelayed > 0);
                                $cancelOpen = $cancelBag->isNotEmpty();
                                $trackingOpen = $trackingBag->isNotEmpty();
                                $cancelAvailableValue = $cancelOpen ? old('available_quantity', '') : '';
                                $cancelDelayedValue = $cancelOpen ? old('delayed_quantity', '') : '';
                                $cancelReasonValue = $cancelOpen ? old('reason', '') : '';
                                $trackingStageValue = $trackingOpen ? old('shipment_type', \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE) : \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE;
                                $trackingQtyValue = $trackingOpen ? old('quantity', '') : '';
                                $trackingCarrierValue = $trackingOpen ? old('carrier', '') : '';
                                $trackingDispatchValue = $trackingOpen ? old('dispatched_at', now()->toDateString()) : now()->toDateString();
                                $trackingNumberValue = $trackingOpen ? old('tracking_number', '') : '';
                                $trackingUrlValue = $trackingOpen ? old('tracking_url', '') : '';
                                $trackingNotesValue = $trackingOpen ? old('notes', '') : '';
                            @endphp
                            <div id="item-{{ $item->id }}" class="rounded-2xl border border-gray-200 p-4" x-init="@if($cancelOpen) openCancel({{ $item->id }}); @endif @if($trackingOpen) openTracking({{ $item->id }}); @endif">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $item->displayTitle() }}</div>
                                        <div class="text-sm text-gray-500">Qty {{ $item->quantity }} · {{ \App\Models\Product::productTypeLabel((string) $item->product_type) }}</div>
                                        @if($item->is_preorder)
                                            <div class="text-sm text-amber-800">Pre-order · Estimated shipping {{ $item->preorderShippingEstimateLabel() ?: 'to be confirmed' }}</div>
                                        @elseif($item->isBackorder())
                                            <div class="text-sm text-sky-800">
                                                @if((int) $item->available_now_quantity > 0)
                                                    {{ (int) $item->available_now_quantity }} shipped first, {{ (int) $item->delayed_quantity }} delayed{{ $item->delayedShippingEstimateLabel() ? ' from '.$item->delayedShippingEstimateLabel() : '' }}
                                                @else
                                                    Backorder · Expected shipping {{ $item->delayedShippingEstimateLabel() ?: 'to be confirmed' }}
                                                @endif
                                            </div>
                                        @endif
                                        @if($item->variant_sku || $item->product_sku)
                                            <div class="text-sm text-gray-500">SKU {{ $item->variant_sku ?: $item->product_sku }}</div>
                                        @endif
                                        <div class="mt-1 text-xs text-gray-500">
                                            Ordered {{ $orderedQty }}
                                            · Dispatched <span x-text="dispatchedQuantity({{ $item->id }})">{{ $dispatchedTotal }}</span>
                                            · Open <span x-text="openQuantity({{ $item->id }})">{{ $remainingTotal }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900">${{ number_format((float) $item->line_total_amount, 2) }}</div>
                                        <div class="text-xs text-gray-500">GST ${{ number_format((float) $item->line_gst_amount, 2) }}</div>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Ordered Qty</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ $orderedQty }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-rose-700">Cancelled Qty</div>
                                        <div class="mt-1 text-lg font-semibold text-rose-900" x-text="cancelledQuantity({{ $item->id }})">{{ $cancelledTotal }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Dispatched Qty</div>
                                        <div class="mt-1 text-lg font-semibold text-emerald-950" x-text="dispatchedQuantity({{ $item->id }})">{{ $dispatchedTotal }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-700">Reserved Qty</div>
                                        <div class="mt-1 text-lg font-semibold text-amber-950" x-text="reservedQuantity({{ $item->id }})">{{ $reservedQty }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-700">Backorder Qty</div>
                                        <div class="mt-1 text-lg font-semibold text-sky-950" x-text="remainingDelayed({{ $item->id }})">{{ $remainingDelayed }}</div>
                                    </div>
                                </div>
                                <div class="mt-3 text-xs text-gray-500">
                                    Reserved qty is stock currently held for this order item. Backorder qty is still waiting on stock before it can be sent.
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <x-ui.button type="button" color="danger-outline" x-on:click="openCancel({{ $item->id }})" x-bind:disabled="!canCancel({{ $item->id }})">Cancel Items</x-ui.button>
                                    <x-ui.button type="button" color="primary-outline" x-on:click="openTracking({{ $item->id }})" x-bind:disabled="!canTrack({{ $item->id }})">Add Shipment</x-ui.button>
                                    <div class="text-xs text-gray-500">Stage dispatch or cancellation changes here, then save the order once.</div>
                                </div>
                                <div x-show="pendingActionCountForItem({{ $item->id }}) > 0" x-cloak class="mt-3 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-indigo-950">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-indigo-700">Pending Item Changes</div>
                                    <div class="mt-3 space-y-2">
                                        <template x-for="action in pendingActionsForItem({{ $item->id }})" :key="action.client_id">
                                            <div class="rounded-2xl border border-indigo-200 bg-white/90 px-3 py-3">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="text-sm font-semibold text-indigo-950" x-text="actionSummary(action)"></div>
                                                        <div class="mt-1 text-xs leading-5 text-indigo-900/80" x-text="actionDetail(action) || 'Ready to apply on save.'"></div>
                                                    </div>
                                                    <button type="button" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-900 transition hover:border-indigo-300 hover:bg-indigo-50" x-on:click="removePendingAction(action.client_id)">
                                                        Undo
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                @if($item->cancellations->isNotEmpty())
                                    <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50/50 p-4">
                                        <div class="text-sm font-semibold text-rose-950">Cancellation History</div>
                                        <div class="mt-3 space-y-3">
                                            @foreach($item->cancellations as $cancellation)
                                                @php
                                                    $cancelledByName = trim((string) (($cancellation->cancelledBy?->firstname ?? '').' '.($cancellation->cancelledBy?->surname ?? '')));
                                                    $cancelledByLabel = $cancelledByName !== '' ? $cancelledByName : ($cancellation->cancelledBy?->email ?? 'Admin');
                                                @endphp
                                                <div class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-sm text-gray-700">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div class="font-semibold text-gray-900">Qty {{ $cancellation->quantity() }} removed from fulfilment</div>
                                                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-500">
                                                                @if((int) $cancellation->available_quantity > 0)
                                                                    <span>Reserved {{ (int) $cancellation->available_quantity }}</span>
                                                                @endif
                                                                @if((int) $cancellation->delayed_quantity > 0)
                                                                    <span>Backorder {{ (int) $cancellation->delayed_quantity }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="text-right text-xs text-gray-500">
                                                            <div>{{ $cancelledByLabel }}</div>
                                                            <div>{{ $cancellation->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 text-xs leading-6 text-gray-600">{{ $cancellation->reason }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($item->trackingEntries->isNotEmpty())
                                    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                                        <div class="text-sm font-semibold text-emerald-950">Shipment Entries</div>
                                        <div class="mt-1 text-xs text-emerald-900/80">Each entry records a dispatch that already happened. Use the order status for the overall state, including Partially Shipped and Shipped.</div>
                                        <div class="mt-3 space-y-3">
                                            @foreach($item->trackingEntries as $tracking)
                                                <div class="rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-gray-700">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div class="font-semibold text-gray-900">
                                                                {{ $tracking->shipmentTypeLabel() }} · Qty {{ (int) $tracking->quantity }}
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                {{ $tracking->dispatched_at?->format('M j, Y g:i a') ?? 'Dispatch date not set' }}
                                                            </div>
                                                        </div>
                                                        <div class="text-right text-xs text-gray-500">
                                                            @if($tracking->carrier)
                                                                <div>{{ $tracking->carrier }}</div>
                                                            @endif
                                                            @if($tracking->tracking_number)
                                                                <div class="font-medium text-gray-700">{{ $tracking->tracking_number }}</div>
                                                            @endif
                                                            @if($tracking->tracking_url)
                                                                <a href="{{ $tracking->tracking_url }}" target="_blank" rel="noopener noreferrer" class="text-sky-700 hover:text-sky-900">Open tracking link</a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($tracking->notes)
                                                        <div class="mt-2 text-xs leading-6 text-gray-600">{{ $tracking->notes }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].cancelOpen" x-cloak class="fixed inset-0 z-[180] bg-black/55" x-on:click.self="closeCancel({{ $item->id }})" x-on:keydown.escape.window="closeCancel({{ $item->id }})">
                                    <div class="flex min-h-full items-center justify-center p-4">
                                        <div class="w-full max-w-2xl rounded-3xl bg-white shadow-xl">
                                            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">Cancel Items</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Remove units from this item’s fulfilment plan. Backordered units are cancelled first, then any reserved stock if more units still need to be removed.</p>
                                                </div>
                                                <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeCancel({{ $item->id }})" aria-label="Close cancel modal">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.shop.order.item.cancel', ['storeOrder' => $order, 'storeOrderItem' => $item]) }}" class="px-6 py-5" x-on:submit.prevent="stageCancelAction({{ $item->id }}, $event.target)">
                                                @csrf
                                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Ordered Qty</div>
                                                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ $orderedQty }}</div>
                                                    </div>
                                                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-rose-700">Cancelled Qty</div>
                                                        <div class="mt-1 text-lg font-semibold text-rose-900" x-text="cancelledQuantity({{ $item->id }})">{{ $cancelledTotal }}</div>
                                                    </div>
                                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-700">Reserved Qty</div>
                                                        <div class="mt-1 text-lg font-semibold text-amber-950" x-text="remainingAvailable({{ $item->id }})">{{ $remainingAvailable }}</div>
                                                    </div>
                                                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-700">Backorder Qty</div>
                                                        <div class="mt-1 text-lg font-semibold text-sky-950" x-text="remainingDelayed({{ $item->id }})">{{ $remainingDelayed }}</div>
                                                    </div>
                                                </div>
                                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Qty to cancel</label>
                                                        <input type="number" name="quantity" min="0" max="{{ $remainingTotal }}" x-bind:max="openQuantity({{ $item->id }})" value="{{ $cancelAvailableValue !== '' || $cancelDelayedValue !== '' ? (int) $cancelAvailableValue + (int) $cancelDelayedValue : '' }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-rose-300 focus:outline-none focus:ring-0" />
                                                        @if($cancelBag->first('quantity'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $cancelBag->first('quantity') }}</div>
                                                        @endif
                                                        @if($cancelBag->first('available_quantity'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $cancelBag->first('available_quantity') }}</div>
                                                        @endif
                                                        @if($cancelBag->first('delayed_quantity'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $cancelBag->first('delayed_quantity') }}</div>
                                                        @endif
                                                    </div>
                                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Cancellation Order</div>
                                                        <div class="mt-2">Backorder items are removed before reserved items.</div>
                                                        <div class="mt-2 text-xs text-gray-500">This keeps reserved stock in place unless the delayed quantity has already been exhausted.</div>
                                                    </div>
                                                </div>
                                                <div class="mt-4">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Reason</label>
                                                    <textarea name="reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-rose-300 focus:outline-none focus:ring-0" placeholder="Why are these units being cancelled from fulfilment?">{{ $cancelReasonValue }}</textarea>
                                                    @if($cancelBag->first('reason'))
                                                        <div class="mt-1 text-xs text-rose-700">{{ $cancelBag->first('reason') }}</div>
                                                    @endif
                                                    @if($cancelBag->first('item'))
                                                        <div class="mt-1 text-xs text-rose-700">{{ $cancelBag->first('item') }}</div>
                                                    @endif
                                                </div>
                                                <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].cancelError" x-cloak class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                                    <span x-text="itemUi['{{ $item->id }}'] ? itemUi['{{ $item->id }}'].cancelError : ''"></span>
                                                </div>
                                                <div class="mt-5 flex flex-wrap justify-end gap-3">
                                                    <x-ui.button type="button" color="outline" x-on:click="closeCancel({{ $item->id }})">Close</x-ui.button>
                                                    <x-ui.button type="submit" color="danger" x-bind:disabled="!canCancel({{ $item->id }})">Stage Cancellation</x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].trackingOpen" x-cloak class="fixed inset-0 z-[180] bg-black/55" x-on:click.self="closeTracking({{ $item->id }})" x-on:keydown.escape.window="closeTracking({{ $item->id }})">
                                    <div class="flex min-h-full items-center justify-center p-4">
                                        <div class="w-full max-w-2xl rounded-3xl bg-white shadow-xl">
                                            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">Add Shipment Entry</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Record a dispatch for the units being sent now. This shipment entry is the shipped record for those units; the order status handles the overall order state.</p>
                                                </div>
                                                <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeTracking({{ $item->id }})" aria-label="Close tracking modal">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.shop.order.item.tracking.store', ['storeOrder' => $order, 'storeOrderItem' => $item]) }}" class="px-6 py-5" x-on:submit.prevent="stageTrackingAction({{ $item->id }}, $event.target)">
                                                @csrf
                                                @if($canTrack)
                                                    <div class="grid gap-3 sm:grid-cols-2">
                                                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-700">Reserved Qty</div>
                                                            <div class="mt-1 text-lg font-semibold text-amber-950" x-text="remainingAvailable({{ $item->id }})">{{ $remainingAvailable }}</div>
                                                        </div>
                                                        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                                                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-700">Backorder Qty</div>
                                                            <div class="mt-1 text-lg font-semibold text-sky-950" x-text="remainingDelayed({{ $item->id }})">{{ $remainingDelayed }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Shipment stage</label>
                                                            @if($remainingAvailable > 0 && $remainingDelayed > 0)
                                                                <select name="shipment_type" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0">
                                                                    <option value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}" @selected($trackingStageValue === \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE) x-text="'Reserved items (' + remainingAvailable({{ $item->id }}) + ' remaining)'">Reserved items ({{ $remainingAvailable }} remaining)</option>
                                                                    <option value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED }}" @selected($trackingStageValue === \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED) x-text="'Backorder items (' + remainingDelayed({{ $item->id }}) + ' remaining)'">Backorder items ({{ $remainingDelayed }} remaining)</option>
                                                                </select>
                                                            @elseif($remainingDelayed > 0)
                                                                <input type="hidden" name="shipment_type" value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED }}" />
                                                                <div class="rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900" x-text="'Backorder items (' + remainingDelayed({{ $item->id }}) + ' remaining)'">Backorder items ({{ $remainingDelayed }} remaining)</div>
                                                            @else
                                                                <input type="hidden" name="shipment_type" value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}" />
                                                                <div class="rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900" x-text="'Reserved items (' + remainingAvailable({{ $item->id }}) + ' remaining)'">Reserved items ({{ $remainingAvailable }} remaining)</div>
                                                            @endif
                                                            @if($trackingBag->first('shipment_type'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('shipment_type') }}</div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Items in this shipment</label>
                                                            <input type="number" name="quantity" min="1" max="{{ max($remainingAvailable, $remainingDelayed) }}" x-bind:max="Math.max(remainingAvailable({{ $item->id }}), remainingDelayed({{ $item->id }}))" value="{{ $trackingQtyValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" />
                                                            @if($trackingBag->first('quantity'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('quantity') }}</div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <x-ui.input
                                                                name="carrier"
                                                                label="Courier"
                                                                :value="$trackingCarrierValue"
                                                                placeholder="Australia Post"
                                                                :suggestions="$carrierSuggestions ?? []"
                                                                showSuggestionsOnFocus="true"
                                                                :error="$trackingBag->first('carrier')"
                                                                class="!mb-0"
                                                            />
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Dispatch date</label>
                                                            <input type="date" name="dispatched_at" value="{{ $trackingDispatchValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" />
                                                            @if($trackingBag->first('dispatched_at'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('dispatched_at') }}</div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking number</label>
                                                            <input type="text" name="tracking_number" value="{{ $trackingNumberValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" placeholder="Optional" />
                                                            @if($trackingBag->first('tracking_number'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('tracking_number') }}</div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking link</label>
                                                            <input type="url" name="tracking_url" value="{{ $trackingUrlValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" placeholder="Optional" />
                                                            @if($trackingBag->first('tracking_url'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('tracking_url') }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="mt-4">
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Notes</label>
                                                        <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0">{{ $trackingNotesValue }}</textarea>
                                                        @if($trackingBag->first('notes'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('notes') }}</div>
                                                        @endif
                                                        @if($trackingBag->first('item'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('item') }}</div>
                                                        @endif
                                                    </div>
                                                    <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].trackingError" x-cloak class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                                        <span x-text="itemUi['{{ $item->id }}'] ? itemUi['{{ $item->id }}'].trackingError : ''"></span>
                                                    </div>
                                                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                                                        <x-ui.button type="button" color="outline" x-on:click="closeTracking({{ $item->id }})">Close</x-ui.button>
                                                        <x-ui.button type="submit">Stage Shipment Entry</x-ui.button>
                                                    </div>
                                                @else
                                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-950">
                                                        @if($order->usesPickup())
                                                            Tracking is hidden for pickup orders.
                                                        @elseif($item->isDigital())
                                                            Tracking is not used for digital items.
                                                        @else
                                                            No open quantity is left on this item to track.
                                                        @endif
                                                    </div>
                                                    <div class="mt-5 flex justify-end">
                                                        <x-ui.button type="button" color="outline" x-on:click="closeTracking({{ $item->id }})">Close</x-ui.button>
                                                    </div>
                                                @endif
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                @if($item->downloads->isNotEmpty())
                                    <div class="mt-4 border-t border-gray-200 pt-4 space-y-2">
                                        @foreach($item->downloads as $download)
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <span>{{ $download->title ?: ($download->media?->title ?: $download->media_name) }}</span>
                                                <span class="text-gray-500">{{ $download->media?->file_type ?? 'Download file' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                </div>

                <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Order Changes</h2>
                                <p class="mt-1 text-sm text-gray-600">Status, notes, cancellations, and tracking are committed together on the final save.</p>
                            </div>
                            <div class="rounded-full border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">
                                <span x-text="pendingActions.length">0</span> staged
                            </div>
                        </div>

                        <form id="order-status-form" method="POST" action="{{ route('admin.shop.order.update', $order) }}" class="mt-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="item_actions_json" x-bind:value="actionsJson()" />
                            <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
                                Save once to create one consolidated item-change workflow, including customer/admin emails, a combined TAN, and one refund attempt where possible.
                            </div>
                            @if($errors->has('item_actions_json'))
                                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                    {{ $errors->first('item_actions_json') }}
                                </div>
                            @endif
                            <x-ui.select name="status" label="Order Status">
                                @foreach(\App\Models\StoreOrder::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ (new \App\Models\StoreOrder(['status' => $status]))->statusLabel() }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.input type="textarea" name="notes" label="Internal Notes" :value="$order->notes ?? ''" />
                            <x-ui.input type="textarea" name="public_notes" label="Public Notes" :value="$order->public_notes ?? ''" info="Visible to the customer from their order page. Use for progress updates or collection instructions." />
                            <x-ui.button type="submit" class="w-full">
                                <span x-show="!hasPendingActions()">Save All Changes</span>
                                <span x-show="hasPendingActions()" x-cloak>Save All Changes (<span x-text="pendingActions.length">0</span> staged)</span>
                            </x-ui.button>
                        </form>
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Queued Order Changes</h2>
                                <p class="mt-1 text-sm text-gray-600">Review the staged actions before applying them.</p>
                            </div>
                            <button
                                type="button"
                                x-show="hasPendingActions()"
                                x-cloak
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 hover:text-gray-900"
                                x-on:click="clearPendingActions()"
                            >
                                Clear Staged Changes
                            </button>
                        </div>

                        <div x-show="hasPendingActions()" x-cloak>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Queued Actions</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900" x-text="pendingActions.length">0</div>
                                </div>
                                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-rose-700">Queued Cancellations</div>
                                    <div class="mt-1 text-lg font-semibold text-rose-900" x-text="pendingCancellationCount()">0</div>
                                </div>
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Queued Shipment Entries</div>
                                    <div class="mt-1 text-lg font-semibold text-emerald-950" x-text="pendingTrackingCount()">0</div>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <template x-for="action in pendingActions" :key="action.client_id">
                                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-semibold text-gray-900" x-text="actionSummary(action)"></div>
                                                <div class="mt-1 text-xs leading-6 text-gray-600" x-text="actionDetail(action) || 'Ready to apply on save.'"></div>
                                            </div>
                                            <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 hover:text-gray-900" x-on:click="removePendingAction(action.client_id)">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div x-show="!hasPendingActions()" x-cloak class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                            No item changes are staged yet. Use the item actions on the left to queue cancellations or tracking updates.
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </x-container>
</x-layout>
