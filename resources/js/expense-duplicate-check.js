const initialiseExpenseDuplicateCheck = (form) => {
    if (!(form instanceof HTMLFormElement) || form.dataset.expenseDuplicateInitialised === 'true') return;

    const supplier = form.elements.namedItem('supplier');
    const invoiceId = form.elements.namedItem('invoice_id');
    const field = invoiceId?.closest('[data-validation-field="invoice_id"]') || invoiceId?.closest('[data-validation-field]');
    const error = field?.querySelector('[data-validation-error]');
    const url = form.dataset.expenseDuplicateCheckUrl;
    if (!supplier || !invoiceId || !error || !url) return;

    form.dataset.expenseDuplicateInitialised = 'true';
    let timer = null;
    let requestVersion = 0;
    let hasMatches = false;
    let latestMatches = [];
    let duplicateErrorActive = false;
    let originalError = null;

    const duplicateClasses = ['border-red-600', 'ring-red-600', 'ring-1'];

    const clearDuplicateError = () => {
        hasMatches = false;
        latestMatches = [];
        if (!duplicateErrorActive) return;

        error.textContent = '';
        error.hidden = true;
        delete error.dataset.expenseDuplicateActive;
        if (originalError?.ariaInvalid === null || originalError?.ariaInvalid === undefined) invoiceId.removeAttribute('aria-invalid');
        else invoiceId.setAttribute('aria-invalid', originalError.ariaInvalid);
        if (originalError?.ariaDescribedBy === null || originalError?.ariaDescribedBy === undefined) invoiceId.removeAttribute('aria-describedby');
        else invoiceId.setAttribute('aria-describedby', originalError.ariaDescribedBy);
        duplicateClasses.forEach((className, index) => invoiceId.classList.toggle(className, originalError?.classes[index] === true));
        duplicateErrorActive = false;
        originalError = null;
    };

    const showMatches = (matches) => {
        if (!matches.length) {
            clearDuplicateError();
            return;
        }

        hasMatches = true;
        latestMatches = matches;
        if (!duplicateErrorActive) {
            originalError = {
                ariaInvalid: invoiceId.getAttribute('aria-invalid'),
                ariaDescribedBy: invoiceId.getAttribute('aria-describedby'),
                classes: duplicateClasses.map((className) => invoiceId.classList.contains(className)),
            };
            duplicateErrorActive = true;
        }

        error.replaceChildren();
        error.append(document.createTextNode('Possible duplicate invoice. Review '));
        matches.forEach((match, index) => {
            if (index > 0) error.append(document.createTextNode(index === matches.length - 1 ? ' or ' : ', '));
            const link = document.createElement('a');
            link.className = 'font-medium underline';
            link.href = match.url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = `Expense #${match.id}`;
            error.append(link);
        });
        error.append(document.createTextNode(' before saving.'));
        error.hidden = false;
        error.dataset.expenseDuplicateActive = 'true';
        invoiceId.setAttribute('aria-invalid', 'true');
        invoiceId.setAttribute('aria-describedby', [invoiceId.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        duplicateClasses.forEach((className) => invoiceId.classList.add(className));
    };

    const check = async () => {
        const supplierValue = String(supplier.value || '').trim();
        const invoiceValue = String(invoiceId.value || '').trim();
        const version = ++requestVersion;
        if (!invoiceValue) {
            clearDuplicateError();
            return;
        }

        const query = new URLSearchParams({ supplier: supplierValue, invoice_id: invoiceValue });
        if (form.dataset.expenseId) query.set('expense_id', form.dataset.expenseId);
        try {
            const response = await fetch(`${url}?${query.toString()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            const body = await response.json().catch(() => ({}));
            if (version !== requestVersion) return;
            if (!response.ok) throw new Error(body.message || 'The duplicate check could not be completed.');
            showMatches(Array.isArray(body.matches) ? body.matches : []);
        } catch (_error) {
            if (version !== requestVersion) return;
            clearDuplicateError();
        }
    };

    const schedule = () => {
        clearDuplicateError();
        window.clearTimeout(timer);
        timer = window.setTimeout(check, 250);
    };

    [supplier, invoiceId].forEach((fieldControl) => {
        fieldControl.addEventListener('input', schedule);
        fieldControl.addEventListener('change', schedule);
        fieldControl.addEventListener('blur', check);
    });
    form.addEventListener('submit', (event) => {
        if (!hasMatches) return;
        event.preventDefault();
        showMatches(latestMatches);
        invoiceId.focus({ preventScroll: true });
    });

    if (String(invoiceId.value || '').trim() !== '') check();
};

const initialiseExpenseDuplicateChecks = () => {
    document.querySelectorAll('form[data-expense-duplicate-check-url]').forEach(initialiseExpenseDuplicateCheck);
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialiseExpenseDuplicateChecks, { once: true });
else initialiseExpenseDuplicateChecks();
