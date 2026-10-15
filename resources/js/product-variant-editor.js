window.SM = window.SM || {};

window.SM.productVariantEditor = () => ({
    variantEditor: true,
    nextVariantKey: 0,
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
    prepareNewVariant(variant) {
        variant.editor_key = `new-${++this.nextVariantKey}`;
        variant.open = true;
        variant.original_option = false;
        variant.is_base = !this.variants.some(row => row.is_base && row.is_active);
        return variant;
    },
});
