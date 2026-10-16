import { changeProductQuantityUnit, defaultProductQuantityUnit, productQuantityUnit } from './product-material-quantity';

window.SM = window.SM || {};

window.SM.productMaterials = (config = {}) => ({
    recipeCatalog: config.catalog || [],
    recipeRows: (config.recipe || []).map((row, index) => ({ ...row, product_id: row.component_product_id, quantity_unit: productQuantityUnit(config.catalog?.find(item => String(item.id) === String(row.component_product_id)), row), key: `recipe-${index}` })),
    cutSizes: (config.cuts || []).map((row, index) => ({ ...row, key: `cut-${index}` })),
    nextRecipeKey: 0,
    nextCutKey: 0,
    isKit: Boolean(config.isKit),
    useAsMaterial: Boolean(config.useAsMaterial),
    materialKind: config.materialKind || 'unit',
    purchaseUnit: config.purchaseUnit || 'each',
    purchaseQuantity: config.purchaseQuantity ?? 1,
    purchasePackUnits: config.purchasePackUnits ?? 1,
    materialMeasureUnit: config.measureUnit || 'm',
    materialLength: config.materialLength ?? '',
    materialWidth: config.materialWidth ?? '',
    unitCost: config.unitCost ?? '',
    purchasePackCost: config.purchasePackCost === null || config.purchasePackCost === undefined || config.purchasePackCost === '' ? '' : Number(config.purchasePackCost).toFixed(2),
    // Keep the original precision until the displayed supplier cost is edited.
    purchasePackCostBasis: config.purchasePackCost ?? '',
    purchaseCosts: config.purchaseCosts || {},
    purchaseCostCentres: config.purchaseCostCentres || {},
    purchaseCostCentre: config.purchaseCostCentre || 'auto',
    automaticAllocation: Boolean(config.automaticAllocation),
    basePackUnits: config.basePackUnits ?? 1,
    operationalCost: config.operationalCost ?? '',
    timeCost: config.timeCost ?? '',
    automaticPricing: Boolean(config.automaticPricing),
    markup: config.markup ?? '',
    productBulkDiscount: config.bulkDiscount ?? '',
    priceEnding: Number(config.priceEnding ?? 0.95),
    costingDefaults: config.defaults || {},
    isPhysicalProduct() {
        return ['physical', 'kit'].includes(this.productType);
    },
    hasMaterialMeasurements() {
        return this.productType === 'material' || (this.productType === 'physical' && this.useAsMaterial);
    },
    materialsSummary() {
        const parts = [];
        if (this.hasMaterialMeasurements()) {
            let unit = this.purchaseUnit;
            if (this.materialKind === 'length') unit += ` · ${this.purchaseQuantity} ${this.materialMeasureUnit}`;
            if (this.materialKind === 'sheet') unit += ` · ${this.materialLength} × ${this.materialWidth} mm`;
            parts.push(unit);
        }
        if (this.isPhysicalProduct()) {
            parts.push(this.recipeRows.length
                ? `${this.recipeRows.length} ${this.isKit ? 'recipe' : 'source'} item${this.recipeRows.length === 1 ? '' : 's'}`
                : 'Purchased item');
        }

        return parts.join(' · ');
    },
    initMaterials() {
        this.isKit = this.productType === 'kit';
        this.recipeRows.forEach((row) => { row.item_name = this.recipeItem(row)?.name || row.item_name || ''; });
        this.$watch('productType', (value) => {
            this.isKit = value === 'kit';
            this.syncPurchasePackFallbackCost();
            if (!['physical', 'kit'].includes(value)) {
                this.automaticAllocation = false;
                this.automaticPricing = false;
                this.variants.forEach((variant) => { variant.automatic_pricing = false; });
            }
        });
        this.$watch('useAsMaterial', (enabled) => {
            if (this.productType !== 'physical') return;
            if (enabled) {
                const cost = this.knownCost(this.unitCost);
                const units = this.packUnits();
                const supplierUnits = Number(this.purchasePackUnits);
                this.purchasePackCostBasis = cost === null || units === null || !Number.isInteger(supplierUnits) || supplierUnits < 1 ? '' : cost * supplierUnits / units;
                this.purchasePackCost = this.purchasePackCostBasis === '' ? '' : Number(this.purchasePackCostBasis).toFixed(2);
                this.syncPurchasePackFallbackCost();
            } else {
                const cost = this.knownCost(this.unitCost);
                this.unitCost = cost === null ? '' : cost.toFixed(4);
            }
        });
        ['purchasePackCostBasis', 'purchasePackUnits', 'basePackUnits'].forEach((property) => {
            this.$watch(property, () => this.syncPurchasePackFallbackCost());
        });
        ['automaticPricing', 'recipeRows', 'isKit', 'unitCost', 'useAsMaterial', 'operationalCost', 'timeCost', 'markup', 'productBulkDiscount', 'productTaxRate', 'basePackUnits', 'basePrice', 'sharedInventory'].forEach((property) => {
            this.$watch(property, () => this.syncAutomaticPrice());
        });
        // Watch the pricing inputs only: writing calculated prices must not trigger another update.
        this.$watch('variantPricingInputs()', () => this.syncAutomaticPrice());
        this.syncPurchasePackFallbackCost();
        this.syncAutomaticPrice();
    },
    syncPurchasePackFallbackCost() {
        if (this.productType !== 'physical' || !this.useAsMaterial) return;
        const cost = this.knownCost(this.purchasePackCostBasis);
        const units = this.packUnits();
        const supplierUnits = Number(this.purchasePackUnits);
        const normalized = cost === null || units === null || !Number.isInteger(supplierUnits) || supplierUnits < 1 ? null : this.roundMaterialCost(cost * units / supplierUnits);
        this.unitCost = normalized === null || normalized > 10000000 ? '' : normalized.toFixed(4);
    },
    variantPricingInputs() {
        return JSON.stringify(this.variants.map((variant) => [variant.id, variant.inventory_units, variant.unit_cost_ex_tax,
            variant.bulk_discount_percent, variant.automatic_pricing, variant.is_active,
            variant.automatic_pricing ? null : variant.price]));
    },
    syncAutomaticPrice() {
        if (!this.isPhysicalProduct()) return;
        const suggestion = this.suggestedSellingPrice();
        if (this.automaticPricing && suggestion !== null && this.basePrice !== suggestion.toFixed(2)) this.basePrice = suggestion.toFixed(2);
        this.variants.forEach((variant) => {
            if (!variant.automatic_pricing) return;
            const price = this.suggestedSellingPrice(variant);
            if (price !== null && variant.price !== price.toFixed(2)) variant.price = price.toFixed(2);
        });
    },
    changeMaterialKind() {
        this.purchaseUnit = ({ unit: 'piece', length: 'roll', sheet: 'sheet' })[this.materialKind] || 'piece';
        if (this.materialKind === 'sheet' || (this.productType === 'physical' && this.materialKind === 'unit')) this.purchaseQuantity = 1;
    },
    recipeItem(row) {
        return this.recipeCatalog.find((item) => String(item.id) === String(row.product_id)) || null;
    },
    recipeItemChanged(row) {
        row.component_product_id = row.product_id;
        row.component_variant_id = '';
        row.cut_size_id = '';
        row.length = '';
        row.length_unit = this.recipeItem(row)?.measure_unit || 'mm';
        row.quantity_unit = row.product_id ? defaultProductQuantityUnit(this.recipeItem(row)) : null;
    },
    recipeQuantityUnit(row) {
        return productQuantityUnit(this.recipeItem(row), row);
    },
    changeRecipeQuantityUnit(row) {
        changeProductQuantityUnit(row, row.quantity_unit, this.recipeItem(row));
    },
    addRecipeRow() {
        this.recipeRows.push({ key: `new-recipe-${++this.nextRecipeKey}`, product_id: '', component_product_id: '', component_variant_id: '', item_name: '', quantity: 1, quantity_unit: null, cut_size_id: '', length: '', length_unit: 'mm', note: '' });
    },
    addCutSize() {
        this.cutSizes.push({ key: `new-cut-${++this.nextCutKey}`, id: '', length_mm: '', width_mm: '', yield: '' });
    },
    gridYield(cut) {
        const length = Number(this.materialLength);
        const width = Number(this.materialWidth);
        const a = Number(cut.length_mm);
        const b = Number(cut.width_mm);
        if (length <= 0 || width <= 0 || a <= 0 || b <= 0) return null;
        return Math.max(Math.floor(length / a) * Math.floor(width / b), Math.floor(length / b) * Math.floor(width / a));
    },
    recipeUnits(row) {
        const item = this.recipeItem(row);
        const quantity = Number(row.quantity);
        if (!item || !Number.isFinite(quantity) || quantity <= 0) return null;
        if ((!item.has_material_measurements && item.product_type !== 'material') || productQuantityUnit(item, row) === 'pack') return quantity;
        const variant = item.variants?.find((option) => String(option.id) === String(row.component_variant_id));
        const packUnits = item.product_type === 'material' ? 1 : Number(variant?.pack_units ?? item.pack_units ?? 1);
        if (!Number.isInteger(packUnits) || packUnits < 1) return null;
        if (item.material_kind === 'sheet') {
            if (!row.cut_size_id) return quantity / packUnits;
            const cut = item.cut_sizes.find((size) => String(size.id) === String(row.cut_size_id));
            return cut?.yield > 0 ? quantity / cut.yield / packUnits : null;
        }
        if (item.material_kind === 'length') {
            if (item.product_type !== 'material' && String(row.length ?? '').trim() === '') return quantity / packUnits;
            const factors = { mm: 1, cm: 10, m: 1000 };
            const length = Number(row.length);
            const purchased = Number(item.purchase_quantity) * (factors[item.measure_unit] || 0);
            return length > 0 && purchased > 0 && factors[row.length_unit] ? quantity * length * factors[row.length_unit] / purchased / packUnits : null;
        }
        return item.purchase_quantity > 0 ? quantity / item.purchase_quantity / packUnits : null;
    },
    recipeRowCost(row) {
        const item = this.recipeItem(row);
        const units = this.recipeUnits(row);
        const variant = item?.variants?.find((option) => String(option.id) === String(row.component_variant_id));
        const cost = variant ? variant.unit_cost : item?.unit_cost;
        const amount = this.knownCost(cost);
        return units === null || amount === null ? null : units * amount;
    },
    knownCost(value) {
        if (value === null || value === undefined || String(value).trim() === '') return null;
        const amount = Number(value);
        return Number.isFinite(amount) && amount >= 0 ? amount : null;
    },
    packUnits(variant = null) {
        const units = Number(variant ? variant.inventory_units : this.basePackUnits);
        return Number.isInteger(units) && units >= 1 ? units : null;
    },
    roundMaterialCost(amount) {
        return Number.isFinite(amount) ? Math.round(amount * 10000) / 10000 : null;
    },
    recipeMaterialCost(variant = null) {
        const units = this.isPhysicalProduct() ? this.packUnits(variant) : 1;
        if (units === null) return null;
        if (this.isPhysicalProduct() && this.recipeRows.length) {
            const baseUnits = this.packUnits();
            if (baseUnits === null) return null;
            let total = 0;
            for (const row of this.recipeRows) {
                const cost = this.recipeRowCost(row);
                if (cost === null) return null;
                total += cost;
            }
            return this.roundMaterialCost(total * units / baseUnits);
        }
        const purchaseCost = this.isPhysicalProduct() && this.sharedInventory
            ? this.purchaseCosts.shared
            : variant
                ? variant.id ? this.purchaseCosts.variants?.[variant.id] : this.purchaseCosts.newVariant
                : this.purchaseCosts.base;
        const purchaseUnitCost = this.knownCost(purchaseCost);
        if (purchaseUnitCost !== null) return this.roundMaterialCost(purchaseUnitCost * units);
        if (variant) {
            const fallback = this.knownCost(variant.unit_cost_ex_tax);
            if (fallback !== null) return fallback;
            const base = this.recipeMaterialCost();
            const baseUnits = this.packUnits();
            return base === null || baseUnits === null ? null : this.roundMaterialCost(base * units / baseUnits);
        }
        return this.knownCost(this.unitCost);
    },
    effectiveOperationalCost() {
        return String(this.operationalCost).trim() === '' ? Number(this.costingDefaults[this.isKit ? 'kitOperational' : 'itemOperational']) : Number(this.operationalCost);
    },
    materialCostsByCentre(variant = null) {
        const cost = this.recipeMaterialCost(variant);
        if (cost === null) return null;
        if (this.purchaseCostCentre !== 'auto') return { [this.purchaseCostCentre]: cost };
        if (this.recipeRows.length) {
            const costs = { stock: 0, consumables: 0 };
            for (const row of this.recipeRows) {
                const item = this.recipeItem(row);
                const option = item?.variants?.find(value => String(value.id) === String(row.component_variant_id));
                const parts = (option || item)?.cost_centres;
                const units = this.recipeUnits(row);
                if (!parts || units === null) return null;
                for (const [name, amount] of Object.entries(parts)) costs[name] = (costs[name] || 0) + units * Number(amount);
            }
            const total = Object.values(costs).reduce((sum, amount) => sum + amount, 0);
            return Object.fromEntries(Object.entries(costs).map(([name, amount]) => [name, total > 0 ? amount * cost / total : 0]));
        }
        const scope = this.sharedInventory ? 'shared' : variant ? (variant.id ? 'variants' : 'newVariant') : 'base';
        const purchase = scope === 'variants' ? this.purchaseCosts.variants?.[variant.id] : this.purchaseCosts[scope];
        if (variant && !this.sharedInventory && this.knownCost(purchase) === null && this.knownCost(variant.unit_cost_ex_tax) === null) {
            const base = this.materialCostsByCentre();
            const total = base === null ? 0 : Object.values(base).reduce((sum, amount) => sum + amount, 0);
            return base === null ? null : Object.fromEntries(Object.entries(base).map(([name, amount]) => [name, total > 0 ? amount * cost / total : 0]));
        }
        const centre = this.knownCost(purchase) === null ? 'stock'
            : scope === 'variants' ? this.purchaseCostCentres.variants?.[variant.id] : this.purchaseCostCentres[scope];
        return { [centre || 'stock']: cost };
    },
    effectiveTimeCost() {
        return String(this.timeCost).trim() === '' ? Number(this.costingDefaults[this.isKit ? 'kitTime' : 'itemTime']) : Number(this.timeCost);
    },
    totalEstimatedCost(variant = null) {
        const materials = this.recipeMaterialCost(variant);
        const operational = this.knownCost(this.effectiveOperationalCost());
        const time = this.knownCost(this.effectiveTimeCost());
        return materials === null || operational === null || time === null ? null : materials + operational + time;
    },
    isBulkPack(variant = null) {
        const units = this.packUnits(variant);
        const minimumPack = this.pricingComparisonVariant();
        if (this.variantEditor && !minimumPack) return false;
        const baseUnits = this.packUnits(minimumPack);
        return this.isPhysicalProduct() && variant !== null && units !== null && baseUnits !== null && units > baseUnits;
    },
    effectiveProductBulkDiscount() {
        const value = this.productBulkDiscount;
        const discount = value === null || value === undefined || String(value).trim() === ''
            ? Number(this.costingDefaults.bulkDiscount ?? 20)
            : Number(value);
        return Number.isFinite(discount) && discount >= 0 && discount <= 100 ? discount : null;
    },
    bulkDiscount(variant = null) {
        if (!this.isBulkPack(variant)) return 0;
        const value = variant.bulk_discount_percent;
        if (value === null || value === undefined || String(value).trim() === '') return this.effectiveProductBulkDiscount();
        const discount = Number(value);
        return Number.isFinite(discount) && discount >= 0 && discount <= 100 ? discount : null;
    },
    roundSellingPrice(price) {
        if (!Number.isFinite(price) || price < 0) return null;
        const suggestion = Math.max(this.priceEnding, Math.ceil(price - this.priceEnding - 0.0000001) + this.priceEnding);
        return Number.isFinite(suggestion) && suggestion <= 99999999.99 ? Math.round(suggestion * 100) / 100 : null;
    },
    costBasedMinimumPrice(variant = null) {
        if (!this.isPhysicalProduct()) return null;
        const total = this.totalEstimatedCost(variant);
        if (total === null) return null;
        const factor = this.costPriceFactor();
        return factor === null ? null : this.roundSellingPrice(total * factor);
    },
    costPriceFactor() {
        const markup = String(this.markup).trim() === '' ? Number(this.costingDefaults.markup) : Number(this.markup);
        const tax = Number(this.productTaxRate);
        if (!Number.isFinite(markup) || markup < 0 || !Number.isFinite(tax) || tax < 0) return null;
        return (1 + markup / 100) * (1 + tax);
    },
    discountedBulkPrice(variant) {
        if (!this.isBulkPack(variant)) return null;
        const discount = this.bulkDiscount(variant);
        if (discount === null) return null;
        const minimumPack = this.pricingComparisonVariant();
        if (minimumPack) {
            let basePrice = this.costBasedMinimumPrice(minimumPack);
            if (!minimumPack.automatic_pricing) {
                const sellingPrice = this.sellingPrice(minimumPack);
                if (sellingPrice !== null) basePrice = Math.max(basePrice ?? 0, sellingPrice);
            }
            return basePrice === null ? null : this.roundSellingPrice(basePrice * this.packUnits(variant) / this.packUnits(minimumPack) * (1 - discount / 100));
        }
        const factor = this.costPriceFactor();
        const handling = this.knownCost(this.effectiveOperationalCost() + this.effectiveTimeCost());
        const basePrice = this.sellingPrice();
        const materials = this.recipeMaterialCost();
        if (factor === null || handling === null) return null;
        if (this.automaticPricing && materials === null) return this.costBasedMinimumPrice(variant);
        if (!this.automaticPricing && basePrice === null) return null;
        const handlingPrice = handling * factor;
        const itemPrice = this.automaticPricing ? materials * factor : Math.max(0, basePrice - handlingPrice);
        return this.roundSellingPrice(handlingPrice + itemPrice * this.packUnits(variant) / this.packUnits() * (1 - discount / 100));
    },
    suggestedSellingPrice(variant = null) {
        const minimum = this.costBasedMinimumPrice(variant);
        if (minimum === null || !this.isBulkPack(variant)) return minimum;
        const bulkPrice = this.discountedBulkPrice(variant);
        return bulkPrice === null ? null : Math.max(minimum, bulkPrice);
    },
    minimumPriceApplied(variant) {
        const minimum = this.costBasedMinimumPrice(variant);
        const bulkPrice = this.discountedBulkPrice(variant);
        return minimum !== null && bulkPrice !== null && minimum > bulkPrice + 0.001;
    },
    canUseSuggestedPrice(variant = null) {
        const suggestion = this.suggestedSellingPrice(variant);
        const actual = this.sellingPrice(variant);
        return suggestion !== null && (actual === null || Math.round(actual * 100) !== Math.round(suggestion * 100));
    },
    useSuggestedPrice(variant = null) {
        const price = this.suggestedSellingPrice(variant);
        if (price === null) return;
        if (variant) variant.price = price.toFixed(2);
        else this.basePrice = price.toFixed(2);
    },
    sellingPrice(variant = null) {
        const value = variant && String(variant.price ?? '').trim() !== '' ? variant.price : this.basePrice;
        return this.knownCost(value);
    },
    pricePerItem(variant = null, suggested = false) {
        const price = suggested ? this.suggestedSellingPrice(variant) : this.sellingPrice(variant);
        const units = this.packUnits(variant);
        return price === null || units === null ? null : price / units;
    },
    savingFromBase(variant) {
        const comparison = this.pricingComparisonVariant();
        if (this.variantEditor && (!comparison || comparison === variant)) return null;
        const base = this.pricePerItem(comparison);
        const price = this.pricePerItem(variant);
        if (base === null || base <= 0 || price === null) return null;
        return 100 * (1 - price / base);
    },
    savingLabel(variant) {
        const saving = this.savingFromBase(variant);
        if (saving === null) return '';
        const comparison = this.pricingComparisonVariant();
        const name = comparison ? this.displayVariantName(comparison, this.variants.indexOf(comparison)) : 'reference';
        if (Math.abs(saving) < 0.5) return '';
        return `${Math.abs(saving).toFixed(1)}% ${saving > 0 ? 'less' : 'more'} per item vs ${name}`;
    },
    pricingComparisonVariant() {
        if (!this.variantEditor) return null;
        return this.variants.filter(variant => variant.is_active && this.packUnits(variant) !== null)
            .sort((a, b) => this.packUnits(a) - this.packUnits(b) || Number(a.id || Number.MAX_SAFE_INTEGER) - Number(b.id || Number.MAX_SAFE_INTEGER))[0] || null;
    },
    minimumPackNotice() {
        const pack = this.pricingComparisonVariant();
        const base = this.variants.find(variant => variant.is_base && variant.is_active);
        if (!this.isPhysicalProduct() || !pack || !base || this.packUnits(base) <= this.packUnits(pack)) return '';
        return `Bulk pricing uses ${this.displayVariantName(pack, this.variants.indexOf(pack))}, the smallest active pack.`;
    },
    isBelowCost(variant = null, suggested = false) {
        const price = suggested ? this.suggestedSellingPrice(variant) : this.sellingPrice(variant);
        const cost = this.totalEstimatedCost(variant);
        return price !== null && cost !== null && price / (1 + Number(this.productTaxRate)) < cost - 0.0001;
    },
    pricingRows() {
        const rows = typeof this.orderedVariantRows === 'function' ? this.orderedVariantRows() : this.variants.map((variant, index) => ({ variant, index }));
        return [
            ...(this.variantEditor ? [] : [{ key: 'base', name: this.baseVariantName || 'Base', variant: null, automatic: this.automaticPricing, active: true }]),
            ...rows.map(({ variant, index }) => ({ key: `variant-${variant.id || `new-${index}`}`, name: this.displayVariantName(variant, index), variant, automatic: variant.automatic_pricing, active: variant.is_active })),
        ];
    },
    costingMoney(amount, missing = 'Cost needed') {
        return amount === null || !Number.isFinite(amount) ? missing
            : new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(Math.round((amount + Number.EPSILON) * 100) / 100);
    },
});
