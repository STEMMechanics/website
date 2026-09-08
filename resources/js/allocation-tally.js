export function allocationTally(config) {
    const signature = (values, supplied, enabled) => JSON.stringify([
        !!enabled,
        Object.keys(values).sort().map(id => [id, Math.round(Number(values[id] || 0) * 100)]),
        Object.keys(supplied).sort().map(id => [id, !!supplied[id]]),
    ]);
    const initialSignature = signature(config.values || {}, config.workshopDefaults?.selected || {}, config.enabled ?? true);
    return {
        values: config.values || {},
        supplied: config.workshopDefaults?.selected || {},
        get allocationChanged() { return signature(this.values, this.supplied, this.enabled) !== initialSignature; },
        refreshWorkshopDefaults() {
            if (!config.workshopDefaults || this.enabled) return;
            Object.keys(this.values).forEach(id => {
                const targets = this.supplied[id] ? config.workshopDefaults.supplied : config.workshopDefaults.notSupplied;
                this.values[id] = ((targets[id] || 0) / 100).toFixed(2);
            });
        },
        applyCalculatorValues(detail) {
            if (!detail?.values || !Object.keys(this.values).every(id => Object.hasOwn(detail.values, id) && Number.isFinite(Number(detail.values[id])) && Number(detail.values[id]) >= 0 && Number(detail.values[id]) <= 10000000)) return;
            this.enabled = true;
            Object.keys(this.values).forEach(id => { this.values[id] = (this.cents(detail.values[id]) / 100).toFixed(2); });
        },
        previewFingerprint: null,
        previewDirty: false,
        previewInvoice(detail, rules, prices = {}) {
            const fingerprint = JSON.stringify(detail);
            this.previewFingerprint ??= fingerprint;
            this.previewDirty = fingerprint !== this.previewFingerprint;
            this.total = detail.total;
            if (!this.enabled) {
                const targets = window.SM.lineCostAllocations(detail.items, rules);
                if (config.products && window.SM.productLineCostAllocations) {
                    for (const [id, amount] of Object.entries(window.SM.productLineCostAllocations(detail.items, this.total, config.products))) {
                        targets[id] = (targets[id] || 0) + amount;
                    }
                }
                const remaining = Math.max(0, this.total - Object.values(targets).reduce((sum, amount) => sum + amount, 0));
                if (prices.rounding_category_id && window.SM.invoiceRoundingAllowance) {
                    const extra = Math.min(remaining, window.SM.invoiceRoundingAllowance(detail.items, rules, prices));
                    targets[prices.rounding_category_id] = (targets[prices.rounding_category_id] || 0) + extra;
                }
                Object.keys(this.values).forEach(id => { this.values[id] = ((targets[id] || 0) / 100).toFixed(2); });
            }
        },
        enabled: config.enabled ?? true,
        total: config.total || 0,
        cents(value) {
            const amount = Number(value || 0);
            return Number.isFinite(amount) ? Math.round(amount * 100) : 0;
        },
        get allocated() { return Object.values(this.values).reduce((sum, value) => sum + this.cents(value), 0); },
        get remaining() { return this.total - this.allocated; },
        get valid() { return !this.enabled || (!config.exact || this.remaining === 0); },
        money(cents) { return (cents / 100).toLocaleString('en-AU', { style: 'currency', currency: 'AUD' }); },
        format(id) { this.values[id] = (this.cents(this.values[id]) / 100).toFixed(2); },
        allocateRemaining(id) {
            if (!this.enabled || this.remaining <= 0 || !Object.hasOwn(this.values, id)) return;
            this.values[id] = ((this.cents(this.values[id]) + this.remaining) / 100).toFixed(2);
        },
        refreshDefaults() {
            if (!config.defaults || this.enabled) return;
            const name = document.getElementById(config.supplierInput)?.value.trim().toLowerCase() || '';
            const split = config.defaults[name] || {};
            Object.keys(this.values).forEach(id => { this.values[id] = '0.00'; });
            let percentage = 0, assigned = 0;
            Object.entries(split).forEach(([id, percent]) => {
                percentage += Number(percent);
                const next = Math.round(Math.max(0, this.total) * percentage / 100);
                if (Object.hasOwn(this.values, id)) this.values[id] = ((next - assigned) / 100).toFixed(2);
                assigned = next;
            });
        },
        refreshTotal(event) {
            if (!config.totalInput || (event && ![config.totalInput, config.gstInput, config.supplierInput].includes(event.target.id))) return;
            this.total = this.cents(document.getElementById(config.totalInput)?.value) - this.cents(document.getElementById(config.gstInput)?.value);
            this.refreshDefaults();
        },
        init() {
            this.refreshTotal();
            const form = this.$el.closest('form');
            if (form && config.exact) {
                const guard = event => {
                    if (!this.valid) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        window.SM.banner('Check cost centre allocation', config.message || 'Allocated amounts must equal the expense total excluding GST.', 'danger');
                    }
                };
                form.addEventListener('submit', guard, true);
                this.cleanup = () => form.removeEventListener('submit', guard, true);
            }
        },
        destroy() { this.cleanup?.(); },
    };
}
window.SM = window.SM || {};
window.SM.allocationTally = allocationTally;
