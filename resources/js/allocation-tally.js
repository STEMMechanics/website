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
        get allocationChanged() { return this.workshopInputsChanged || signature(this.values, this.supplied, this.enabled) !== initialSignature; },
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
        workshopInputs: { ...(config.workshopInputs || {}) },
        workshopInputsChanged: false,
        previewWorkshopFunding(detail) {
            if (!config.workshopId) return;
            let amount = 0, found = false;
            for (const item of detail.items || []) {
                const net = Math.round(window.SM.lineAmounts(item).net * 100);
                if (item.kind === 'workshop' && item.details_json?.workshop?.linked_workshop_id === config.workshopId) {
                    found = true;
                    amount += net;
                } else if (item.kind === 'multi_workshop') {
                    const weights = {};
                    for (const row of item.workshops ?? item.details_json?.multi_workshop?.rows ?? []) {
                        const id = row.details_json?.workshop?.linked_workshop_id;
                        if (id === config.workshopId) found = true;
                        const key = id ? `workshop:${id}` : 'invoice';
                        weights[key] = (weights[key] || 0) + Math.round(Number(row.workshop_hours || 0) * Number(row.workshop_seats || 0) * 100);
                    }
                    const total = Object.values(weights).reduce((sum, value) => sum + value, 0);
                    let before = 0;
                    for (const key of Object.keys(weights).sort()) {
                        if (total && key === `workshop:${config.workshopId}`) amount += Math.round(net * (before + weights[key]) / total) - Math.round(net * before / total);
                        before += weights[key];
                    }
                }
            }
            if (found) this.total = Math.max(Number(config.received || 0), amount);
        },
        previewWorkshop(detail) {
            if (!config.workshopId || detail?.id !== config.workshopId) return;
            const participants = Number(detail.participants), hours = Number(detail.hours);
            if (!Number.isFinite(participants) || participants < 0 || !Number.isFinite(hours) || hours < 0) return;
            this.workshopInputs.participants = participants;
            this.workshopInputs.hours = hours;
            this.workshopInputsChanged = participants !== Number(config.workshopInputs.participants) || hours !== Number(config.workshopInputs.hours);
            const calculate = supplied => {
                const targets = {};
                for (const rule of config.workshopRules) {
                    let units = { participant: participants, hour: hours, venue_hour: supplied ? 0 : hours,
                        travel: Math.ceil(Math.max(0, Number(this.workshopInputs.travel_minutes || 0) - Number(this.workshopInputs.travel_free_minutes ?? 30)) / 15) }[rule.basis] ?? 1;
                    if (rule.suppliable && supplied) units = 0;
                    targets[rule.category_id] = (targets[rule.category_id] || 0) + Math.round(rule.rate_cents * units);
                }
                return targets;
            };
            config.workshopDefaults.supplied = calculate(true);
            config.workshopDefaults.notSupplied = calculate(false);
            this.refreshWorkshopDefaults();
        },
        previewFingerprint: null,
        previewDirty: false,
        previewInvoice(detail, rules, prices = {}) {
            const fingerprint = JSON.stringify(detail);
            this.previewFingerprint ??= fingerprint;
            this.previewDirty = fingerprint !== this.previewFingerprint;
            const expanded = window.SM.expandWorkshopGroups ? window.SM.expandWorkshopGroups(detail.items) : detail.items;
            const items = expanded.filter(item => !(item.kind === 'workshop' && item.details_json?.workshop?.linked_workshop_id));
            const linkedNet = detail.items.reduce((sum, item) => {
                if (item.kind === 'workshop' && item.details_json?.workshop?.linked_workshop_id) return sum + Math.round(window.SM.lineAmounts(item).net * 100);
                if (item.kind !== 'multi_workshop') return sum;
                const rows = item.workshops ?? item.details_json?.multi_workshop?.rows ?? [];
                const units = row => Number(row.workshop_hours || 0) * Number(row.workshop_seats || 0);
                const total = rows.reduce((count, row) => count + units(row), 0);
                const manual = rows.filter(row => !row.details_json?.workshop?.linked_workshop_id).reduce((count, row) => count + units(row), 0);
                const net = Math.round(window.SM.lineAmounts(item).net * 100);
                return sum + (total ? net - Math.round(net * manual / total) : 0);
            }, 0);
            this.total = detail.total - linkedNet;
            if (!this.enabled) {
                const targets = window.SM.lineCostAllocations(items, rules);
                if (config.products && window.SM.productLineCostAllocations) {
                    for (const [id, amount] of Object.entries(window.SM.productLineCostAllocations(items, this.total, config.products))) {
                        targets[id] = (targets[id] || 0) + amount;
                    }
                }
                const remaining = Math.max(0, this.total - Object.values(targets).reduce((sum, amount) => sum + amount, 0));
                if (prices.rounding_category_id && window.SM.invoiceRoundingAllowance) {
                    const extra = Math.min(remaining, window.SM.invoiceRoundingAllowance(items, rules, prices));
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
            if (!this.enabled || this.remaining === 0 || !Object.hasOwn(this.values, id)) return;
            this.values[id] = (Math.max(0, this.cents(this.values[id]) + this.remaining) / 100).toFixed(2);
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
