window.SM = window.SM || {};

window.SM.productLineEditor = (catalogProducts = [], includeTicket = false) => ({
    catalogProducts,
    itemTypeOptions: [
        { value: 'product', label: 'Store Product', icon: 'fa-box' },
        { value: 'shipping', label: 'Shipping', icon: 'fa-truck' },
        { value: 'multi_workshop', label: 'Multi Workshop Delivery', icon: 'fa-layer-group' },
        { value: 'workshop', label: 'Workshop Delivery', icon: 'fa-chalkboard-user' },
        { value: 'travel', label: 'Travel Fee', icon: 'fa-route' },
        ...(includeTicket ? [{ value: 'ticket', label: 'Ticket', icon: 'fa-ticket' }] : []),
        { value: 'custom', label: 'Custom', icon: 'fa-pen-to-square' },
    ],
    defaultDescriptionForKind(kind) {
        return {
            shipping: 'Shipping',
            workshop: 'Charged per hour, per seat', multi_workshop: 'Charged per hour, per seat',
            travel: 'Travel Fee',
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
            item.source_variant_id = '0';
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

        this.serializeLineItems();
    },
    selectItemType(index, kind) {
        const item = this.lineItems[index];
        if (!item || this.isLocked) {
            return;
        }

        item.kind = kind;
        if (kind === 'multi_workshop') {
            item.auto_pricing = true;
            if (!item.workshops?.length) SM.addWorkshopRow(item);
        }
        if (kind === 'travel') SM.hydrateTravelLine(item);
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
