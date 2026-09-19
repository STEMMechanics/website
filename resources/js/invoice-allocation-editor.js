document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('[data-allocation-inline]')) return;
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
    if (!allocation || allocation.dataset.allocationChanged !== '1') return;
    for (const [name, value] of new FormData(allocation)) {
        if (!['budget_id', 'version_id', 'use_defaults', 'targets', 'supplied_categories'].includes(name.split('[')[0])) continue;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.dataset.invoiceAllocationInput = '1';
        const bracket = name.indexOf('[');
        input.name = bracket < 0 ? `allocation[${name}]` : `allocation[${name.slice(0, bracket)}]${name.slice(bracket)}`;
        input.value = value;
        invoiceForm.appendChild(input);
    }
};
