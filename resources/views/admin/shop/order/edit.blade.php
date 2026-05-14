    <x-layout>
        @php
        $isQuoteRequested = (string) $order->status === \App\Models\StoreOrder::STATUS_QUOTE_REQUESTED;
        $linkedQuote = $order->quote ?? $order->invoice?->quote;
        $shippingBreakdown = $order->shippingBreakdown();
        $pickupCollectionOpen = $order->usesPickup()
            && ! in_array((string) $order->status, [
                \App\Models\StoreOrder::STATUS_COLLECTED,
                \App\Models\StoreOrder::STATUS_FULFILLED,
                \App\Models\StoreOrder::STATUS_CANCELLED,
            ], true);
        $maxParcelNumber = (int) $order->items
            ->flatMap(fn ($item) => $item->trackingEntries)
            ->map(fn ($tracking) => max(0, (int) ($tracking->parcel_number ?? 0)))
            ->max();
        $maxParcelNumber = max(0, $maxParcelNumber);
        $defaultParcelNumber = max(1, $maxParcelNumber + 1);
        $itemActionMeta = $order->items
            ->mapWithKeys(function ($item) use ($order, $pickupCollectionOpen) {
                $remainingAvailable = $item->remainingAvailableQuantity();
                $remainingDelayed = $item->remainingDelayedQuantity();
                $remainingPickupAvailable = $item->remainingPickupAvailableQuantity();
                $remainingPickupDelayed = $item->remainingPickupDelayedQuantity();
                $remainingPickup = $item->remainingPickupQuantity();
                $readyPickupAvailable = $item->readyPickupAvailableQuantity();
                $readyPickupDelayed = $item->readyPickupDelayedQuantity();
                $readyPickup = $item->readyPickupQuantity();
                $remainingPickupToReady = $item->remainingPickupToReadyQuantity();

                return [
                    (string) $item->id => [
                        'id' => (int) $item->id,
                        'title' => $item->displayTitle(),
                        'is_digital' => $item->isDigital(),
                        'ordered_quantity' => max(0, (int) $item->quantity),
                        'cancelled_quantity' => $item->cancelledQuantity(),
                        'tracked_quantity' => $item->trackedQuantity(),
                        'reserved_quantity' => $item->reservedInventory(),
                        'remaining_available' => $remainingAvailable,
                        'remaining_delayed' => $remainingDelayed,
                        'remaining_pickup_available' => $remainingPickupAvailable,
                        'remaining_pickup_delayed' => $remainingPickupDelayed,
                        'remaining_pickup' => $remainingPickup,
                        'ready_pickup_available' => $readyPickupAvailable,
                        'ready_pickup_delayed' => $readyPickupDelayed,
                        'ready_pickup' => $readyPickup,
                        'remaining_pickup_to_ready' => $remainingPickupToReady,
                        'collected_quantity' => $item->collectedQuantity(),
                        'open_quantity' => $item->remainingFulfillableQuantity(),
                        'can_track' => $order->contains_physical && ! $order->usesPickup() && ! $item->isDigital() && ($remainingAvailable > 0 || $remainingDelayed > 0),
                        'can_ready' => $pickupCollectionOpen && ! $item->isDigital() && $remainingPickupToReady > 0,
                        'can_collect' => $pickupCollectionOpen && ! $item->isDigital() && $readyPickup > 0,
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
        $itemActionsLocked = $order->isLockedForItemChanges();
        $orderEditorConfig = \Illuminate\Support\Js::from([
            'items' => $itemActionMeta,
            'initialActions' => $initialPendingActions,
            'initialSidebarTab' => $errors->has('public_notes') ? 'public-notes' : ($errors->has('notes') ? 'private-notes' : 'changes'),
                'currentStatus' => (string) $order->status,
                'containsPhysical' => (bool) $order->contains_physical,
                'usesPickup' => (bool) $order->usesPickup(),
                'pickupCollectionOpen' => (bool) $pickupCollectionOpen,
                'maxParcelNumber' => $maxParcelNumber,
                'trackingLinkTemplates' => \App\Support\ShopShippingSettings::trackingLinkTemplates(),
                'statusLabels' => [
                    \App\Models\StoreOrder::STATUS_PENDING_PAYMENT => 'Pending Payment',
                    \App\Models\StoreOrder::STATUS_QUOTE_REQUESTED => 'Quote Requested',
                    \App\Models\StoreOrder::STATUS_PROCESSING => 'Preparing Order',
                    \App\Models\StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION => 'Ready for Partial Collection',
                    \App\Models\StoreOrder::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
                    \App\Models\StoreOrder::STATUS_PARTIALLY_COLLECTED => 'Partially Collected',
                    \App\Models\StoreOrder::STATUS_PARTIALLY_SHIPPED => 'Partially Shipped',
                    \App\Models\StoreOrder::STATUS_SHIPPED => 'Shipped',
                    \App\Models\StoreOrder::STATUS_COLLECTED => 'Collected',
                \App\Models\StoreOrder::STATUS_FULFILLED => 'Complete',
                \App\Models\StoreOrder::STATUS_CANCELLED => 'Cancelled',
            ],
            'itemActionsLocked' => $itemActionsLocked,
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
                    collection_type: String(action?.collection_type || ''),
                    pickup_state: String(action?.pickup_state || ''),
                    quantity: Number(action?.quantity || 0),
                    collected_at: String(action?.collected_at || ''),
                    notes: String(action?.notes || ''),
                    tracking_mode: String(action?.tracking_mode || (String(action?.tracking_number || '').trim() !== '' || String(action?.tracking_url || '').trim() !== '' ? 'tracking_number' : 'none')),
                    shipment_type: String(action?.shipment_type || ''),
                    parcel_number: Number(action?.parcel_number || 0),
                    carrier: String(action?.carrier || ''),
                    tracking_number: String(action?.tracking_number || ''),
                    tracking_url: String(action?.tracking_url || ''),
                    dispatched_at: String(action?.dispatched_at || ''),
                })),
                itemUi: {},
                selectedItemIds: [],
                bulkCancelOpen: false,
                bulkCancelError: '',
                bulkCancelReason: '',
                bulkTrackingOpen: false,
                bulkTrackingError: '',
                bulkTrackingShipmentType: '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}',
                bulkTrackingMode: 'none',
                bulkTrackingModeTouched: false,
                bulkTrackingParcelNumber: @js($defaultParcelNumber),
                bulkTrackingCarrier: '',
                bulkTrackingTrackingNumber: '',
                bulkTrackingTrackingUrl: '',
                bulkTrackingNotes: '',
                bulkTrackingDispatchedAt: @js(now()->toDateString()),
                currentStatus: String(config?.currentStatus || ''),
                containsPhysical: Boolean(config?.containsPhysical),
                usesPickup: Boolean(config?.usesPickup),
                maxParcelNumber: Number(config?.maxParcelNumber || 0),
                trackingLinkTemplates: config?.trackingLinkTemplates || {},
                statusLabels: config?.statusLabels || {},
                statusValue: @js(old('status', $order->status)),
                liveStatusCode: @js($order->status),
                statusEditorOpen: false,
                manualStatusTouched: false,
                sidebarTab: config?.initialSidebarTab || 'changes',
                nextClientId: normalizedInitialActions.length + 1,

                init() {
                    Object.keys(this.items || {}).forEach((itemId) => {
                        this.ensureItemUi(itemId);
                    });
                    this.refreshLiveStatus();
                },

                ensureItemUi(itemId) {
                    const key = String(itemId);
                    if (!this.itemUi[key]) {
                        this.itemUi[key] = {
                            cancelOpen: false,
                            trackingOpen: false,
                            collectionOpen: false,
                            detailsOpen: false,
                            cancelError: '',
                            trackingError: '',
                            collectionError: '',
                            pickupState: '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}',
                            trackingMode: 'none',
                            trackingModeTouched: false,
                            trackingShipmentType: '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}',
                            trackingQuantity: 0,
                            trackingParcelNumber: 1,
                            trackingCarrier: '',
                            trackingNumber: '',
                            trackingUrl: '',
                            collectionType: '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE }}',
                            collectionQuantity: 0,
                            collectionCollectedAt: '',
                            collectionNotes: '',
                        };
                    }

                    return this.itemUi[key];
                },

                itemMeta(itemId) {
                    return this.items?.[String(itemId)] || {};
                },

                itemProjectedCancelledQuantity(itemId) {
                    const base = Number(this.itemMeta(itemId).cancelled_quantity || 0);
                    return base + this.pendingCancelAvailable(itemId) + this.pendingCancelDelayed(itemId);
                },

                itemProjectedTrackedQuantity(itemId) {
                    const base = Number(this.itemMeta(itemId).tracked_quantity || 0);
                    return base + this.pendingTrackedAvailable(itemId) + this.pendingTrackedDelayed(itemId);
                },

                orderedQuantity(itemId) {
                    return Number(this.itemMeta(itemId).ordered_quantity || 0);
                },

                itemProjectedOpenQuantity(itemId) {
                    const base = Number(this.itemMeta(itemId).open_quantity || 0);
                    return Math.max(0, base - this.pendingCancelAvailable(itemId) - this.pendingCancelDelayed(itemId) - this.pendingTrackedAvailable(itemId) - this.pendingTrackedDelayed(itemId));
                },

                physicalItems() {
                    return Object.keys(this.items || {})
                        .map((itemId) => ({ itemId: Number(itemId), meta: this.itemMeta(itemId) }))
                        .filter((item) => !Boolean(item.meta?.is_digital));
                },

                hasPendingTracking() {
                    return this.pendingTrackingCount() > 0;
                },

                previewStatusCode() {
                    const current = String(this.currentStatus || this.statusValue || '');

                    if (!this.hasPendingActions()) {
                        return current;
                    }

                    if (!this.containsPhysical) {
                        return current === '' ? 'fulfilled' : current;
                    }

                    if (this.usesPickup) {
                        const pickupItems = this.physicalItems();
                        if (pickupItems.length === 0) {
                            return current;
                        }

                        const readyQuantity = pickupItems.reduce((carry, item) => carry + this.readyPickup(item.itemId), 0);
                        const collectedQuantity = pickupItems.reduce((carry, item) => carry + this.collectedQuantity(item.itemId), 0);
                        const remainingQuantity = pickupItems.reduce((carry, item) => carry + this.remainingPickup(item.itemId), 0);

                        if (collectedQuantity > 0 && remainingQuantity > 0) {
                            return '{{ \App\Models\StoreOrder::STATUS_PARTIALLY_COLLECTED }}';
                        }

                        if (remainingQuantity <= 0 && collectedQuantity > 0) {
                            return '{{ \App\Models\StoreOrder::STATUS_COLLECTED }}';
                        }

                        if (readyQuantity > 0 && readyQuantity < remainingQuantity) {
                            return '{{ \App\Models\StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION }}';
                        }

                        if (readyQuantity > 0 && readyQuantity >= remainingQuantity) {
                            return '{{ \App\Models\StoreOrder::STATUS_READY_FOR_PICKUP }}';
                        }

                        return '{{ \App\Models\StoreOrder::STATUS_PROCESSING }}';
                    }

                    const physicalItems = this.physicalItems();
                    if (physicalItems.length === 0) {
                        return current;
                    }

                    const allCancelled = physicalItems.every((item) => this.itemProjectedCancelledQuantity(item.itemId) >= Number(item.meta?.ordered_quantity || 0));
                    if (allCancelled) {
                        return '{{ \App\Models\StoreOrder::STATUS_CANCELLED }}';
                    }

                    const anyDispatched = physicalItems.some((item) => this.itemProjectedTrackedQuantity(item.itemId) > 0);
                    const anyRemaining = physicalItems.some((item) => this.itemProjectedOpenQuantity(item.itemId) > 0);

                    if (!anyDispatched) {
                        return '{{ \App\Models\StoreOrder::STATUS_PROCESSING }}';
                    }

                    return anyRemaining
                        ? '{{ \App\Models\StoreOrder::STATUS_PARTIALLY_SHIPPED }}'
                        : '{{ \App\Models\StoreOrder::STATUS_SHIPPED }}';
                },

                previewStatusLabel() {
                    return this.statusLabel(this.previewStatusCode());
                },

                displayStatusCode() {
                    return this.manualStatusTouched
                        ? String(this.statusValue || this.currentStatus || '')
                        : this.previewStatusCode();
                },

                statusLabel(statusCode) {
                    return this.statusLabels?.[String(statusCode)] || String(statusCode || '');
                },

                syncStatusSelection() {
                    if (!this.manualStatusTouched) {
                        this.statusValue = this.previewStatusCode();
                    }
                    this.refreshLiveStatus();
                },

                refreshLiveStatus() {
                    this.liveStatusCode = this.displayStatusCode();
                },

                openStatusEditor() {
                    if (!this.manualStatusTouched) {
                        this.statusValue = this.displayStatusCode();
                    }
                    this.statusEditorOpen = true;
                },

                closeStatusEditor() {
                    this.statusEditorOpen = false;
                },

                openCancel(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.cancelError = '';
                    ui.cancelOpen = true;
                },

                openTracking(itemId, shipmentType = null, quantity = null, parcelNumber = null, trackingMode = null, carrier = null, trackingNumber = null, trackingUrl = null) {
                    const ui = this.ensureItemUi(itemId);
                    ui.trackingError = '';
                    ui.trackingShipmentType = shipmentType !== null && shipmentType !== ''
                        ? String(shipmentType)
                        : this.defaultTrackingShipmentType(itemId);
                    ui.trackingQuantity = quantity !== null
                        ? (quantity === '' ? '' : Number(quantity))
                        : this.defaultTrackingQuantity(itemId, ui.trackingShipmentType);
                    const parcelValue = Number(parcelNumber);
                    ui.trackingParcelNumber = parcelNumber !== null && parcelNumber !== '' && parcelValue > 0
                        ? parcelValue
                        : this.defaultParcelNumber();
                    ui.trackingMode = trackingMode !== null && trackingMode !== ''
                        ? String(trackingMode)
                        : 'none';
                    ui.trackingModeTouched = false;
                    ui.trackingCarrier = carrier !== null && carrier !== undefined ? String(carrier) : '';
                    ui.trackingNumber = trackingNumber !== null && trackingNumber !== undefined ? String(trackingNumber) : '';
                    ui.trackingUrl = trackingUrl !== null && trackingUrl !== undefined ? String(trackingUrl) : '';
                    ui.trackingOpen = true;
                },

                openCollection(itemId, collectionType = null, quantity = null, collectedAt = null, notes = null, pickupState = null) {
                    const ui = this.ensureItemUi(itemId);
                    ui.collectionError = '';
                    ui.pickupState = pickupState !== null && pickupState !== ''
                        ? String(pickupState)
                        : this.defaultPickupState(itemId);
                    ui.collectionType = collectionType !== null && collectionType !== ''
                        ? String(collectionType)
                        : this.defaultCollectionType(itemId);
                    ui.collectionQuantity = quantity !== null
                        ? (quantity === '' ? '' : Number(quantity))
                        : this.defaultCollectionQuantity(itemId, ui.collectionType, ui.pickupState);
                    ui.collectionCollectedAt = collectedAt !== null && collectedAt !== undefined
                        ? String(collectedAt)
                        : this.defaultCollectionCollectedAt();
                    ui.collectionNotes = notes !== null && notes !== undefined ? String(notes) : '';
                    ui.collectionOpen = true;
                },

                defaultTrackingShipmentType(itemId) {
                    return this.remainingAvailable(itemId) > 0
                        ? '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}'
                        : '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED }}';
                },

                defaultTrackingQuantity(itemId, shipmentType = null) {
                    const selectedType = String(shipmentType || this.defaultTrackingShipmentType(itemId));

                    return selectedType === '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED }}'
                        ? this.remainingDelayed(itemId)
                        : this.remainingAvailable(itemId);
                },

                defaultPickupState(itemId) {
                    return this.readyPickup(itemId) > 0
                        ? '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}'
                        : '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}';
                },

                defaultCollectionType(itemId) {
                    return this.remainingPickupAvailable(itemId) > 0
                        ? '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE }}'
                        : '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}';
                },

                defaultCollectionQuantity(itemId, collectionType = null, pickupState = null) {
                    const selectedType = String(collectionType || this.defaultCollectionType(itemId));
                    const selectedState = String(pickupState || this.defaultPickupState(itemId));

                    if (selectedState === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}') {
                        return selectedType === '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}'
                            ? this.readyPickupDelayed(itemId)
                            : this.readyPickupAvailable(itemId);
                    }

                    return selectedType === '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}'
                        ? this.remainingPickupDelayedToReady(itemId)
                        : this.remainingPickupAvailableToReady(itemId);
                },

                defaultCollectionCollectedAt() {
                    return new Date().toISOString().slice(0, 10);
                },

                highestParcelNumber() {
                    const pendingMax = this.pendingActions.reduce((max, action) => Math.max(max, Number(action.parcel_number || 0)), 0);
                    return Math.max(Number(this.maxParcelNumber || 0), pendingMax);
                },

                defaultParcelNumber() {
                    return Math.max(1, this.highestParcelNumber() + 1);
                },

                syncTrackingQuantity(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.trackingQuantity = this.defaultTrackingQuantity(itemId, ui.trackingShipmentType);
                },

                defaultTrackingModeForCarrier(carrier) {
                    const normalized = String(carrier || '').toLowerCase();

                    return normalized.includes('express') ? 'tracking_number' : 'none';
                },

                normalizeTrackingCarrierKey(value) {
                    return String(value || '')
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                },

                trackingLinkTemplateForCarrier(carrier) {
                    const key = this.normalizeTrackingCarrierKey(carrier);
                    if (key === '') {
                        return '';
                    }

                    for (const [configuredCarrier, template] of Object.entries(this.trackingLinkTemplates || {})) {
                        if (this.normalizeTrackingCarrierKey(configuredCarrier) === key) {
                            return String(template || '');
                        }
                    }

                    return '';
                },

                resolveTrackingLink(carrier, trackingNumber) {
                    const template = this.trackingLinkTemplateForCarrier(carrier);
                    const number = String(trackingNumber || '').trim();

                    if (template === '' || number === '') {
                        return '';
                    }

                    const encodedNumber = encodeURIComponent(number);
                    return template
                        .replaceAll('{tracking}', encodedNumber)
                        .replaceAll('@{{tracking_number}}', encodedNumber)
                        .replaceAll('@{{ tracking_number }}', encodedNumber)
                        .replaceAll('{tracking_number}', encodedNumber);
                },

                applyTrackingLinkTemplateToItem(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    const trackingNumber = String(ui.trackingNumber || '').trim();
                    const carrier = String(ui.trackingCarrier || '').trim();
                    const currentUrl = String(ui.trackingUrl || '').trim();

                    if (trackingNumber === '' || carrier === '' || currentUrl !== '') {
                        return;
                    }

                    const resolved = this.resolveTrackingLink(carrier, trackingNumber);
                    if (resolved !== '') {
                        ui.trackingUrl = resolved;
                    }
                },

                applyTrackingLinkTemplateToBulk() {
                    const carrier = String(this.bulkTrackingCarrier || '').trim();
                    const trackingNumber = String(this.bulkTrackingTrackingNumber || '').trim();
                    const currentUrl = String(this.bulkTrackingTrackingUrl || '').trim();

                    if (trackingNumber === '' || carrier === '' || currentUrl !== '') {
                        return;
                    }

                    const resolved = this.resolveTrackingLink(carrier, trackingNumber);
                    if (resolved !== '') {
                        this.bulkTrackingTrackingUrl = resolved;
                    }
                },

                syncItemTrackingModeFromCarrier(itemId, carrier) {
                    const ui = this.ensureItemUi(itemId);

                    if (ui.trackingModeTouched) {
                        return;
                    }

                    ui.trackingMode = this.defaultTrackingModeForCarrier(carrier);
                },

                syncBulkTrackingModeFromCarrier(carrier) {
                    if (this.bulkTrackingModeTouched) {
                        return;
                    }

                    this.bulkTrackingMode = this.defaultTrackingModeForCarrier(carrier);
                },

                toggleDetails(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.detailsOpen = !ui.detailsOpen;
                },

                closeCancel(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.cancelOpen = false;
                },

                closeTracking(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.trackingOpen = false;
                },

                closeCollection(itemId) {
                    const ui = this.ensureItemUi(itemId);
                    ui.collectionOpen = false;
                },

                isSelected(itemId) {
                    return this.selectedItemIds.includes(Number(itemId));
                },

                toggleSelected(itemId, checked) {
                    const id = Number(itemId);

                    if (checked) {
                        if (!this.selectedItemIds.includes(id)) {
                            this.selectedItemIds.push(id);
                        }
                        return;
                    }

                    this.selectedItemIds = this.selectedItemIds.filter((selectedId) => Number(selectedId) !== id);
                },

                selectAllItems(checked) {
                    this.selectedItemIds = checked
                        ? Object.keys(this.items || {}).map((itemId) => Number(itemId))
                        : [];
                },

                clearSelection() {
                    this.selectedItemIds = [];
                },

                selectedItems() {
                    return this.selectedItemIds
                        .map((itemId) => ({ itemId: Number(itemId), meta: this.itemMeta(itemId) }))
                        .filter((item) => Number(item.meta?.open_quantity || 0) > 0);
                },

                openBulkCancel() {
                    if (this.selectedItems().length === 0) {
                        this.bulkCancelError = 'Select at least one item first.';
                        return;
                    }

                    this.bulkCancelError = '';
                    this.bulkCancelReason = '';
                    this.bulkCancelOpen = true;
                },

                closeBulkCancel() {
                    this.bulkCancelOpen = false;
                },

                stageBulkCancel(form) {
                    const reason = String(new FormData(form).get('reason') || '').trim();

                    if (reason === '') {
                        this.bulkCancelError = 'Enter a reason before staging these cancellations.';
                        return;
                    }

                    let stagedCount = 0;
                    this.selectedItems().forEach(({ itemId }) => {
                        const quantity = this.openQuantity(itemId);
                        if (quantity <= 0) {
                            return;
                        }

                        const breakdown = this.cancellationBreakdown(itemId, quantity);
                        this.pendingActions.push({
                            client_id: `queued-${this.nextClientId++}`,
                            type: 'cancel',
                            item_id: Number(itemId),
                            quantity: breakdown.quantity,
                            available_quantity: breakdown.available_quantity,
                            delayed_quantity: breakdown.delayed_quantity,
                            reason,
                            shipment_type: '',
                            carrier: '',
                            tracking_number: '',
                            tracking_url: '',
                            notes: '',
                            dispatched_at: '',
                        });
                        stagedCount++;
                    });

                    if (stagedCount === 0) {
                        this.bulkCancelError = 'No selected items have quantities left to cancel.';
                        return;
                    }

                    this.bulkCancelError = '';
                    this.bulkCancelOpen = false;
                    this.clearSelection();
                    form.reset();
                    this.syncStatusSelection();
                },

                openBulkTracking() {
                    if (this.selectedItems().length === 0) {
                        this.bulkTrackingError = 'Select at least one item first.';
                        return;
                    }

                    this.bulkTrackingError = '';
                    this.bulkTrackingShipmentType = '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}';
                    this.bulkTrackingMode = 'none';
                    this.bulkTrackingModeTouched = false;
                    this.bulkTrackingParcelNumber = this.defaultParcelNumber();
                    this.bulkTrackingCarrier = '';
                    this.bulkTrackingTrackingNumber = '';
                    this.bulkTrackingTrackingUrl = '';
                    this.bulkTrackingNotes = '';
                    this.bulkTrackingDispatchedAt = @js(now()->toDateString());
                    this.bulkTrackingOpen = true;
                },

                closeBulkTracking() {
                    this.bulkTrackingOpen = false;
                },

                stageBulkTracking(form) {
                    const formData = new FormData(form);
                    const shipmentType = String(formData.get('shipment_type') || '{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}');
                    const trackingMode = String(formData.get('tracking_mode') || 'none');
                    const parcelNumber = trackingMode === 'tracking_number'
                        ? 0
                        : Math.max(1, Number(formData.get('parcel_number') || this.defaultParcelNumber()));
                    const carrier = String(formData.get('carrier') || '').trim();
                    let trackingNumber = String(formData.get('tracking_number') || '').trim();
                    let trackingUrl = String(formData.get('tracking_url') || '').trim();
                    const notes = String(formData.get('notes') || '').trim();
                    const dispatchedAt = String(formData.get('dispatched_at') || '').trim();
                    let stagedCount = 0;

                    if (trackingMode === 'tracking_number' && trackingNumber === '') {
                        this.bulkTrackingError = 'Enter a tracking number or choose No Tracking Number.';
                        return;
                    }

                    if (trackingMode === 'none') {
                        trackingNumber = '';
                        trackingUrl = '';
                    }

                    this.selectedItems().forEach(({ itemId }) => {
                        const quantity = shipmentType === 'delayed'
                            ? this.remainingDelayed(itemId)
                            : this.remainingAvailable(itemId);

                        if (quantity <= 0) {
                            return;
                        }

                        this.pendingActions.push({
                            client_id: `queued-${this.nextClientId++}`,
                            type: 'tracking',
                            item_id: Number(itemId),
                            available_quantity: 0,
                            delayed_quantity: 0,
                            reason: '',
                            shipment_type: shipmentType,
                            tracking_mode: trackingMode,
                            parcel_number: parcelNumber,
                            quantity,
                            carrier,
                            tracking_number: trackingNumber,
                            tracking_url: trackingUrl,
                            notes,
                            dispatched_at: dispatchedAt,
                        });
                        stagedCount++;
                    });

                    if (stagedCount === 0) {
                        this.bulkTrackingError = 'No selected items have quantities left to dispatch for that shipment type.';
                        return;
                    }

                    this.bulkTrackingError = '';
                    this.bulkTrackingOpen = false;
                    this.clearSelection();
                    form.reset();
                    this.syncStatusSelection();
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

                remainingPickupAvailable(itemId) {
                    return Number(this.itemMeta(itemId).remaining_pickup_available || 0);
                },

                remainingPickupDelayed(itemId) {
                    return Number(this.itemMeta(itemId).remaining_pickup_delayed || 0);
                },

                remainingPickup(itemId) {
                    return Math.max(0, Number(this.itemMeta(itemId).remaining_pickup || 0));
                },

                readyPickupAvailable(itemId) {
                    const base = Number(this.itemMeta(itemId).ready_pickup_available || 0);
                    return Math.max(0, base + this.pendingPickupReadyAvailable(itemId) - this.pendingPickupCollectedAvailable(itemId));
                },

                readyPickupDelayed(itemId) {
                    const base = Number(this.itemMeta(itemId).ready_pickup_delayed || 0);
                    return Math.max(0, base + this.pendingPickupReadyDelayed(itemId) - this.pendingPickupCollectedDelayed(itemId));
                },

                readyPickup(itemId) {
                    return this.readyPickupAvailable(itemId) + this.readyPickupDelayed(itemId);
                },

                remainingPickupAvailableToReady(itemId) {
                    return Number(this.itemMeta(itemId).remaining_pickup_to_ready || 0) > 0
                        ? Math.max(0, Number(this.itemMeta(itemId).remaining_pickup_available || 0) - this.pendingPickupReadyAvailable(itemId))
                        : 0;
                },

                remainingPickupDelayedToReady(itemId) {
                    return Number(this.itemMeta(itemId).remaining_pickup_to_ready || 0) > 0
                        ? Math.max(0, Number(this.itemMeta(itemId).remaining_pickup_delayed || 0) - this.pendingPickupReadyDelayed(itemId))
                        : 0;
                },

                remainingPickupToReady(itemId) {
                    return this.remainingPickupAvailableToReady(itemId) + this.remainingPickupDelayedToReady(itemId);
                },

                collectedQuantity(itemId) {
                    return Math.max(0, Number(this.itemMeta(itemId).collected_quantity || 0) + this.pendingPickupCollectedAvailable(itemId) + this.pendingPickupCollectedDelayed(itemId));
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
                    return this.usesPickup
                        ? this.remainingPickup(itemId)
                        : this.remainingAvailable(itemId) + this.remainingDelayed(itemId);
                },

                canCancel(itemId) {
                    return this.openQuantity(itemId) > 0;
                },

                canTrack(itemId) {
                    return Boolean(this.itemMeta(itemId).can_track) && this.openQuantity(itemId) > 0;
                },

                canCollect(itemId) {
                    return Boolean(this.itemMeta(itemId).can_collect) && this.readyPickup(itemId) > 0;
                },

                canReady(itemId) {
                    return Boolean(this.itemMeta(itemId).can_ready) && this.remainingPickup(itemId) > 0;
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

                pendingPickupCollectionQuantity(itemId, pickupState, collectionType) {
                    return this.pendingActions
                        .filter((action) => action.type === 'collection'
                            && Number(action.item_id) === Number(itemId)
                            && String(action.pickup_state || '') === String(pickupState)
                            && String(action.collection_type || '') === String(collectionType))
                        .reduce((carry, action) => carry + Number(action.quantity || 0), 0);
                },

                pendingPickupReadyAvailable(itemId) {
                    return this.pendingPickupCollectionQuantity(itemId, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}', '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE }}');
                },

                pendingPickupReadyDelayed(itemId) {
                    return this.pendingPickupCollectionQuantity(itemId, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}', '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}');
                },

                pendingPickupCollectedAvailable(itemId) {
                    return this.pendingPickupCollectionQuantity(itemId, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}', '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE }}');
                },

                pendingPickupCollectedDelayed(itemId) {
                    return this.pendingPickupCollectionQuantity(itemId, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}', '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}');
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
                    const pickupCount = this.pendingActions.filter((action) => action.type === 'collection' && Number(action.item_id) === Number(itemId)).length;
                    const parts = [];

                    if (cancelCount > 0) {
                        parts.push(`${cancelCount} cancellation${cancelCount === 1 ? '' : 's'}`);
                    }

                    if (trackingCount > 0) {
                        parts.push(`${trackingCount} tracking entr${trackingCount === 1 ? 'y' : 'ies'}`);
                    }

                    if (pickupCount > 0) {
                        parts.push(`${pickupCount} pickup action${pickupCount === 1 ? '' : 's'}`);
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
                    this.syncStatusSelection();
                },

                stageTrackingAction(itemId, form) {
                    const ui = this.ensureItemUi(itemId);
                    const formData = new FormData(form);
                    const shipmentType = String(formData.get('shipment_type') || 'available');
                    const trackingMode = String(formData.get('tracking_mode') || 'none');
                    const quantity = Math.max(0, Number(formData.get('quantity') || 0));
                    const parcelNumber = trackingMode === 'tracking_number'
                        ? 0
                        : Math.max(1, Number(formData.get('parcel_number') || this.defaultParcelNumber()));
                    const remaining = shipmentType === 'delayed'
                        ? this.remainingDelayed(itemId)
                        : this.remainingAvailable(itemId);
                    let trackingNumber = String(formData.get('tracking_number') || '').trim();
                    let trackingUrl = String(formData.get('tracking_url') || '').trim();

                    if (quantity <= 0) {
                        ui.trackingError = 'Stage at least one item to dispatch.';
                        return;
                    }

                    if (quantity > remaining) {
                        ui.trackingError = 'Tracking quantity exceeds what is still open after other staged actions.';
                        return;
                    }

                    if (trackingMode === 'tracking_number' && trackingNumber === '') {
                        ui.trackingError = 'Enter a tracking number or choose No Tracking Number.';
                        return;
                    }

                    if (trackingMode === 'none') {
                        trackingNumber = '';
                        trackingUrl = '';
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
                        tracking_mode: trackingMode,
                        parcel_number: parcelNumber,
                        quantity,
                        carrier: String(formData.get('carrier') || '').trim(),
                        tracking_number: trackingNumber,
                        tracking_url: trackingUrl,
                        notes: String(formData.get('notes') || '').trim(),
                        dispatched_at: String(formData.get('dispatched_at') || '').trim(),
                    });

                    ui.trackingError = '';
                    ui.trackingOpen = false;
                    form.reset();
                    this.syncStatusSelection();
                },

                stagePickupAction(itemId, form) {
                    const ui = this.ensureItemUi(itemId);
                    const formData = new FormData(form);
                    const pickupState = String(formData.get('pickup_state') || '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}');
                    const collectionType = String(formData.get('collection_type') || this.defaultCollectionType(itemId));
                    const quantity = Math.max(0, Number(formData.get('quantity') || 0));
                    const collectedAt = String(formData.get('collected_at') || '').trim();
                    const notes = String(formData.get('notes') || '').trim();
                    const remaining = pickupState === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}'
                        ? (collectionType === '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}'
                            ? this.readyPickupDelayed(itemId)
                            : this.readyPickupAvailable(itemId))
                        : (collectionType === '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}'
                            ? this.remainingPickupDelayedToReady(itemId)
                            : this.remainingPickupAvailableToReady(itemId));

                    if (quantity <= 0) {
                        ui.collectionError = 'Stage at least one quantity for this pickup action.';
                        return;
                    }

                    if (quantity > remaining) {
                        ui.collectionError = pickupState === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}'
                            ? 'Collection quantity exceeds what is already ready after other staged actions.'
                            : 'Ready quantity exceeds what is still waiting to be prepared after other staged actions.';
                        return;
                    }

                    if (pickupState === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}' && collectedAt !== '') {
                        const parsedDate = new Date(collectedAt);
                        if (Number.isNaN(parsedDate.getTime())) {
                            ui.collectionError = 'Enter a valid pickup date before staging this collection.';
                            return;
                        }
                    }

                    this.pendingActions.push({
                        client_id: `queued-${this.nextClientId++}`,
                        type: 'collection',
                        item_id: Number(itemId),
                        collection_type: collectionType,
                        pickup_state: pickupState,
                        quantity,
                        collected_at: collectedAt,
                        notes,
                        available_quantity: 0,
                        delayed_quantity: 0,
                        reason: '',
                        shipment_type: '',
                        tracking_mode: '',
                        parcel_number: 0,
                        carrier: '',
                        tracking_number: '',
                        tracking_url: '',
                        dispatched_at: '',
                    });

                    ui.collectionError = '';
                    ui.collectionOpen = false;
                    form.reset();
                    this.syncStatusSelection();
                },

                removePendingAction(clientId) {
                    this.pendingActions = this.pendingActions.filter((action) => action.client_id !== clientId);
                    this.syncStatusSelection();
                },

                clearPendingActions() {
                    this.pendingActions = [];
                    Object.keys(this.itemUi || {}).forEach((itemId) => {
                        const ui = this.ensureItemUi(itemId);
                        ui.cancelError = '';
                        ui.trackingError = '';
                        ui.collectionError = '';
                    });
                    this.syncStatusSelection();
                },

                actionSummary(action) {
                    const item = this.items?.[String(action.item_id)];
                    const title = String(item?.title || 'Order item');
                    const usesTrackingNumber = String(action.tracking_mode || '') === 'tracking_number'
                        || String(action.tracking_number || '').trim() !== ''
                        || String(action.tracking_url || '').trim() !== '';

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

                    if (String(action.type) === 'collection') {
                        const quantity = Number(action.quantity || 0);
                        const pickupLabel = String(action.pickup_state) === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}'
                            ? 'Record'
                            : 'Stage';
                        const stageLabel = String(action.collection_type) === '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}'
                            ? 'backorder'
                            : 'reserved';

                        return `${title} · ${pickupLabel} ${quantity > 0 ? `${quantity} ${stageLabel} item${quantity === 1 ? '' : 's'}` : `${stageLabel} pickup`}`.trim();
                    }

                    const shipmentLabel = String(action.shipment_type) === 'delayed' ? 'Backorder' : 'Reserved';
                    const parcelLabel = ! usesTrackingNumber && Number(action.parcel_number || 0) > 0
                        ? `Parcel #${Number(action.parcel_number)}`
                        : '';

                    return [
                        `${title} · Ship ${Number(action.quantity || 0)} ${shipmentLabel.toLowerCase()} item${Number(action.quantity || 0) === 1 ? '' : 's'}`,
                        parcelLabel,
                    ].filter((part) => part !== '').join(' · ');
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

                    if (String(action.type) === 'collection') {
                        const parts = [];
                        parts.push(String(action.pickup_state) === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}' ? 'Collected' : 'Ready for pickup');
                        parts.push(String(action.collection_type) === '{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}' ? 'Backorder stock' : 'Reserved stock');
                        if (String(action.collected_at || '').trim() !== '') {
                            parts.push(String(action.collected_at).trim());
                        }
                        if (String(action.notes || '').trim() !== '') {
                            parts.push(String(action.notes).trim());
                        }

                        return parts.join(' | ');
                    }

                    const usesTrackingNumber = String(action.tracking_mode || '') === 'tracking_number'
                        || String(action.tracking_number || '').trim() !== ''
                        || String(action.tracking_url || '').trim() !== '';
                    const parcelNumber = ! usesTrackingNumber ? Number(action.parcel_number || 0) : 0;

                    return [
                        String(action.carrier || '').trim(),
                        parcelNumber > 0 ? `Parcel #${parcelNumber}` : '',
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
            <section class="rounded-[1.75rem] border border-gray-200 bg-white p-4 shadow-sm xl:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4 xl:items-center">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Store Order</div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <h2 class="text-2xl font-bold text-gray-900 xl:text-[1.9rem]">{{ $order->order_number }}</h2>
                            <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-600" x-text="statusLabel(displayStatusCode())">{{ $order->statusLabel() }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($linkedQuote)
                            <x-ui.button href="{{ route('admin.quote.edit', $linkedQuote) }}" color="outline">Open Quote</x-ui.button>
                        @endif
                        @if($order->invoice)
                            <x-ui.button href="{{ route('admin.invoice.edit', $order->invoice) }}" color="outline">Open Invoice</x-ui.button>
                        @endif
                        <x-ui.button href="{{ route('admin.shop.order.pick-list.pdf', $order) }}" target="_blank" color="outline">Pick List PDF</x-ui.button>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Order Status</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->statusLabel() }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Placed</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Invoice</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->invoice?->invoice_number ?? '-' }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Customer</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $order->billing_name ?: '-' }}</div>
                        <div class="mt-0.5 text-xs text-gray-500 truncate">{{ $order->billing_email ?: '-' }}</div>
                        <div class="mt-0.5 text-xs text-gray-500 truncate">{{ formatPhoneNumber($order->billing_phone) ?: '-' }}</div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 {{ $order->contains_physical ? 'md:grid-cols-2 md:items-start' : '' }}">
                    @if($order->contains_physical)
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $order->usesPickup() ? 'Collection Details' : 'Delivery Details' }}</div>
                                <div class="mt-4 space-y-2 text-sm text-gray-700">
                                    <div class="font-semibold text-gray-900">
                                        {{ $isQuoteRequested ? 'To be quoted' : ($order->usesPickup() ? 'Pick up / Collection' : ($order->shipping_method ?: 'Shipping')) }}
                                    </div>
                                    @if(!$isQuoteRequested && $order->usesPickup())
                                        <div class="text-xs text-gray-500">
                                        @if((string) $order->status === \App\Models\StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION)
                                            Some items are ready for collection.
                                        @elseif((string) $order->status === \App\Models\StoreOrder::STATUS_READY_FOR_PICKUP)
                                            Ready for pickup now.
                                        @elseif((string) $order->status === \App\Models\StoreOrder::STATUS_PARTIALLY_COLLECTED)
                                            Some items have been collected.
                                        @elseif((string) $order->status === \App\Models\StoreOrder::STATUS_COLLECTED)
                                            Collected.
                                        @else
                                            Customer will be contacted for collection.
                                        @endif
                                        </div>
                                    @elseif(!$isQuoteRequested && $order->shipping_package_summary)
                                    <div class="text-xs text-gray-500">{{ $order->shipping_package_summary }}</div>
                                @endif
                            </div>
                            @if(!$order->usesPickup())
                                <div class="mt-4 space-y-1 text-sm text-gray-700">
                                    @foreach($order->shippingAddressLines() as $line)
                                        <div>{{ $line }}</div>
                                    @endforeach
                                </div>
                            @endif
                            @if(!$order->usesPickup() && !$isQuoteRequested && !empty($shippingBreakdown['shipments']))
                                <div class="mt-4">
                                    @include('shop.partials.shipping-breakdown', [
                                        'shipments' => $shippingBreakdown['shipments'],
                                    ])
                                </div>
                            @elseif($isQuoteRequested)
                                <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
                                    Shipping has not been selected yet. Final delivery options will be set on the quote.
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 {{ $order->contains_physical ? '' : 'xl:col-span-2' }}">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Order Summary</div>
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
                        <div class="mt-4 flex items-center justify-between gap-4 border-t border-gray-200 pt-4 text-xs text-gray-500">
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

                @if((string) $order->status === \App\Models\StoreOrder::STATUS_QUOTE_REQUESTED)
                    <div class="mt-6 rounded-3xl border border-sky-200 bg-sky-50 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-700">Manual Quote Request</div>
                                <div class="mt-1 text-lg font-semibold text-sky-950">Legacy quick quote</div>
                                <div class="mt-2 text-sm text-sky-900">Older quote-requested orders can still be converted here with one final shipping amount. New manual quote requests are created as standard quotes so you can add multiple shipping options before converting them.</div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.shop.order.quote.send', $order) }}" class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,220px),minmax(0,1fr),auto]">
                            @csrf
                            <x-ui.input
                                name="shipping_amount"
                                label="Shipping Amount (inc GST)"
                                type="number"
                                min="0"
                                step="0.01"
                                :value="old('shipping_amount', number_format((float) $order->shipping_amount, 2, '.', ''))"
                            />
                            <x-ui.input
                                name="email_message"
                                label="Email Message"
                                type="textarea"
                                :value="old('email_message', '')"
                            />
                            <div class="flex items-end">
                                <x-ui.button type="submit">Create Quote</x-ui.button>
                            </div>
                        </form>
                    </div>
                @endif

            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr),minmax(340px,0.8fr)]">
                <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Items</h2>
                    @if($isQuoteRequested)
                        <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            Fulfilment actions stay hidden until the shipping quote is accepted and this request becomes a normal order.
                        </div>
                    @endif
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            @php
                                $cancelBag = $errors->getBag('cancelItem_'.$item->id);
                                $trackingBag = $errors->getBag('trackingItem_'.$item->id);
                                $collectionBag = $errors->getBag('collectionItem_'.$item->id);
                                $orderedQty = max(0, (int) $item->quantity);
                                $availableTotal = $item->availableQuantityTotal();
                                $delayedTotal = $item->delayedQuantityTotal();
                                $remainingAvailable = $item->remainingAvailableQuantity();
                                $remainingDelayed = $item->remainingDelayedQuantity();
                                $remainingPickupAvailable = $item->remainingPickupAvailableQuantity();
                                $remainingPickupDelayed = $item->remainingPickupDelayedQuantity();
                                $remainingPickup = $item->remainingPickupQuantity();
                                $readyPickupAvailable = $item->readyPickupAvailableQuantity();
                                $readyPickupDelayed = $item->readyPickupDelayedQuantity();
                                $readyPickup = $item->readyPickupQuantity();
                                $remainingPickupToReady = $item->remainingPickupToReadyQuantity();
                                $remainingTotal = $item->remainingFulfillableQuantity();
                                $cancelledTotal = $item->cancelledQuantity();
                                $trackedTotal = $item->trackedQuantity();
                                $collectedTotal = $item->collectedQuantity();
                                $dispatchedTotal = $trackedTotal;
                                $reservedQty = $item->reservedInventory();
                                $canTrack = $order->contains_physical && !$order->usesPickup() && !$item->isDigital() && ($remainingAvailable > 0 || $remainingDelayed > 0);
                                $canReady = $pickupCollectionOpen && !$item->isDigital() && $remainingPickupToReady > 0;
                                $canCollect = $pickupCollectionOpen && !$item->isDigital() && $readyPickup > 0;
                                $cancelOpen = $cancelBag->isNotEmpty();
                                $trackingOpen = $trackingBag->isNotEmpty();
                                $collectionOpen = $collectionBag->isNotEmpty();
                                $cancelAvailableValue = $cancelOpen ? old('available_quantity', '') : '';
                                $cancelDelayedValue = $cancelOpen ? old('delayed_quantity', '') : '';
                                $cancelReasonValue = $cancelOpen ? old('reason', '') : '';
                                $trackingStageValue = $trackingOpen ? old('shipment_type', \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE) : \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE;
                                $trackingModeValue = $trackingOpen ? old('tracking_mode', 'none') : 'none';
                                $trackingQtyValue = $trackingOpen ? old('quantity', '') : '';
                                $trackingParcelValue = $trackingOpen ? old('parcel_number', (string) $defaultParcelNumber) : (string) $defaultParcelNumber;
                                $trackingCarrierValue = $trackingOpen ? old('carrier', '') : '';
                                $trackingDispatchValue = $trackingOpen ? old('dispatched_at', now()->toDateString()) : now()->toDateString();
                                $trackingNumberValue = $trackingOpen ? old('tracking_number', '') : '';
                                $trackingUrlValue = $trackingOpen ? old('tracking_url', '') : '';
                                $trackingNotesValue = $trackingOpen ? old('notes', '') : '';
                                $collectionTypeValue = $collectionOpen ? old('collection_type', \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE) : \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE;
                                $collectionStateValue = $collectionOpen ? old('pickup_state', \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY) : \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY;
                                $collectionQtyValue = $collectionOpen ? old('quantity', '') : '';
                                $collectionNotesValue = $collectionOpen ? old('notes', '') : '';
                                $collectionCollectedAtValue = $collectionOpen ? old('collected_at', now()->toDateString()) : now()->toDateString();
                            @endphp
                                    <div id="item-{{ $item->id }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm" x-init="@if($cancelOpen) openCancel({{ $item->id }}); @endif @if($trackingOpen) openTracking({{ $item->id }}, @js($trackingStageValue), @js($trackingQtyValue), @js($trackingParcelValue), @js($trackingModeValue), @js($trackingCarrierValue), @js($trackingNumberValue), @js($trackingUrlValue)); @endif @if($collectionOpen) openCollection({{ $item->id }}, @js($collectionTypeValue), @js($collectionQtyValue), @js($collectionCollectedAtValue), @js($collectionNotesValue), @js($collectionStateValue)); @endif">
                                @php
                                    $itemSku = trim((string) ($item->variant_sku ?: $item->product_sku ?: $item->variant?->sku ?: $item->product?->sku));
                                @endphp
                                <div class="space-y-4">
                                    <div class="grid gap-4 grid-cols-[auto_minmax(0,1fr)_auto] items-start">
                                        <label class="flex items-start pt-2">
                                            <input
                                                type="checkbox"
                                                class="h-8 w-8 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                                x-bind:checked="isSelected({{ $item->id }})"
                                                x-on:change="toggleSelected({{ $item->id }}, $event.target.checked)"
                                            >
                                        </label>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-start gap-4">
                                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-sky-200 bg-sky-100 text-lg font-bold text-sky-800 shadow-sm">
                                                    {{ $loop->iteration }}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-semibold text-gray-900">{{ $item->displayTitle() }}</div>
                                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                                                        <span>Qty {{ $item->quantity }}</span>
                                                        @if($itemSku !== '')
                                                            <span>SKU {{ $itemSku }}</span>
                                                        @endif
                                                        <span>{{ \App\Models\Product::productTypeLabel((string) $item->product_type) }}</span>
                                                    </div>
                                                    @if($item->is_preorder)
                                                        <div class="mt-1 text-sm text-amber-800">Pre-order · Estimated shipping {{ $item->preorderShippingEstimateLabel() ?: 'to be confirmed' }}</div>
                                                    @elseif($item->isBackorder())
                                                        <div class="mt-1 text-sm text-sky-800">
                                                            @if((int) $item->available_now_quantity > 0)
                                                                {{ (int) $item->available_now_quantity }} shipped first, {{ (int) $item->delayed_quantity }} delayed{{ $item->delayedShippingEstimateLabel() ? ' from '.$item->delayedShippingEstimateLabel() : '' }}
                                                            @else
                                                                Backorder · Expected shipping {{ $item->delayedShippingEstimateLabel() ?: 'to be confirmed' }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right md:pt-1">
                                            <div class="font-semibold text-gray-900">${{ number_format((float) $item->line_total_amount, 2) }}</div>
                                            <div class="text-xs text-gray-500">GST ${{ number_format((float) $item->line_gst_amount, 2) }}</div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3 ml-12 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="flex min-w-0 flex-1 flex-wrap gap-2 text-[11px] font-semibold text-gray-700">
                                            <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1">
                                                <i class="fa-solid fa-clipboard-list text-gray-400" aria-hidden="true"></i>
                                                <span>Ordered <span x-text="orderedQuantity({{ $item->id }})">{{ $orderedQty }}</span></span>
                                            </span>
                                            @if($order->usesPickup())
                                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-800">
                                                    <i class="fa-solid fa-check text-emerald-500" aria-hidden="true"></i>
                                                    <span>Ready <span x-text="readyPickup({{ $item->id }})">{{ $readyPickup }}</span></span>
                                                </span>
                                                <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1">
                                                    <i class="fa-solid fa-box text-sky-500" aria-hidden="true"></i>
                                                    <span>To prepare <span x-text="remainingPickupToReady({{ $item->id }})">{{ $remainingPickupToReady }}</span></span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1">
                                                    <i class="fa-solid fa-box text-sky-500" aria-hidden="true"></i>
                                                    <span>Open <span x-text="openQuantity({{ $item->id }})">{{ $remainingTotal }}</span></span>
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-rose-800">
                                                <i class="fa-solid fa-circle-xmark text-rose-500" aria-hidden="true"></i>
                                                <span>Cancelled <span x-text="cancelledQuantity({{ $item->id }})">{{ $cancelledTotal }}</span></span>
                                            </span>
                                            @if($order->usesPickup())
                                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-800">
                                                    <i class="fa-solid fa-box-check text-emerald-500" aria-hidden="true"></i>
                                                    <span>Collected <span x-text="collectedQuantity({{ $item->id }})">{{ $collectedTotal }}</span></span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-800">
                                                    <i class="fa-solid fa-truck-fast text-emerald-500" aria-hidden="true"></i>
                                                    <span>Shipped <span x-text="dispatchedQuantity({{ $item->id }})">{{ $dispatchedTotal }}</span></span>
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-800">
                                                <i class="fa-solid fa-warehouse text-amber-500" aria-hidden="true"></i>
                                                <span>Reserved <span x-text="reservedQuantity({{ $item->id }})">{{ $reservedQty }}</span></span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-sky-800">
                                                <i class="fa-solid fa-hourglass-half text-sky-500" aria-hidden="true"></i>
                                                <span>Backorder <span x-text="remainingDelayed({{ $item->id }})">{{ $remainingDelayed }}</span></span>
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap flex-col sm:flex-row gap-2 justify-end">
                                            @unless($itemActionsLocked)
                                                <x-ui.button type="button" class="w-full sm:w-auto" color="danger-outline" x-on:click="openCancel({{ $item->id }})" x-bind:disabled="!canCancel({{ $item->id }})">Cancel Items</x-ui.button>
                                                @unless($order->usesPickup())
                                                    <x-ui.button type="button" class="w-full sm:w-auto" color="primary-outline" x-on:click="openTracking({{ $item->id }})" x-bind:disabled="!canTrack({{ $item->id }})">Add Shipment</x-ui.button>
                                                @endunless
                                            @endunless
                                            @if($pickupCollectionOpen)
                                                @if($item->remainingPickupToReadyQuantity() > 0)
                                                    <x-ui.button type="button" class="w-full sm:w-auto" color="primary-outline" x-on:click="openCollection({{ $item->id }}, null, defaultCollectionQuantity({{ $item->id }}, null, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}'), null, null, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}')" x-bind:disabled="!canReady({{ $item->id }})">Mark Ready</x-ui.button>
                                                @endif
                                                @if($item->readyPickupQuantity() > 0)
                                                    <x-ui.button type="button" class="w-full sm:w-auto" color="primary-outline" x-on:click="openCollection({{ $item->id }}, null, defaultCollectionQuantity({{ $item->id }}, null, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}'), null, null, '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}')" x-bind:disabled="!canCollect({{ $item->id }})">Record Collection</x-ui.button>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    @if($item->downloads->isNotEmpty())
                                        <div class="text-xs text-sky-700">{{ $item->downloads->count() }} download{{ $item->downloads->count() === 1 ? '' : 's' }}</div>
                                    @endif

                                    @if($isQuoteRequested)
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                            This item is waiting on the customer shipping quote. Dispatch and cancellation controls stay hidden until the quote is accepted.
                                        </div>
                                    @endif
                                </div>

                                @unless($itemActionsLocked)
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
                                @endunless

                                @unless($itemActionsLocked)
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
                                                                <select
                                                                    name="shipment_type"
                                                                    class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"
                                                                    x-model="itemUi['{{ $item->id }}'].trackingShipmentType"
                                                                    x-on:change="syncTrackingQuantity({{ $item->id }})"
                                                                >
                                                                    <option value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}" @selected($trackingStageValue === \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE) x-text="'Reserved items (' + remainingAvailable({{ $item->id }}) + ' remaining)'">Reserved items ({{ $remainingAvailable }} remaining)</option>
                                                                    <option value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED }}" @selected($trackingStageValue === \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED) x-text="'Backorder items (' + remainingDelayed({{ $item->id }}) + ' remaining)'">Backorder items ({{ $remainingDelayed }} remaining)</option>
                                                                </select>
                                                            @elseif($remainingDelayed > 0)
                                                                <input type="hidden" name="shipment_type" x-bind:value="itemUi['{{ $item->id }}'].trackingShipmentType" />
                                                                <div class="rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900" x-text="'Backorder items (' + remainingDelayed({{ $item->id }}) + ' remaining)'">Backorder items ({{ $remainingDelayed }} remaining)</div>
                                                            @else
                                                                <input type="hidden" name="shipment_type" x-bind:value="itemUi['{{ $item->id }}'].trackingShipmentType" />
                                                                <div class="rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900" x-text="'Reserved items (' + remainingAvailable({{ $item->id }}) + ' remaining)'">Reserved items ({{ $remainingAvailable }} remaining)</div>
                                                            @endif
                                                            @if($trackingBag->first('shipment_type'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('shipment_type') }}</div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Items in this shipment</label>
                                                            <input
                                                                type="number"
                                                                name="quantity"
                                                                min="1"
                                                                max="{{ max($remainingAvailable, $remainingDelayed) }}"
                                                                x-bind:max="Math.max(remainingAvailable({{ $item->id }}), remainingDelayed({{ $item->id }}))"
                                                                x-model.number="itemUi['{{ $item->id }}'].trackingQuantity"
                                                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"
                                                            />
                                                            @if($trackingBag->first('quantity'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('quantity') }}</div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking mode</label>
                                                            <select
                                                                name="tracking_mode"
                                                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"
                                                                x-model="itemUi['{{ $item->id }}'].trackingMode"
                                                                x-on:change="itemUi['{{ $item->id }}'].trackingModeTouched = true"
                                                            >
                                                                <option value="none" @selected($trackingModeValue === 'none')>No Tracking Number</option>
                                                                <option value="tracking_number" @selected($trackingModeValue === 'tracking_number')>Tracking Number</option>
                                                            </select>
                                                            @if($trackingBag->first('tracking_mode'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('tracking_mode') }}</div>
                                                            @endif
                                                            <div class="mt-1 text-xs text-gray-500">Choose whether this parcel should keep only a parcel number or also record a courier tracking number.</div>
                                                        </div>
                                                        <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].trackingMode === 'none'" x-cloak>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Parcel number</label>
                                                            <input
                                                                type="number"
                                                                name="parcel_number"
                                                                min="1"
                                                                step="1"
                                                                x-model.number="itemUi['{{ $item->id }}'].trackingParcelNumber"
                                                                value="{{ $trackingParcelValue }}"
                                                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"
                                                            />
                                                            @if($trackingBag->first('parcel_number'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('parcel_number') }}</div>
                                                            @endif
                                                            <div class="mt-1 text-xs text-gray-500">Use the same number for items sharing one parcel.</div>
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
                                                                x-model="itemUi['{{ $item->id }}'].trackingCarrier"
                                                                x-on:blur="syncItemTrackingModeFromCarrier({{ $item->id }}, $event.target.value); applyTrackingLinkTemplateToItem({{ $item->id }})"
                                                                x-on:input="syncItemTrackingModeFromCarrier({{ $item->id }}, $event.target.value)"
                                                                class="mb-0!"
                                                            />
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Dispatch date</label>
                                                            <input type="date" name="dispatched_at" value="{{ $trackingDispatchValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" />
                                                            @if($trackingBag->first('dispatched_at'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('dispatched_at') }}</div>
                                                            @endif
                                                        </div>
                                                        <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].trackingMode === 'tracking_number'" x-cloak>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking number</label>
                                                            <input
                                                                type="text"
                                                                name="tracking_number"
                                                                value="{{ $trackingNumberValue }}"
                                                                x-model="itemUi['{{ $item->id }}'].trackingNumber"
                                                                x-on:blur="applyTrackingLinkTemplateToItem({{ $item->id }})"
                                                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"
                                                                placeholder="Optional"
                                                            />
                                                            @if($trackingBag->first('tracking_number'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('tracking_number') }}</div>
                                                            @endif
                                                        </div>
                                                        <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].trackingMode === 'tracking_number'" x-cloak>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking link</label>
                                                            <input
                                                                type="url"
                                                                name="tracking_url"
                                                                value="{{ $trackingUrlValue }}"
                                                                x-model="itemUi['{{ $item->id }}'].trackingUrl"
                                                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"
                                                                placeholder="Optional"
                                                            />
                                                            @if($trackingBag->first('tracking_url'))
                                                                <div class="mt-1 text-xs text-rose-700">{{ $trackingBag->first('tracking_url') }}</div>
                                                            @endif
                                                            <div class="mt-1 text-xs text-gray-500">Leave blank to auto-generate from the courier template when one exists.</div>
                                                        </div>
                                                    </div>
                                                    <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].trackingMode === 'none'" x-cloak class="mt-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                                                        No tracking number will be recorded for this parcel. The parcel number still keeps the shipment grouped.
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
                                                            Pickup orders use Ready for Pickup.
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
                                @endunless

                                @if($pickupCollectionOpen)
                                <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].collectionOpen" x-cloak class="fixed inset-0 z-[180] bg-black/55" x-on:click.self="closeCollection({{ $item->id }})" x-on:keydown.escape.window="closeCollection({{ $item->id }})">
                                    <div class="flex min-h-full items-center justify-center p-4">
                                        <div class="w-full max-w-2xl rounded-3xl bg-white shadow-xl">
                                            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">Pickup Action</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Mark this item ready for pickup or record a collection against the quantity that has already been prepared.</p>
                                                </div>
                                                <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeCollection({{ $item->id }})" aria-label="Close collection modal">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.shop.order.item.collection.store', ['storeOrder' => $order, 'storeOrderItem' => $item]) }}" class="px-6 py-5" x-on:submit.prevent="stagePickupAction({{ $item->id }}, $event.target)">
                                                @csrf
                                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Ready to collect</div>
                                                        <div class="mt-1 text-lg font-semibold text-gray-900" x-text="remainingPickupAvailable({{ $item->id }})">{{ $remainingPickupAvailable }}</div>
                                                    </div>
                                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Still to prepare</div>
                                                        <div class="mt-1 text-lg font-semibold text-gray-900" x-text="remainingPickupDelayed({{ $item->id }})">{{ $remainingPickupDelayed }}</div>
                                                    </div>
                                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Collected</div>
                                                        <div class="mt-1 text-lg font-semibold text-emerald-900" x-text="collectedQuantity({{ $item->id }})">{{ $collectedTotal }}</div>
                                                    </div>
                                                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-700">Open</div>
                                                        <div class="mt-1 text-lg font-semibold text-sky-900" x-text="remainingPickup({{ $item->id }})">{{ $remainingPickup }}</div>
                                                    </div>
                                                </div>
                                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Pickup action</label>
                                                        <select name="pickup_state" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-sky-300 focus:outline-none focus:ring-0" x-model="itemUi['{{ $item->id }}'].pickupState" x-on:change="itemUi['{{ $item->id }}'].collectionQuantity = defaultCollectionQuantity({{ $item->id }}, itemUi['{{ $item->id }}'].collectionType, itemUi['{{ $item->id }}'].pickupState)">
                                                            <option value="{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY }}" @selected($collectionStateValue === \App\Models\StoreOrderItemCollection::PICKUP_STATE_READY)>Mark Ready for Pickup</option>
                                                            <option value="{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}" @selected($collectionStateValue === \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED)>Record Collection</option>
                                                        </select>
                                                        @if($collectionBag->first('pickup_state'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $collectionBag->first('pickup_state') }}</div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Collection stage</label>
                                                        <select name="collection_type" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-sky-300 focus:outline-none focus:ring-0">
                                                            <option value="{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE }}" @selected($collectionTypeValue === \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE)>Available stock</option>
                                                            <option value="{{ \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED }}" @selected($collectionTypeValue === \App\Models\StoreOrderItemCollection::COLLECTION_TYPE_DELAYED)>Backorder stock</option>
                                                        </select>
                                                        @if($collectionBag->first('collection_type'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $collectionBag->first('collection_type') }}</div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Quantity</label>
                                                        <input type="number" name="quantity" min="1" max="{{ $remainingPickup }}" x-model="itemUi['{{ $item->id }}'].collectionQuantity" x-bind:max="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].pickupState === '{{ \App\Models\StoreOrderItemCollection::PICKUP_STATE_COLLECTED }}' ? readyPickup({{ $item->id }}) : remainingPickupToReady({{ $item->id }})" value="{{ $collectionQtyValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-sky-300 focus:outline-none focus:ring-0" />
                                                        @if($collectionBag->first('quantity'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $collectionBag->first('quantity') }}</div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Action date</label>
                                                        <input type="date" name="collected_at" value="{{ $collectionCollectedAtValue }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-sky-300 focus:outline-none focus:ring-0" />
                                                        @if($collectionBag->first('collected_at'))
                                                            <div class="mt-1 text-xs text-rose-700">{{ $collectionBag->first('collected_at') }}</div>
                                                        @endif
                                                    </div>
                                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Pickup note</div>
                                                        <div class="mt-2">Add pickup actions to the queue, then save the order once you are done staging items.</div>
                                                    </div>
                                                </div>
                                                <div class="mt-4">
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Notes</label>
                                                    <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-sky-300 focus:outline-none focus:ring-0" placeholder="Optional pickup notes">{{ $collectionNotesValue }}</textarea>
                                                    @if($collectionBag->first('notes'))
                                                        <div class="mt-1 text-xs text-rose-700">{{ $collectionBag->first('notes') }}</div>
                                                    @endif
                                                    @if($collectionBag->first('item'))
                                                        <div class="mt-1 text-xs text-rose-700">{{ $collectionBag->first('item') }}</div>
                                                    @endif
                                                </div>
                                                <div x-show="itemUi['{{ $item->id }}'] && itemUi['{{ $item->id }}'].collectionError" x-cloak class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                                    <span x-text="itemUi['{{ $item->id }}'] ? itemUi['{{ $item->id }}'].collectionError : ''"></span>
                                                </div>
                                                <div class="mt-5 flex flex-wrap justify-end gap-3">
                                                    <x-ui.button type="button" color="outline" x-on:click="closeCollection({{ $item->id }})">Close</x-ui.button>
                                                    <x-ui.button type="submit" color="primary">Stage Pickup Action</x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif

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
                        @unless($itemActionsLocked)
                        <div x-show="bulkCancelOpen" x-cloak class="fixed inset-0 z-[180] bg-black/55" x-on:click.self="closeBulkCancel()" x-on:keydown.escape.window="closeBulkCancel()">
                            <div class="flex min-h-full items-center justify-center p-4">
                                <div class="w-full max-w-2xl rounded-3xl bg-white shadow-xl">
                                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Stage Bulk Cancellation</h3>
                                            <p class="mt-1 text-sm text-gray-600">This will stage a cancellation for every selected item using each row’s current open quantity.</p>
                                        </div>
                                        <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeBulkCancel()" aria-label="Close bulk cancellation modal">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                    <form class="px-6 py-5" x-on:submit.prevent="stageBulkCancel($event.target)">
                                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Selected items</div>
                                            <div class="mt-2 space-y-1">
                                                <template x-for="item in selectedItems()" :key="item.itemId">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span x-text="item.meta.title || ('Item #' + item.itemId)"></span>
                                                        <span class="text-xs text-gray-500" x-text="'Open ' + Number(item.meta.open_quantity || 0)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Reason</label>
                                            <textarea name="reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-rose-300 focus:outline-none focus:ring-0" placeholder="Why are these items being cancelled together?"></textarea>
                                        </div>
                                        <div x-show="bulkCancelError !== ''" x-cloak class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                            <span x-text="bulkCancelError"></span>
                                        </div>
                                        <div class="mt-5 flex flex-wrap justify-end gap-3">
                                            <x-ui.button type="button" color="outline" x-on:click="closeBulkCancel()">Close</x-ui.button>
                                            <x-ui.button type="submit" color="danger">Stage Cancellations</x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div x-show="bulkTrackingOpen" x-cloak class="fixed inset-0 z-[180] bg-black/55" x-on:click.self="closeBulkTracking()" x-on:keydown.escape.window="closeBulkTracking()">
                            <div class="flex min-h-full items-center justify-center p-4">
                                <div class="w-full max-w-2xl rounded-3xl bg-white shadow-xl">
                                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Stage Bulk Shipment Entry</h3>
                                            <p class="mt-1 text-sm text-gray-600">This will stage one shipment entry per selected item using the current open quantity for the chosen shipment stage.</p>
                                        </div>
                                        <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeBulkTracking()" aria-label="Close bulk shipment modal">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                    <form class="px-6 py-5" x-on:submit.prevent="stageBulkTracking($event.target)">
                                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Selected items</div>
                                            <div class="mt-2 space-y-1">
                                                <template x-for="item in selectedItems()" :key="item.itemId">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span x-text="item.meta.title || ('Item #' + item.itemId)"></span>
                                                        <span class="text-xs text-gray-500" x-text="'Open ' + Number(item.meta.open_quantity || 0)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Shipment stage</label>
                                                <select name="shipment_type" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0">
                                                    <option value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE }}">Reserved items</option>
                                                    <option value="{{ \App\Models\StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED }}">Backorder items</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking mode</label>
                                                <select name="tracking_mode" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" x-model="bulkTrackingMode" x-on:change="bulkTrackingModeTouched = true">
                                                    <option value="none">No Tracking Number</option>
                                                    <option value="tracking_number">Tracking Number</option>
                                                </select>
                                                <div class="mt-1 text-xs text-gray-500">Choose whether the parcels are recorded with a tracking number or only a parcel number.</div>
                                            </div>
                                            <x-ui.input
                                                name="carrier"
                                                label="Courier"
                                                placeholder="Australia Post"
                                                :suggestions="$carrierSuggestions ?? []"
                                                showSuggestionsOnFocus="true"
                                                x-model="bulkTrackingCarrier"
                                                x-on:blur="syncBulkTrackingModeFromCarrier($event.target.value); applyTrackingLinkTemplateToBulk()"
                                                x-on:input="syncBulkTrackingModeFromCarrier($event.target.value)"
                                                class="mb-0!"
                                            />
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Dispatch date</label>
                                                <input type="date" name="dispatched_at" value="{{ now()->toDateString() }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" />
                                            </div>
                                            <div x-show="bulkTrackingMode === 'none'" x-cloak>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Parcel number</label>
                                                <input type="number" name="parcel_number" min="1" step="1" x-model.number="bulkTrackingParcelNumber" value="{{ $defaultParcelNumber }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" />
                                                <div class="mt-1 text-xs text-gray-500">Use the same number for items sharing one parcel.</div>
                                            </div>
                                            <div x-show="bulkTrackingMode === 'tracking_number'" x-cloak>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking number</label>
                                                <input type="text" name="tracking_number" x-model="bulkTrackingTrackingNumber" x-on:blur="applyTrackingLinkTemplateToBulk()" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" placeholder="Optional" />
                                            </div>
                                            <div x-show="bulkTrackingMode === 'tracking_number'" x-cloak class="sm:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Tracking link</label>
                                                <input type="url" name="tracking_url" x-model="bulkTrackingTrackingUrl" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0" placeholder="Optional" />
                                                <div class="mt-1 text-xs text-gray-500">Leave blank to auto-generate from the courier template when one exists.</div>
                                            </div>
                                            <div x-show="bulkTrackingMode === 'none'" x-cloak class="sm:col-span-2 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                                                No tracking number will be recorded for these parcels. The parcel number still keeps each staged shipment grouped.
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-700">Notes</label>
                                                <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-emerald-300 focus:outline-none focus:ring-0"></textarea>
                                            </div>
                                        </div>
                                        <div x-show="bulkTrackingError !== ''" x-cloak class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                            <span x-text="bulkTrackingError"></span>
                                        </div>
                                        <div class="mt-5 flex flex-wrap justify-end gap-3">
                                            <x-ui.button type="button" color="outline" x-on:click="closeBulkTracking()">Close</x-ui.button>
                                            <x-ui.button type="submit">Stage Shipments</x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endunless
                        @unless($itemActionsLocked)
                            <div class="my-4 flex flex-wrap flex-col sm:flex-row items-center justify-end gap-3">
                                <x-ui.button type="button" class="w-full sm:w-auto" color="danger-outline" x-on:click="openBulkCancel()" x-bind:disabled="selectedItemIds.length === 0">Cancel Selected</x-ui.button>
                                @if(! $order->usesPickup())
                                    <x-ui.button type="button" class="w-full sm:w-auto" color="primary-outline" x-on:click="openBulkTracking()" x-bind:disabled="selectedItemIds.length === 0">Add Shipment</x-ui.button>
                                @endif
                                <x-ui.button type="button" class="w-full sm:w-auto" color="outline" x-on:click="clearSelection()" x-bind:disabled="selectedItemIds.length === 0">Clear Selection</x-ui.button>
                            </div>
                        @endunless
                    </section>

                </div>

                <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Order Changes</h2>
                                <p class="mt-1 text-sm text-gray-600">Status, staged item changes, and notes are saved together.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                            <button
                                type="button"
                                class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] transition"
                                :class="sidebarTab === 'changes' ? 'border border-gray-900 bg-gray-900 text-white' : 'border border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white hover:text-gray-900'"
                                x-on:click="sidebarTab = 'changes'"
                            >
                                Changes
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] transition"
                                :class="sidebarTab === 'private-notes' ? 'border border-gray-900 bg-gray-900 text-white' : 'border border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white hover:text-gray-900'"
                                x-on:click="sidebarTab = 'private-notes'"
                            >
                                Private Notes
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] transition"
                                :class="sidebarTab === 'public-notes' ? 'border border-gray-900 bg-gray-900 text-white' : 'border border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white hover:text-gray-900'"
                                x-on:click="sidebarTab = 'public-notes'"
                            >
                                Public Notes
                            </button>
                        </div>

                        <form id="order-status-form" method="POST" action="{{ route('admin.shop.order.update', $order) }}" class="mt-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="item_actions_json" x-bind:value="actionsJson()" />
                            <input type="hidden" name="status" x-bind:value="displayStatusCode()" x-bind:disabled="statusEditorOpen" />
                            @if($errors->has('item_actions_json'))
                                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                    {{ $errors->first('item_actions_json') }}
                                </div>
                            @endif

                            <div x-show="sidebarTab === 'changes'" x-cloak class="space-y-4">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Order Status</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-900">
                                            <span x-text="statusLabel(displayStatusCode())">{{ $order->statusLabel() }}</span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            @if($order->usesPickup())
                                                Pickup readiness is managed from the item actions.
                                            @elseif($order->status === \App\Models\StoreOrder::STATUS_PROCESSING)
                                                Leave this closed to let shipment and cancellation changes set the status automatically.
                                            @else
                                                {{ $order->statusLabel() }}.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @unless($order->usesPickup())
                                    <div class="mt-4">
                                        <x-ui.button type="button" color="outline" x-on:click="statusEditorOpen ? closeStatusEditor() : openStatusEditor()">
                                            <span x-show="!statusEditorOpen" x-cloak>Edit</span>
                                            <span x-show="statusEditorOpen" x-cloak>Done</span>
                                        </x-ui.button>
                                    </div>
                                    <div x-show="statusEditorOpen" x-cloak class="mt-4">
                                        <x-ui.select name="status" label="Status" x-model="statusValue" x-bind:disabled="!statusEditorOpen" x-on:change="manualStatusTouched = true; refreshLiveStatus()">
                                            @foreach(\App\Models\StoreOrder::STATUSES as $status)
                                                <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ (new \App\Models\StoreOrder(['status' => $status]))->statusLabel() }}</option>
                                            @endforeach
                                        </x-ui.select>
                                        <div class="text-xs text-gray-500">Leave this closed to let shipment and cancellation changes set the status automatically.</div>
                                    </div>
                                @endunless
                            </div>

                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900">Queued Order Changes</h3>
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
                                        <div class="mt-4 space-y-3">
                                            <template x-for="action in pendingActions" :key="action.client_id">
                                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
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
                                        No item changes are staged yet. Use the item actions on the left to queue cancellations, tracking updates, or pickup actions.
                                    </div>
                                </div>
                            </div>

                            <div x-show="sidebarTab === 'private-notes'" x-cloak class="space-y-4">
                                <x-ui.input type="textarea" name="notes" label="Private Notes" :value="$order->notes ?? ''" />
                            </div>

                            <div x-show="sidebarTab === 'public-notes'" x-cloak class="space-y-4">
                                <x-ui.input type="textarea" name="public_notes" label="Public Notes" :value="$order->public_notes ?? ''" info="Visible to the customer from their order page. Use for progress updates or collection instructions." />
                            </div>

                            <div class="mt-4 text-right">
                                <x-ui.button type="submit">
                                    <span>Save All Changes</span>
                                </x-ui.button>
                            </div>
                        </form>
                    </section>
                </aside>
            </div>
        </div>
    </x-container>
</x-layout>
