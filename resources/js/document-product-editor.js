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
            this.applyKind(index);
            item.unit_price_inc_tax = '0.00';
            item.saved_pricing = null;
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
