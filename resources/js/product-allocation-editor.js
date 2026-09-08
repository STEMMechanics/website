import { productTargets } from './product-allocation';

window.SM = window.SM || {};
window.SM.productAllocationEditor = (config) => ({
    baseCells: {},
    selectedColumn: 'base',
    init() {
        const saved = config.old || null;
        this.baseCells = this.cells(saved ? saved.base : config.data.base);
        this.variants.forEach((variant, index) => {
            const allocation = saved ? saved.variants?.[index] : config.data.variants?.[variant.id];
            variant.allocation = { inherit: allocation?.inherit ?? true, cells: this.cells(allocation?.rules) };
        });
        if (window.location?.hash === '#cost-centre-allocation') this.$nextTick(() => {
            this.$el.querySelector('details').open = true;
            this.$el.scrollIntoView({ block: 'start' });
        });
    },
    cells(rules = {}) {
        const result = {};
        for (const id of config.categories) {
            const fixed = Number(rules?.fixed?.[id] || 0);
            const percent = Number(rules?.percent?.[id] || 0);
            result[id] = {
                amount: ((fixed || percent) / 100).toFixed(2),
                percentage: !fixed && percent > 0,
                extraPercent: fixed && percent ? (percent / 100).toFixed(2) : '',
            };
        }
        return result;
    },
    get columns() {
        return [{ key: 'base', name: this.baseOptionDisplayName(), price: this.basePrice, variant: null },
            ...this.variants.map((variant, index) => ({ key: String(index), name: this.displayVariantName(variant, index), price: variant.price === '' || variant.price === null ? this.basePrice : variant.price, variant }))];
    },
    get mobileColumn() {
        return this.columns.some(column => column.key === this.selectedColumn) ? this.selectedColumn : 'base';
    },
    inherited(column) { return column.variant && (column.variant.allocation?.inherit ?? true); },
    values(column) { return !column.variant || this.inherited(column) ? this.baseCells : column.variant.allocation.cells; },
    editable(column) {
        if (!column.variant) return this.baseCells;
        if (this.inherited(column)) column.variant.allocation = { inherit: false, cells: JSON.parse(JSON.stringify(this.baseCells)) };
        return column.variant.allocation.cells;
    },
    setValue(column, id, value, extra = false) { this.editable(column)[id][extra ? 'extraPercent' : 'amount'] = value; },
    format(column, id, extra = false) {
        const field = extra ? 'extraPercent' : 'amount';
        const value = this.values(column)[id][field];
        if (Number.isFinite(Number(value)) && Number(value) >= 0) {
            // Blurring an unchanged inherited field should not turn it into an override.
            this.values(column)[id][field] = Number(value).toFixed(2);
        }
    },
    toggleType(column, id, percentage) {
        const cell = this.editable(column)[id];
        cell.percentage = percentage;
        cell.extraPercent = '';
    },
    useBase(column) { column.variant.allocation = { inherit: true, cells: {} }; },
    rules(cells) {
        const rules = { fixed: {}, percent: {} };
        for (const [id, cell] of Object.entries(cells)) {
            const value = Math.round(Number(cell.amount) * 100);
            rules[cell.percentage ? 'percent' : 'fixed'][id] = value;
            if (cell.extraPercent !== '') rules.percent[id] = Math.round(Number(cell.extraPercent) * 100);
        }
        return rules;
    },
    total(column) {
        const rules = this.rules(this.values(column));
        const net = Math.round(Number(column.price || 0) * 100 / (1 + config.taxRate));
        const allocated = Object.values(productTargets(rules, 1, net)).reduce((sum, value) => sum + value, 0);
        const fixed = Object.values(rules.fixed).reduce((sum, value) => sum + value, 0);
        const percent = Object.values(rules.percent).reduce((sum, value) => sum + value, 0);
        return { net, allocated, remaining: Math.max(0, net - allocated), excessive: fixed > net || percent > 10000,
            missing: net > 0 && (allocated < net || fixed > net || percent > 10000) };
    },
    get summary() {
        const missing = this.columns.filter(column => column.variant?.is_active !== false && this.total(column).missing).length;
        return missing ? `${missing} option${missing === 1 ? '' : 's'} need allocation review` : 'All options allocated';
    },
    get payload() {
        return JSON.stringify({ base: this.rules(this.baseCells), variants: this.variants.map(variant => ({
            inherit: variant.allocation?.inherit ?? true,
            rules: this.rules(variant.allocation?.inherit === false ? variant.allocation.cells : this.baseCells),
        })) });
    },
});
