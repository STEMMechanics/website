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
            variant.allocation = { cells: this.cells(rules, variant.price === '' || variant.price === null ? this.basePrice : variant.price) };
        });
        if (window.location?.hash === '#cost-centre-allocation') this.$nextTick(() => {
            this.$el.querySelector('details').open = true;
            this.$el.scrollIntoView({ block: 'start' });
        });
    },
    cells(rules = {}, price = this.basePrice) {
        const fixedTotal = Object.values(rules?.fixed || {}).reduce((sum, value) => sum + Number(value), 0);
        const net = Math.round(Number(price || 0) * 100 / (1 + config.taxRate));
        // Convert legacy percentages at this option's price without hiding fixed costs above its price.
        const amounts = productTargets(rules || {}, 1, Math.max(net, fixedTotal));
        return Object.fromEntries(config.categories.map(id => [id, { amount: ((amounts[id] || 0) / 100).toFixed(2) }]));
    },
    get columns() {
        return [{ key: 'base', name: this.baseOptionDisplayName(), price: this.basePrice, variant: null },
            ...this.variants.map((variant, index) => ({ key: String(index), name: this.displayVariantName(variant, index), price: variant.price === '' || variant.price === null ? this.basePrice : variant.price, variant }))];
    },
    get mobileColumn() {
        return this.columns.some(column => column.key === this.selectedColumn) ? this.selectedColumn : 'base';
    },
    values(column) {
        if (!column.variant) return this.baseCells;
        column.variant.allocation ??= { cells: this.cells({}, column.price) };
        return column.variant.allocation.cells;
    },
    setValue(column, id, value) { this.values(column)[id].amount = value; },
    format(column, id) {
        const value = this.values(column)[id].amount;
        if (Number.isFinite(Number(value)) && Number(value) >= 0) this.values(column)[id].amount = Number(value).toFixed(2);
    },
    rules(cells) {
        return { fixed: Object.fromEntries(Object.entries(cells).map(([id, cell]) => [id, Math.round(Number(cell.amount) * 100)])), percent: {} };
    },
    total(column) {
        const rules = this.rules(this.values(column));
        const net = Math.round(Number(column.price || 0) * 100 / (1 + config.taxRate));
        const allocated = Object.values(rules.fixed).reduce((sum, value) => sum + value, 0);
        return { net, allocated, remaining: Math.max(0, net - allocated), excessive: allocated > net,
            missing: net > 0 && (allocated !== net) };
    },
    get summary() {
        const missing = this.columns.filter(column => column.variant?.is_active !== false && this.total(column).missing).length;
        return missing ? `${missing} option${missing === 1 ? '' : 's'} need allocation review` : 'All options allocated';
    },
    get payload() {
        return JSON.stringify({ base: this.rules(this.baseCells), variants: this.columns.slice(1).map(column => ({
            inherit: false, rules: this.rules(this.values(column)),
        })) });
    },
});
