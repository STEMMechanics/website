/** Keep quantity semantics identical in recipes, blueprints and workshops. */
export function productQuantityUnit(product, row = {}) {
    if (product?.product_type === 'material') return 'item';
    if (['item', 'pack'].includes(row.quantity_unit)) return row.quantity_unit;
    const hasLength = String(row.length ?? '').trim() !== '';

    return product?.has_material_measurements && (hasLength || row.cut_size_id) ? 'item' : 'pack';
}

export function defaultProductQuantityUnit(product) {
    return product?.product_type === 'material' || product?.has_material_measurements ? 'item' : 'pack';
}

export function changeProductQuantityUnit(row, value, product = null) {
    row.quantity_unit = value;
    if (value === 'pack') {
        row.cut_size_id = null;
        row.length = null;
        row.length_unit = null;
    } else if (!row.length_unit) {
        row.length_unit = product?.measure_unit || 'mm';
    }
}

export function productQuantityChoice(product, row = {}) {
    if (productQuantityUnit(product, row) === 'item') return 'item';
    const variantId = Number(row.product_variant_id) || 0;

    return variantId > 0 ? `variant:${variantId}` : 'pack';
}

export function changeProductQuantityChoice(row, value, product) {
    if (value === 'item') {
        row.product_variant_id = null;
        changeProductQuantityUnit(row, 'item', product);
        return;
    }
    const variantId = value.startsWith('variant:') ? Number(value.slice(8)) : null;
    const activeVariant = product?.variants?.some(variant => Number(variant.id) === variantId);
    const retainedVariant = Number(row.product_variant_id) === variantId && product?.inactive_variants?.some(variant => Number(variant.id) === variantId);
    if (value !== 'pack' && !activeVariant && !retainedVariant) return;

    row.product_variant_id = variantId;
    changeProductQuantityUnit(row, 'pack', product);
}

export function productPreparationFields(model, catalog = []) {
    return {
        preparationItem: model,
        preparationCatalog: Array.isArray(catalog) ? catalog : [],
        get linkedMaterial() {
            return this.preparationCatalog.find(option => String(option.id) === String(this.preparationItem.product_id));
        },
        get quantityUnit() {
            return productQuantityUnit(this.linkedMaterial, this.preparationItem);
        },
        get quantityChoice() {
            return productQuantityChoice(this.linkedMaterial, this.preparationItem);
        },
        get selectableVariants() {
            const inactive = this.linkedMaterial?.inactive_variants?.find(variant => String(variant.id) === String(this.preparationItem.product_variant_id));

            return [...(this.linkedMaterial?.variants || []), ...(inactive ? [{ ...inactive, name: inactive.name + ' (inactive)' }] : [])];
        },
        get hasQuantityChoice() {
            return this.linkedMaterial?.product_type !== 'material'
                && Boolean(this.linkedMaterial?.has_material_measurements || this.selectableVariants.length || this.linkedMaterial?.pack_units > 1);
        },
        get hasPreparationFields() {
            return this.hasQuantityChoice || (this.quantityUnit === 'item' && ['length', 'sheet'].includes(this.linkedMaterial?.material_kind));
        },
    };
}

window.SM = window.SM || {};
Object.assign(window.SM, { productQuantityUnit, defaultProductQuantityUnit, changeProductQuantityUnit, productQuantityChoice, changeProductQuantityChoice, productPreparationFields });
