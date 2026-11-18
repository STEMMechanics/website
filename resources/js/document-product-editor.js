window.SM = window.SM || {};

window.SM.lineItemsEditor = (isLocked = false, pricingPlans = [], selectedPricingVersionId = null, workshopCatalog = [], locationCatalog = [], multiWorkshopDefaults = { hours: 1, seats: 10 }) => {
    const itemKeys = new WeakMap();
    let nextItemKey = 0;

    return {
        pricingPlans,
        workshopCatalog,
        locationCatalog,
        multiWorkshopDefaults,
        selectedPricingVersionId: selectedPricingVersionId === null || selectedPricingVersionId === ''
            ? null
            : Number(selectedPricingVersionId),
        mobileLineItemIndex: null,
        roundingItem: null,
        roundingProposed: 0,
        roundingRequested: 0,
        initializeLineItems() {
            if (!isLocked) this.lineItems.forEach(item => window.SM.initializeWorkshopText(item));
            this.serializeLineItems();
        },
        handleLineChange(item) {
            window.SM.updateWorkshopLine(item);
            this.serializeLineItems();
        },
        handleLineGstChange(item) {
            window.SM.changeLineGst(item);
            this.handleLineChange(item);
        },
        resetWorkshopNotes(item) {
            window.SM.refreshWorkshopNotes(item);
            this.serializeLineItems();
        },
        markWorkshopNotesEdited(item) {
            window.SM.markWorkshopNotesEdited(item);
            this.serializeLineItems();
        },
        commitWorkshopRow(item, row) {
            window.SM.commitWorkshopSpacing(row);
            this.handleLineChange(item);
        },
        updateTravelHours(item, row) {
            row.travel_hours_user_changed = true;
            row.travel_hours_defaulted = false;
            row.details_json.travel ??= {};
            row.details_json.travel.hours_user_changed = true;
            row.details_json.travel.hours_defaulted = false;
            this.handleLineChange(item);
        },
        moveWorkshopRowAndSave(item, rowIndex, direction) {
            window.SM.moveWorkshopRow(item, rowIndex, direction);
            this.serializeLineItems();
        },
        addWorkshopRowAndSave(item) {
            window.SM.addWorkshopRow(item, this.multiWorkshopDefaults);
            window.SM.updateWorkshopLine(item);
            this.serializeLineItems();
        },
        addTravelRowAndSave(item) {
            window.SM.addTravelRow(item);
            window.SM.updateWorkshopLine(item);
            this.serializeLineItems();
        },
        mobileLineItemKind() {
            const item = this.mobileLineItemIndex === null ? null : this.lineItems[this.mobileLineItemIndex];
            return item ? item.kind : '';
        },
        roundingItemName() {
            const workshop = this.roundingItem && this.roundingItem.details_json
                ? this.roundingItem.details_json.workshop
                : null;
            return workshop && workshop.name || (this.roundingItem && this.roundingItem.description)
                || (this.roundingItem && typeof this.itemTypeLabel === 'function' ? this.itemTypeLabel(this.roundingItem.kind) : '') || '';
        },
        roundingChangeText() {
            const original = this.roundingOriginal ? Number(this.roundingOriginal.gross) : 0;
            const preview = this.roundingPreview ? Number(this.roundingPreview.gross) : 0;
            return (preview >= original ? '+' : '−') + '$' + Math.abs(preview - original).toFixed(2);
        },
        get selectedPricingPlan() {
            return this.pricingPlans.find(plan => Number(plan.id) === Number(this.selectedPricingVersionId)) || null;
        },
        priceRoundingInfo(item) { return window.SM.priceRoundingForLine(item); },
        priceRoundingTitle(item) {
            const quote = this.priceRoundingInfo(item);
            if (!quote) return '';
            if (quote.adjusted && quote.clean) return `Rounded from $${quote.original.unitInc.toFixed(2)} to $${quote.current.unitInc.toFixed(2)} including GST. Click to review.`;
            return `This rate does not break down into exact cents. Suggested: $${quote.suggested.unitInc.toFixed(2)} including GST ($${quote.suggested.unitEx.toFixed(2)} ex GST). Click to review.`;
        },
        finishUnitPrice(item, index) {
            const saved = window.SM.lineAmounts(item).saved;
            if (!isLocked && !saved) window.SM.roundWorkshopPrice(item);
            this.normalizeLineItem(index, 'unit_price_inc_tax');
            this.serializeLineItems();
        },
        applyPendingPriceRounding() {
            if (isLocked) return;
            this.lineItems.forEach(item => window.SM.roundWorkshopPrice(item));
            this.serializeLineItems();
        },
        applyCurrentGst() {
            if (isLocked) return;
            this.lineItems.forEach(item => {
                if (Math.abs(window.SM.lineTaxRate(item) - window.SM.currentGstRate()) > 0.00000001) window.SM.applyCurrentGst(item);
            });
            this.serializeLineItems();
        },
        openPriceRounding(item) {
            if (isLocked || !item) return;
            const quote = this.priceRoundingInfo(item);
            if (!quote) return;
            this.roundingItem = item;
            this.roundingRequested = quote.original.unitInc;
            this.roundingProposed = quote.suggested.unitInc;
            this.$nextTick(() => {
                const dialog = document.getElementById('finance-price-rounding');
                if (dialog && !dialog.open) dialog.showModal();
            });
        },
        get roundingPreview() {
            if (!this.roundingItem) return null;
            const rate = window.SM.lineTaxRate(this.roundingItem);
            return window.SM.cleanPriceQuote(this.roundingProposed, this.roundingItem.quantity, rate)?.suggested;
        },
        get roundingOriginal() {
            if (!this.roundingItem) return null;
            const rate = window.SM.lineTaxRate(this.roundingItem);
            return window.SM.cleanPriceQuote(this.roundingRequested, this.roundingItem.quantity, rate)?.current;
        },
        moveRoundingPrice(direction) {
            const rate = window.SM.lineTaxRate(this.roundingItem);
            const quote = window.SM.cleanPriceQuote(this.roundingProposed, this.roundingItem.quantity, rate);
            this.roundingProposed = direction > 0 ? quote.next.unitInc : quote.previous.unitInc;
        },
        applyRoundingPrice() {
            if (isLocked || !this.roundingItem) return;
            window.SM.applyCleanPrice(this.roundingItem, this.roundingProposed, this.roundingRequested);
            this.serializeLineItems();
            document.getElementById('finance-price-rounding')?.close();
        },
        quantityIsCalculated(item) {
            return ['multi_workshop', 'multi_travel'].includes(item.kind) || (item.kind === 'workshop' && !!item.workshop_hours && !!item.workshop_seats);
        },
        lineItemKey(item) {
            if (!itemKeys.has(item)) itemKeys.set(item, ++nextItemKey);
            return itemKeys.get(item);
        },
        moveLineItem(index, direction) {
            const destination = index + direction;
            if (isLocked || !Number.isInteger(index) || ![-1, 1].includes(direction)
                || index < 0 || index >= this.lineItems.length
                || destination < 0 || destination >= this.lineItems.length) return;

            const editingItem = this.mobileLineItemIndex === null ? null : this.lineItems[this.mobileLineItemIndex];
            [this.lineItems[index], this.lineItems[destination]] = [this.lineItems[destination], this.lineItems[index]];
            if (editingItem) this.mobileLineItemIndex = this.lineItems.indexOf(editingItem);
            this.serializeLineItems();
        },
        mobileWorkshopRows(item) {
            if (item?.kind === 'multi_workshop') return (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []).filter(row => !SM.isWorkshopSpacing(row));
            if (item?.kind === 'multi_travel') return (item.travel_rows ?? item.details_json?.multi_travel?.rows ?? []).filter(row => !SM.isWorkshopSpacing(row));
            return [];
        },
        mobileWorkshopRowsLabel(item) {
            const rows = this.mobileWorkshopRows(item);
            const label = item?.kind === 'multi_travel' ? 'Location' : 'Workshop';
            return `${rows.length} ${label}${rows.length === 1 ? '' : 's'}`;
        },
        mobileWorkshopSummary(item) {
            return this.mobileWorkshopRows(item).map(row => String(row.description || '').trim()).filter(Boolean).join(', ');
        },
        openMobileLineItem(index) {
            this.mobileLineItemIndex = index;
            this.$nextTick(() => {
                const dialog = this.$refs?.mobileLineItemDialog || document.querySelector('.sm-invoice-line-item-dialog');
                if (dialog && !dialog.open) dialog.showModal();
            });
        },
        closeMobileLineItem() {
            const dialog = this.$refs?.mobileLineItemDialog || document.querySelector('.sm-invoice-line-item-dialog[open]');
            if (dialog?.open) dialog.close();
            this.mobileLineItemIndex = null;
        },
        async requestLineItemRemovalConfirmation(title, content) {
            const dialog = this.$refs?.mobileLineItemDialog || document.querySelector('.sm-invoice-line-item-dialog[open]');
            const reopenDialog = Boolean(dialog?.open);
            const editingIndex = this.mobileLineItemIndex;
            if (reopenDialog) this.closeMobileLineItem();

            let confirmed = false;
            try {
                const result = await window.SM.confirm(title, content, 'Remove');
                confirmed = Boolean(result?.isConfirmed);
            } finally {
                if (reopenDialog && !confirmed) this.openMobileLineItem(editingIndex);
            }

            return confirmed;
        },
        async confirmRemoveLineItem(index) {
            if (isLocked || !Number.isInteger(index) || index < 0 || index >= this.lineItems.length
                || !window.SM || typeof window.SM.confirm !== 'function') return false;

            const confirmed = await this.requestLineItemRemovalConfirmation(
                'Remove line item?',
                'Are you sure you want to remove this line item? This cannot be undone.'
            );
            if (!confirmed) return false;

            if (this.mobileLineItemIndex === index) this.closeMobileLineItem();
            this.lineItems.splice(index, 1);
            if (this.mobileLineItemIndex !== null && this.mobileLineItemIndex > index) this.mobileLineItemIndex -= 1;
            this.serializeLineItems();
            return true;
        },
        async confirmRemoveLineItemRow(item, collectionName, rowIndex) {
            const rows = item?.[collectionName];
            if (isLocked || !Array.isArray(rows) || !Number.isInteger(rowIndex) || rowIndex < 0 || rowIndex >= rows.length
                || !['workshops', 'travel_rows'].includes(collectionName)
                || !window.SM || typeof window.SM.confirm !== 'function') return false;

            const isTravel = collectionName === 'travel_rows';
            const label = isTravel ? 'travel location' : 'workshop';
            const confirmed = await this.requestLineItemRemovalConfirmation(
                `Remove ${label}?`,
                `Are you sure you want to remove this ${label} from the line item?`
            );
            if (!confirmed) return false;

            rows.splice(rowIndex, 1);
            window.SM.updateWorkshopLine(item);
            this.serializeLineItems();
            return true;
        },
    };
};

window.SM.lineTypePicker = () => ({
    open: false,
    menuTop: 0,
    menuLeft: 0,
    positionAndToggle(element) {
        const rect = element.getBoundingClientRect();
        this.menuTop = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - 320));
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - 264));
        this.open = !this.open;
    },
    choose(element, index, value) {
        const scope = Alpine.$data(element);
        if (typeof scope.selectItemType === 'function') scope.selectItemType(index, value);
        scope.expanded = true;
        this.open = false;
    },
});

window.SM.mobileLineItemDetails = element => {
    const parent = Alpine.$data(element.parentElement);
    return {
        expanded: true,
        get item() {
            return parent.lineItems[parent.mobileLineItemIndex];
        },
        get index() {
            return parent.mobileLineItemIndex;
        },
    };
};

window.SM.productLineEditor = (catalogProducts = [], includeTicket = false) => ({
    catalogProducts,
    itemTypeOptions: [
        { value: 'product', label: 'Store Product', icon: 'fa-box' },
        { value: 'shipping', label: 'Shipping', icon: 'fa-truck' },
        { value: 'multi_workshop', label: 'Multi Workshop Delivery', icon: 'fa-layer-group' },
        { value: 'multi_travel', label: 'Multi Travel Fee', icon: 'fa-route' },
        { value: 'workshop', label: 'Workshop Delivery', icon: 'fa-chalkboard-user' },
        { value: 'travel', label: 'Travel Fee', icon: 'fa-route' },
        ...(includeTicket ? [{ value: 'ticket', label: 'Ticket', icon: 'fa-ticket' }] : []),
        { value: 'custom', label: 'Custom', icon: 'fa-pen-to-square' },
    ],
    defaultDescriptionForKind(kind) {
        return {
            shipping: 'Shipping',
            workshop: 'Charged per hour, per seat', multi_workshop: 'Charged per hour, per seat',
            travel: 'Travel Fee', multi_travel: 'Travel Fee',
        }[kind] ?? '';
    },
    findProduct(productId) {
        return this.catalogProducts.find((product) => parseInt(product.id || 0) === parseInt(productId || 0)) || null;
    },
    productSearchState(item) {
        if (!item.product_search || typeof item.product_search !== 'object') {
            const product = this.findProduct(item.source_id);
            item.product_search = {
                query: product?.title || '',
                open: false,
                selectedIndex: 0,
                top: 0,
                left: 0,
                width: 0,
                maxHeight: 288,
                activeId: null,
            };
        }

        return item.product_search;
    },
    productSuggestions(item) {
        const query = String(this.productSearchState(item).query || '').trim().toLocaleLowerCase();
        if (!query) {
            return [];
        }

        return this.catalogProducts
            .map((product) => {
                const title = String(product.title || '').toLocaleLowerCase();
                const sku = String(product.sku || '').toLocaleLowerCase();
                const titleIndex = title.indexOf(query);
                const skuIndex = sku.indexOf(query);
                if (titleIndex < 0 && skuIndex < 0) {
                    return null;
                }

                const rank = title === query || sku === query ? 0
                    : title.startsWith(query) || sku.startsWith(query) ? 1
                        : titleIndex >= 0 ? 2 : 3;

                return { product, rank };
            })
            .filter(Boolean)
            .sort((a, b) => a.rank - b.rank || String(a.product.title || '').localeCompare(String(b.product.title || '')))
            .slice(0, 8)
            .map(({ product }) => product);
    },
    positionProductSuggestions(item, input) {
        if (!(input instanceof HTMLElement)) {
            return;
        }

        const rect = input.getBoundingClientRect();
        const viewportPadding = 8;
        const state = this.productSearchState(item);
        const width = Math.min(rect.width, window.innerWidth - viewportPadding * 2);
        const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - width - viewportPadding));
        const spaceBelow = window.innerHeight - rect.bottom - viewportPadding - 4;
        const spaceAbove = rect.top - viewportPadding - 4;
        const opensBelow = spaceBelow >= 160 || spaceBelow >= spaceAbove;
        const availableHeight = Math.max(80, Math.min(288, opensBelow ? spaceBelow : spaceAbove));

        state.top = opensBelow ? rect.bottom + 4 : Math.max(viewportPadding, rect.top - availableHeight - 4);
        state.left = left;
        state.width = width;
        state.maxHeight = availableHeight;
    },
    openProductSuggestions(item, input, activeId = null) {
        const state = this.productSearchState(item);
        state.open = true;
        state.selectedIndex = 0;
        state.activeId = activeId;
        this.positionProductSuggestions(item, input);
    },
    closeProductSuggestions(item, activeId = null) {
        const state = this.productSearchState(item);
        if (activeId === null || state.activeId === activeId) {
            state.open = false;
        }
    },
    moveProductSuggestion(item, step, input, activeId = null) {
        const state = this.productSearchState(item);
        if (!state.open || state.activeId !== activeId) {
            this.openProductSuggestions(item, input, activeId);
            return;
        }

        const count = this.productSuggestions(item).length;
        if (!count) {
            return;
        }

        state.selectedIndex = (state.selectedIndex + step + count) % count;
    },
    clearProductSelection(item) {
        item.source_id = '';
        item.saved_pricing = null;
        item.tax_rate = item.gst_applicable === false ? 0 : window.SM.currentGstRate();
        item.source_type = null;
        item.source_variant_id = 0;
        item.store_context = null;
        item.description = '';
        item.product_selection_changed = false;
        item.saved_pricing = null;
        item.unit_price = '0.00';
        item.unit_price_ex_tax = '0.00';
        item.unit_price_inc_tax = '0.00';
        if (item.details_json && typeof item.details_json === 'object') {
            delete item.details_json.store_context;
            delete item.details_json.variant_id;
            delete item.details_json.inclusive_unit_price;
        }
    },
    searchProducts(item, index, value, input, activeId = null) {
        const state = this.productSearchState(item);
        if (this.findProduct(item.source_id)) {
            this.clearProductSelection(item);
        }

        state.query = String(value || '');
        state.open = true;
        state.selectedIndex = 0;
        state.activeId = activeId;
        this.positionProductSuggestions(item, input);
        this.serializeLineItems();
    },
    chooseProductSuggestion(item, index, product) {
        if (!product || this.isLocked) {
            return;
        }

        const state = this.productSearchState(item);
        if (parseInt(item.source_id || 0, 10) !== parseInt(product.id || 0, 10)) {
            item.source_id = String(product.id);
            item.source_variant_id = String(product.default_variant_id || 0);
        }
        state.query = String(product.title || '');
        state.open = false;
        state.selectedIndex = 0;
        this.applyProductSelection(index);
    },
    confirmProductSuggestion(item, index) {
        const suggestions = this.productSuggestions(item);
        if (!suggestions.length) {
            return;
        }

        const state = this.productSearchState(item);
        const selected = suggestions[Math.min(state.selectedIndex, suggestions.length - 1)];
        this.chooseProductSuggestion(item, index, selected);
    },
    normalizeSelectionValue(value, fallback = '') {
        const numeric = parseInt(value || 0, 10);
        if (Number.isNaN(numeric) || numeric < 0) {
            return fallback;
        }

        return String(numeric);
    },
    itemTypeFor(kind) {
        return this.itemTypeOptions.find((option) => option.value === kind) || this.itemTypeOptions[this.itemTypeOptions.length - 1];
    },
    itemTypeLabel(kind) {
        return this.itemTypeFor(kind)?.label || 'Custom';
    },
    itemTypeIcon(kind) {
        return this.itemTypeFor(kind)?.icon || 'fa-pen-to-square';
    },
    variantOptions(item) {
        const product = this.findProduct(item.source_id);
        if (!product || !product.has_option_choices) {
            return [];
        }

        if (product.uses_variant_records) {
            return Array.isArray(product.variants) ? product.variants : [];
        }

        return [
            {
                id: 0,
                name: product.base_option_name || product.title,
                sku: product.sku || '',
                summary: product.summary || '',
            },
            ...(Array.isArray(product.variants) ? product.variants : []),
        ];
    },
    displayProductTitle(product, variant = null) {
        if (!product) {
            return '';
        }

        if (variant && variant.name) {
            return `${product.title} - ${variant.name}`;
        }

        return product.title || '';
    },
    applyKind(index) {
        const item = this.lineItems[index];
        if (!item) {
            return;
        }

        item.source_id = '';
        item.source_type = null;
        item.store_context = null;
        delete item.details_json?.store_context;
        delete item.details_json?.variant_id;
        item.source_variant_id = 0;
        item.product_search = null;
        item.description = this.defaultDescriptionForKind(item.kind);
        item.notes = item.kind === 'custom' ? item.notes : '';
        if (item.kind === 'product') {
            item.description = '';
            item.notes = '';
        }

        if (['workshop', 'multi_workshop'].includes(item.kind)) {
            item.details_json ??= {};
            delete item.details_json.workshop_notes_mode;
            SM.initializeWorkshopText(item);
        }

        this.serializeLineItems();
    },
    selectItemType(index, kind) {
        const item = this.lineItems[index];
        if (!item || this.isLocked) {
            return;
        }

        const wasKind = item.kind;
        const seedTravelRows = kind === 'multi_travel' && wasKind !== 'multi_travel' && !item.travel_rows?.length;
        if (kind === 'workshop' && item.kind !== 'workshop') item.legacy_workshop = false;
        item.kind = kind;
        if (kind === 'multi_workshop') {
            item.auto_pricing = true;
            if (!item.workshops?.length) SM.addWorkshopRow(item, this.multiWorkshopDefaults);
        }
        if (kind === 'multi_travel') {
            item.auto_pricing = true;
            if (seedTravelRows) {
                const workshopItem = this.lineItems.slice(0, index).reverse().find(line => line.kind === 'multi_workshop');
                if (!SM.copyWorkshopRowsToTravel(item, workshopItem, this.workshopCatalog, this.locationCatalog)) SM.addTravelRow(item);
            } else if (!item.travel_rows?.length) {
                SM.addTravelRow(item);
            }
        }
        if (kind === 'travel') {
            if (wasKind !== 'travel') {
                item.travel_hours = '';
                item.travel_hours_defaulted = false;
                item.travel_hours_user_changed = false;
                item.quantity = 0;
                delete item.travel_units;
                delete item.location_id;
                if (item.details_json) delete item.details_json.travel;
            }
            SM.hydrateTravelLine(item);
        }
        SM.updateWorkshopLine(item);
        this.applyKind(index);
        SM.updateWorkshopLine(item);
        this.serializeLineItems();
    },
    applyProductSelection(index) {
        const item = this.lineItems[index];
        const product = this.findProduct(item?.source_id);
        if (!item || this.isLocked) return;
        if (!product) {
            this.clearProductSelection(item);
            this.serializeLineItems();
            return;
        }

        const variant = this.variantOptions(item).find((entry) => parseInt(entry.id || 0) === parseInt(item.source_variant_id || 0)) || null;
        item.description = this.displayProductTitle(product, variant);
        item.saved_pricing = null;
        item.product_selection_changed = true;
        item.auto_pricing = false;
        item.tax_rate = Number(product.tax_rate || 0);
        item.source_type = 'App\\Models\\Product';
        item.details_json = { ...(item.details_json || {}), variant_id: Number(variant?.id || 0) || null };
        delete item.details_json.inclusive_unit_price;
        item.gst_applicable = parseFloat(product.tax_rate || 0) > 0;
        item.unit_price_inc_tax = SM.formatUnitPrice(variant?.price ?? product.price ?? 0);
        this.serializeLineItems();
    },
    invoiceProductItem(item) {
        if (item.kind !== 'product' || !item.product_selection_changed) return item;
        const product = this.findProduct(item.source_id);
        if (!product) return item;
        const variant = this.variantOptions(item).find(entry => Number(entry.id) === Number(item.source_variant_id || 0));
        const variantId = Number(variant?.id || 0) || null;
        return {
            ...item,
            source_type: 'App\\Models\\Product',
            source_id: Number(product.id),
            details_json: {
                ...(item.details_json || {}),
                variant_id: variantId,
                store_context: {
                    ...(item.details_json?.store_context || {}),
                    product_id: Number(product.id), variant_id: variantId,
                    product_title: product.title, variant_name: variant?.name || product.base_option_name || '',
                    product_sku: product.sku || '', variant_sku: variant?.sku || '', tax_rate: Number(product.tax_rate || 0),
                },
            },
        };
    },
});
