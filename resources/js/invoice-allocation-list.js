import { bindSelectionCycle, selectionKey } from './selection-cycle';
const storageKey = 'admin-invoice-allocation-selection';
function initialise() {
    const root = document.querySelector('[data-dynamic-list="admin-invoice-index"]');
    const button = root?.querySelector('[data-invoice-allocate]');
    if (!button || button.dataset.bound) return;
    button.dataset.bound = '1';
    const endpoint = button.href;
    const rows = [...root.querySelectorAll('[data-invoice-select]')];
    let selected = [];
    try { const saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); if (Array.isArray(saved)) selected = [...new Set(saved.map(String))].slice(0, 200); } catch (_) {}
    const renders = [];
    const render = () => {
        rows.forEach(row => { row.checked = selected.includes(row.value); });
        const url = new URL(endpoint);
        selected.forEach(id => url.searchParams.append('invoice_ids[]', id));
        button.href = url.href;
        button.textContent = `Allocate ${selected.length} invoices`;
        button.setAttribute('aria-disabled', String(!selected.length));
        button.classList.toggle('opacity-50', !selected.length);
        renders.forEach(update => update());
    };
    const setSelected = values => {
        if (values.length > 200) { SM.banner('Selection limit', 'Select up to 200 invoices per batch.', 'warning'); return false; }
        selected = [...new Set(values)];
        sessionStorage.setItem(storageKey, JSON.stringify(selected)); render();
    };
    for (const header of root.querySelectorAll('[data-invoice-select-all]')) {
        renders.push(bindSelectionCycle({ header, pageIds: [...new Set(rows.map(row => row.value))], getSelected: () => selected, setSelected,
            key: selectionKey(location.href),
            onError: error => SM.banner('Could not select invoices', error.message, 'danger'),
            loadMatching: async () => {
                const url = new URL(location.href); url.searchParams.set('allocation_selection', '1');
                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Could not load matching invoices.');
                return data.names;
            },
        }));
    }
    rows.forEach(row => row.addEventListener('change', () => setSelected(row.checked ? [...selected.filter(id => id !== row.value), row.value] : selected.filter(id => id !== row.value))));
    button.addEventListener('click', event => { if (!selected.length) { event.preventDefault(); event.stopPropagation(); } });
    render();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise); else initialise();
document.addEventListener('sm:list-updated', initialise);

document.addEventListener('sm:record-saved', event => { if (event.detail?.action === 'invoice-allocation') sessionStorage.removeItem(storageKey); });
