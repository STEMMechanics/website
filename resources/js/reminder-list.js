import { bindSelectionCycle, selectionKey } from './selection-cycle';
const storageKey = 'admin-reminder-selection';
function initialise() {
    const root = document.querySelector('[data-dynamic-list="admin-reminder-index"]');
    if (!root) return;
    const rows = [...root.querySelectorAll('[data-reminder-select]')];
    const button = root.querySelector('[data-reminder-edit]');
    if (!button || button.dataset.bound) return;
    button.dataset.bound = '1';
    let selected = [];
    try { selected = JSON.parse(sessionStorage.getItem(storageKey) || '[]').map(String); } catch {}
    let renderHeader = () => {};
    const error = error => SM.banner('Could not select reminders', error.message, 'danger');
    const render = () => {
        rows.forEach(row => row.checked = selected.includes(row.value));
        button.hidden = !selected.length;
        button.disabled = !selected.length;
        button.textContent = `Edit ${selected.length} ${selected.length === 1 ? 'item' : 'items'}`;
        root.querySelector('[data-reminder-bulk-inputs]').replaceChildren(...selected.map(id => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'reminder_ids[]'; input.value = id; return input;
        }));
        renderHeader();
    };
    const setSelected = ids => {
        if (ids.length > 5000) { error(new Error('Select up to 5,000 reminders at a time.')); return false; }
        selected = [...new Set(ids)];
        try { sessionStorage.setItem(storageKey, JSON.stringify(selected)); } catch {}
        render();
    };
    renderHeader = bindSelectionCycle({
        header: root.querySelector('[data-reminder-select-all]'), pageIds: rows.map(row => row.value),
        getSelected: () => selected, setSelected, key: selectionKey(window.location.href), onError: error,
        loadMatching: async () => {
            const url = new URL(window.location.href); url.searchParams.set('select_listing', '1');
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Could not select reminders.');
            return data.names;
        },
    });
    rows.forEach(row => row.addEventListener('change', () => setSelected(row.checked ? [...selected, row.value] : selected.filter(id => id !== row.value))));
    render();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise); else initialise();
document.addEventListener('sm:list-updated', initialise);
