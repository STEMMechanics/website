import { productTargets } from './product-allocation';

window.SM = window.SM || {};
window.SM.productAllocationEditor = (config) => ({
    baseCells: {},
    selectedColumn: 'base',
    init() {
        const saved = config.old || null;
        const baseRules = saved ? saved.base : config.data.base;
        this.baseCells = this.cells(baseRules, this.basePrice);
        this.variants.forEach((variant, index) => {
            const allocation = saved ? saved.variants?.[index] : config.data.variants?.[variant.id];
            const rules = (allocation?.inherit ?? true) ? baseRules : allocation.rules;
            variant.allocation = { cells: this.cells(rules, variant.price === '' || variant.price === null ? this.basePrice : variant.price), sourceRules: rules, original: allocation, dirty: false };
        });
        if (window.location?.hash === '#cost-centre-allocation') this.$nextTick(() => {
            this.$el.querySelector('details').open = true;
            this.$el.scrollIntoView({ block: 'start' });
        });
    },
    cells(rules = {}, price = this.basePrice) {
        const fixedTotal = Object.values(rules?.fixed || {}).reduce((sum, value) => sum + Number(value), 0);
        const net = Math.round(Number(price || 0) * 100 / (1 + Number(this.productTaxRate ?? config.taxRate)));
        // Convert legacy percentages at this option's price without hiding fixed costs above its price.
        const amounts = productTargets(rules || {}, 1, Math.max(net, fixedTotal));
        return Object.fromEntries(config.categories.map(id => [id, { amount: ((amounts[id] || 0) / 100).toFixed(2) }]));
    },
    get allColumns() {
        return [{ key: 'base', name: this.baseOptionDisplayName(), price: this.basePrice, variant: null },
            ...this.variants.map((variant, index) => ({ key: String(index), name: this.displayVariantName(variant, index), price: variant.price === '' || variant.price === null ? this.basePrice : variant.price, variant }))];
    },
    get columns() {
        if (!this.variantEditor) return this.allColumns.filter(column => column.variant?.is_active !== false);
        const ordered = typeof this.orderedVariantRows === 'function' ? this.orderedVariantRows() : this.variants.map((variant, index) => ({ variant, index }));
        return ordered.filter(({ variant }) => variant.is_active).map(({ index }) => this.allColumns[index + 1]);
    },
    get mobileColumn() {
        return this.columns.some(column => column.key === this.selectedColumn) ? this.selectedColumn : this.columns[0]?.key;
    },
    values(column) {
        if (this.automaticAllocation) return this.cells(this.automaticRules(column) || {}, column.price);
        if (!column.variant) return this.baseCells;
        column.variant.allocation ??= { cells: this.cells({}, column.price) };
        if (this.variantEditor && column.variant.allocation.sourceRules && !column.variant.allocation.dirty) {
            return this.cells(column.variant.allocation.sourceRules, column.price);
        }
        return column.variant.allocation.cells;
    },
    rememberEdit(column, id, cell) {
        if (!column.variant) return;
        const cells = this.values(column);
        cells[id] = cell;
        column.variant.allocation.cells = cells;
        column.variant.allocation.dirty = true;
    },
    setValue(column, id, value) {
        if (this.automaticAllocation) return this.values(column)[id].amount;
        const cell = this.values(column)[id];
        const input = String(value);
        // Allow an unfinished decimal while typing, but reject invalid edits/pastes.
        if (/^\d*(?:\.\d{0,2})?$/.test(input)
            && (input === '.' || Number.isSafeInteger(Math.round(Number(input) * 100)))) {
            cell.amount = input;
            this.rememberEdit(column, id, cell);
        }
        return cell.amount;
    },
    cents(value) {
        const amount = Math.round(Number(value || 0) * 100);
        return Number.isSafeInteger(amount) && amount >= 0 ? amount : 0;
    },
    format(column, id) {
        this.values(column)[id].amount = (this.cents(this.values(column)[id].amount) / 100).toFixed(2);
    },
    allocateRemaining(column, id) {
        if (this.automaticAllocation) return;
        const cell = this.values(column)[id];
        const total = this.total(column);
        const remaining = total.net - total.allocated;
        if (!cell || !Number.isFinite(remaining)) return;
        const current = this.cents(cell.amount);
        if (!Number.isFinite(current) || current < 0) return;
        cell.amount = (Math.max(0, current + remaining) / 100).toFixed(2);
        this.rememberEdit(column, id, cell);
    },
    rules(cells) {
        return { fixed: Object.fromEntries(Object.entries(cells).map(([id, cell]) => [id, this.cents(cell.amount)])), percent: {} };
    },
    automaticRules(column) {
        const materials = this.materialCostsByCentre(column.variant);
        const percent = this.effectiveRemainder();
        if (materials === null || percent === null) return null;
        const costs = { ...materials, operational: this.effectiveOperationalCost(), 'owner remuneration': this.effectiveTimeCost() };
        const fixed = {};
        const total = Object.values(materials).reduce((sum, amount) => sum + amount, 0);
        const cents = Math.round(total * 100);
        let cumulative = 0, previous = 0;
        for (const [name, amount] of Object.entries(costs)) {
            const id = config.centres?.[name];
            if (!Number.isFinite(amount) || amount < 0 || amount > 10000000 || amount > 0 && !id) return null;
            let value = Math.round(amount * 100);
            if (Object.hasOwn(materials, name)) {
                cumulative += amount;
                const next = total > 0 ? Math.round(cents * cumulative / total) : 0;
                value = next - previous;
                previous = next;
            }
            if (id) fixed[id] = value;
        }
        if (Object.entries(percent).some(([id, amount]) => !config.categories.some(category => String(category) === id) || !Number.isInteger(amount) || amount < 0)
            || Object.values(percent).reduce((sum, amount) => sum + amount, 0) > 10000) return null;
        return { fixed, percent };
    },
    contribution(column, id) {
        if (!this.automaticAllocation) return '';
        const cost = this.automaticRules(column)?.fixed[id] || 0;
        const extra = this.cents(this.values(column)[id].amount) - cost;
        return cost > 0 && extra > 0 ? `$${(cost / 100).toFixed(2)} cost + $${(extra / 100).toFixed(2)} remainder` : '';
    },
    total(column) {
        const rules = this.rules(this.values(column));
        const net = Math.round(this.cents(column.price) / (1 + Number(this.productTaxRate ?? config.taxRate)));
        const allocated = Object.values(rules.fixed).reduce((sum, value) => sum + value, 0);
        const unknown = this.automaticAllocation && this.automaticRules(column) === null;
        return { net, allocated, remaining: Math.max(0, net - allocated), excessive: allocated > net, unknown,
            missing: unknown || (this.automaticAllocation ? allocated > net : net > 0 && allocated !== net) };
    },
    get summary() {
        const missing = this.columns.filter(column => (!this.variantEditor || column.variant) && column.variant?.is_active !== false && this.total(column).missing).length;
        return missing ? `${missing} option${missing === 1 ? '' : 's'} need allocation review` : this.automaticAllocation ? 'Automatically allocated' : 'All options allocated';
    },
    get payload() {
        return JSON.stringify({ base: this.variantEditor ? (config.old?.base || config.data.base) : this.rules(this.baseCells), variants: this.allColumns.slice(1).map(column => {
            const state = column.variant.allocation;
            if (this.variantEditor && state?.original && !state.dirty) return state.original;
            return { inherit: false, rules: this.rules(state?.cells || this.cells({}, column.price)) };
        }) });
    },
});
