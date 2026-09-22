document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('[data-allocation-inline], [data-workshop-allocation]')) return;
    event.preventDefault();
    const invoiceForm = document.getElementById('invoice-edit-form');
    if (invoiceForm) invoiceForm.requestSubmit();
});
document.addEventListener('click', async event => {
    const link = event.target.closest('[data-allocation-load]');
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    const form = link.closest('form');
    if (form.dataset.saving) return;
    form.dataset.saving = 'true';
    form.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(link.href, { credentials: 'same-origin' });
        if (!response.ok || response.redirected) throw new Error('The plan could not be loaded. Please try again.');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = doc.querySelector('[data-allocation-inline]');
        if (!replacement) throw new Error('The allocation editor could not be loaded.');
        replacement.dataset.allocationChanged = '1';
        form.replaceWith(replacement);
    } catch (error) {
        SM.banner('Could not load plan', error.message, 'danger');
    } finally { delete form.dataset.saving; form.removeAttribute('aria-busy'); }
});

window.SM.attachInvoiceAllocation = function (invoiceForm) {
    invoiceForm.querySelectorAll('[data-invoice-allocation-input]').forEach(input => input.remove());
    const allocation = document.querySelector('[data-allocation-inline]');
    const forms = allocation ? [{ form: allocation, prefix: 'allocation', fields: ['budget_id', 'version_id', 'use_defaults', 'targets', 'supplied_categories'] }] : [];
    document.querySelectorAll('[data-workshop-allocation][data-allocation-ready="1"]').forEach(form => {
        forms.push({ form, prefix: `workshop_allocations[${form.dataset.workshopAllocation}]`, fields: ['source_hash', 'revision', 'override', 'targets', 'supplied_categories'] });
    });
    for (const { form, prefix, fields } of forms) {
        if (form.dataset.allocationChanged !== '1') continue;
        for (const [name, value] of new FormData(form)) {
            if (!fields.includes(name.split('[')[0])) continue;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.dataset.invoiceAllocationInput = '1';
            const bracket = name.indexOf('[');
            input.name = bracket < 0 ? `${prefix}[${name}]` : `${prefix}[${name.slice(0, bracket)}]${name.slice(bracket)}`;
            input.value = value;
            invoiceForm.appendChild(input);
        }
    }
};

window.SM.invoiceAllocationWorkspace = config => ({
    plans: config.plans, income: config.income, scope: config.scope, inspection: {}, funding: {},
    updatePlan(detail) {
        if (!detail || !Object.hasOwn(this.plans, detail.key)) return;
        this.plans[detail.key] = { ...detail.values };
        if (detail.inspect !== undefined) this.inspection[detail.key] = detail.inspect;
        if (detail.funding !== undefined) this.funding[detail.key] = detail.funding;
    },
    needsInspection(key) { return !!this.inspection[key] || (this.funding[key] !== undefined && this.planTotal(key) > this.funding[key]); },
    inspectionReason(key) { return this.funding[key] !== undefined && this.planTotal(key) > this.funding[key] ? 'Needs inspection: allocation exceeds funding' : 'Needs inspection'; },
    categoryAmount(key, id) { return Math.round(Number(this.plans[key]?.[id] || 0) * 100); },
    categoryTotal(id) { return Object.keys(this.plans).reduce((sum, key) => sum + this.categoryAmount(key, id), 0); },
    planTotal(key) { return Object.values(this.plans[key] || {}).reduce((sum, value) => sum + Math.round(Number(value || 0) * 100), 0); },
    get total() { return Object.keys(this.plans).reduce((sum, key) => sum + this.planTotal(key), 0); },
    money(cents) { return (cents / 100).toLocaleString('en-AU', { style: 'currency', currency: 'AUD' }); },
});
