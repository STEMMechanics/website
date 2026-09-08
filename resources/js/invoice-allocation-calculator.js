export function calculateAllocationRows(plan, rows) {
    if (!plan || !rows.length || rows.length > 50) return null;
    const totals = {};
    const rules = plan.rules.map(rule => ({ ...rule, basis: rule.basis === 'flat' ? 'workshop' : rule.basis }));
    for (const row of rows) {
        const count = Number(row.count), hours = Number(row.hours), participants = Number(row.participants), travel = Number(row.travel);
        if (!Number.isInteger(count) || count < 1 || count > 1000
            || !Number.isFinite(hours) || hours < 0.01 || hours > 24 || Math.abs(hours * 100 - Math.round(hours * 100)) > 0.000001
            || !Number.isInteger(participants) || participants < 1 || participants > 10000
            || !Number.isFinite(travel) || travel < 0 || travel > 2500 || !Number.isInteger(travel * 4)) return null;
        const amounts = window.SM.lineCostAllocations([
            { kind: 'workshop', workshop_hours: hours, workshop_seats: participants, venue_supplied: false, supplied_categories: row.supplied },
            { kind: 'travel', travel_hours: travel, supplied_categories: row.supplied },
        ], rules);
        for (const [id, amount] of Object.entries(amounts)) {
            if (!Number.isSafeInteger(amount) || amount < 0) return null;
            totals[id] = (totals[id] || 0) + amount * count;
            if (!Number.isSafeInteger(totals[id]) || totals[id] > 1000000000) return null;
        }
    }
    return totals;
}

window.SM.invoiceAllocationCalculator = config => {
    const calculator = {
        dialogId: config.dialogId,
        plans: config.plans,
        categories: config.categories,
        planId: String(config.planId),
        rows: [],
        nextId: 0,
        isOpen: false,
        minimised: false,
        inputsValid: false,
        get plan() { return this.plans.find(plan => String(plan.id) === String(this.planId)); },
        get supplies() {
            const ids = new Set((this.plan?.rules || []).filter(rule => rule.suppliable || rule.basis === 'venue_hour').map(rule => String(rule.category_id)));
            return this.categories.filter(category => ids.has(String(category.id)));
        },
        get canApply() {
            return this.inputsValid && this.allocated > 0 && Object.values(this.values).every(value => String(value).trim() !== '' && Number.isFinite(Number(value)) && Number(value) >= 0 && Number(value) <= 10000000);
        },
        init() {
            this.$nextTick(() => {
                const dialog = document.getElementById(config.dialogId);
                const closed = () => { this.isOpen = dialog.open; };
                dialog.addEventListener('close', closed);
                this.cleanup = () => dialog.removeEventListener('close', closed);
            });
        },
        launch(total) {
            this.total = total;
            this.minimised = false;
            if (!this.rows.length) this.addRow();
            this.isOpen = true;
            document.getElementById(config.dialogId).showModal();
        },
        minimise() {
            this.minimised = true;
            this.isOpen = false;
            document.getElementById(config.dialogId).close();
            this.$nextTick(() => this.$refs.restoreCalculator.focus());
        },
        addRow() {
            if (this.rows.length >= 50) return;
            this.rows.push({ id: ++this.nextId, count: 1, hours: 1, participants: Number(this.plan?.participants || 10), travel: 0, supplied: {} });
            this.recalculate();
        },
        removeRow(id) {
            this.rows = this.rows.filter(row => row.id !== id);
            this.recalculate();
        },
        recalculate() {
            const amounts = calculateAllocationRows(this.plan, this.rows);
            this.inputsValid = amounts !== null;
            Object.keys(this.values).forEach(id => { this.values[id] = ((amounts?.[id] || 0) / 100).toFixed(2); });
        },
        apply() {
            if (!this.canApply) return;
            this.$dispatch('allocation-calculated', { values: { ...this.values } });
            document.getElementById(config.dialogId).close();
        },
    };
    return Object.defineProperties(
        window.SM.allocationTally({ values: Object.fromEntries(config.categories.map(category => [category.id, '0.00'])), total: 0, exact: false, enabled: true }),
        Object.getOwnPropertyDescriptors(calculator),
    );
};
