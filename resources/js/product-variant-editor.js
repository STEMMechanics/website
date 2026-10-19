window.SM = window.SM || {};

window.SM.productVariantEditor = (config = {}) => ({
    variantEditor: true,
    nextVariantKey: 0,
    duplicatingVariantKey: null,
    variantCopyError: '',
    orderedVariantRows() {
        const group = variant => variant.is_active ? (variant.is_base ? 0 : 1) : 2;
        return this.variants.map((variant, index) => ({ variant, index }))
            .sort((a, b) => group(a.variant) - group(b.variant)
                || Number(a.variant.sort_order || 0) - Number(b.variant.sort_order || 0)
                || a.index - b.index);
    },
    normalizeVariantOrder() {
        this.orderedVariantRows().forEach(({ variant }, index) => { variant.sort_order = index; });
    },
    initVariantEditor() {
        this.variants.forEach((variant, index) => {
            variant.editor_key = variant.id ? `saved-${variant.id}` : `new-${++this.nextVariantKey}`;
            variant.is_active = variant.is_active === true || Number(variant.is_active) === 1;
            variant.is_base = variant.is_base === true || Number(variant.is_base) === 1;
            variant.original_option = variant.original_option === true || Number(variant.original_option) === 1;
            variant.open = !variant.id || (this.variantErrors || []).some(key => key.startsWith(`variants.${index}.`));
        });
        const selected = this.variants.find(variant => variant.is_base && variant.is_active);
        this.selectBaseVariant(selected || this.variants.find(variant => variant.is_active));
    },
    selectBaseVariant(selected) {
        if (selected && !selected.is_active) return;
        this.variants.forEach(variant => { variant.is_base = variant === selected && variant.is_active; });
        this.normalizeVariantOrder();
    },
    nextActiveVariant(current) {
        const ordered = this.orderedVariantRows().map(row => row.variant);
        const index = ordered.indexOf(current);
        const after = [...ordered.slice(index + 1), ...ordered.slice(0, index + 1)];
        return after.find(variant => variant.is_active) || null;
    },
    variantActiveChanged(variant) {
        if (!variant.is_active) variant.open = false;
        if (variant.is_base && !variant.is_active) {
            this.selectBaseVariant(this.nextActiveVariant(variant));
        } else if (variant.is_active && !this.variants.some(row => row.is_base)) {
            this.selectBaseVariant(variant);
        } else {
            this.normalizeVariantOrder();
        }
    },
    variantMoveDisabledReason(variant, direction) {
        if (variant.is_base) return 'The base variant always appears first.';
        const ordered = this.orderedVariantRows().map(row => row.variant);
        const target = ordered[ordered.indexOf(variant) + direction];
        if (!target) return direction < 0 ? 'This variant is already first.' : 'This variant is already last.';
        if (target.is_base) return 'The base variant always appears first.';
        if (target.is_active !== variant.is_active) return 'Inactive variants stay below active variants.';
        return '';
    },
    moveVariant(index, direction) {
        const variant = this.variants[index];
        if (!variant || ![-1, 1].includes(direction) || this.variantMoveDisabledReason(variant, direction)) return;
        const ordered = this.orderedVariantRows().map(row => row.variant);
        const target = ordered[ordered.indexOf(variant) + direction];
        [variant.sort_order, target.sort_order] = [target.sort_order, variant.sort_order];
        this.normalizeVariantOrder();
    },
    variantDeletionDisabledReason(variant) {
        if (variant.is_base) return 'Choose another base variant before deleting this variant.';
        return variant.deletion_disabled_reason || (variant.original_option
            ? 'The original variant is kept for existing links. Deactivate it to retire it.' : '');
    },
    removeVariant(index) {
        const variant = this.variants[index];
        if (!variant || this.variantDeletionDisabledReason(variant)) return;
        this.variants.splice(index, 1);
        this.normalizeVariantOrder();
    },
    variantCopyName(source, index) {
        const base = String(source.name || '').trim() || `Variant ${index + 1}`;
        const names = new Set(this.variants.map(variant => String(variant.name || '').trim().toLowerCase()));
        let suffix = 1;
        let name;
        do {
            const marker = ` - COPY${suffix > 1 ? `-${suffix}` : ''}`;
            name = Array.from(base).slice(0, 120 - marker.length).join('').trimEnd() + marker;
            suffix++;
        } while (names.has(name.toLowerCase()));
        return name;
    },
    async duplicateVariant(index) {
        const source = this.variants[index];
        if (!source || this.duplicatingVariantKey !== null) return null;
        this.duplicatingVariantKey = source.editor_key;
        this.variantCopyError = '';
        try {
            const copy = JSON.parse(JSON.stringify(source));
            const response = await fetch(config.copySkuUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                body: JSON.stringify({
                    sku: copy.sku || '',
                    reserved_skus: [this.baseSku || '', ...this.variants.map(variant => variant.sku || '')],
                }),
                signal: AbortSignal.timeout(10000),
            });
            if (!response.ok) throw new Error('SKU check failed');
            const { sku } = await response.json();
            if (!this.variants.includes(source)) return null;
            // The form may have changed while the saved SKUs were being checked.
            if (!sku || [this.baseSku, ...this.variants.map(variant => variant.sku)]
                .some(value => String(value || '').trim().toLowerCase() === sku.toLowerCase())) {
                throw new Error('SKU changed');
            }
            Object.assign(copy, {
                id: null,
                name: this.variantCopyName(copy, index),
                sku,
                is_base: false,
                deletion_disabled_reason: '',
                inventory_quantity: copy.inventory_quantity === '' || copy.inventory_quantity == null ? '' : 0,
                awaiting_fulfilment: 0,
                reserved_quantity: 0,
                sort_order: Number(source.sort_order || 0) + 0.5,
            });
            this.variants.push(this.prepareNewVariant(copy));
            this.normalizeVariantOrder();
            return copy;
        } catch {
            this.variantCopyError = 'Could not duplicate this variant. Please try again.';
            return null;
        } finally {
            this.duplicatingVariantKey = null;
        }
    },
    prepareNewVariant(variant) {
        variant.editor_key = `new-${++this.nextVariantKey}`;
        variant.open = true;
        variant.original_option = false;
        variant.is_base = !!variant.is_active && !this.variants.some(row => row.is_base && row.is_active);
        return variant;
    },
});
