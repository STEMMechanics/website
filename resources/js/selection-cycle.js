// Shared three-step selection: visible page, entire filter, clear entire filter.
const filters = new Map();
export function bindSelectionCycle({ header, pageIds, getSelected, setSelected, loadMatching, key, onError }) {
    if (!header) return () => {};
    const state = filters.get(key) || { names: null, all: false };
    filters.set(key, state);
    let busy = false;
    const render = () => {
        const selected = new Set(getSelected());
        const full = state.all && state.names?.length > 0 && state.names.every(name => selected.has(name));
        const partial = pageIds.some(name => selected.has(name)) || state.names?.some(name => selected.has(name));
        header.checked = Boolean(full);
        header.indeterminate = !full && Boolean(partial);
        header.disabled = busy || !pageIds.length;
        header.setAttribute('aria-busy', String(busy));
        header.setAttribute('aria-checked', full ? 'true' : partial ? 'mixed' : 'false');
        const action = full ? 'Clear all matching rows' : pageIds.every(name => selected.has(name)) ? 'Select all matching rows, including other pages' : 'Select all rows on this page';
        header.setAttribute('aria-label', action);
        header.title = `${action}. Cycle: this page, all matching rows, none.`;
    };
    header.addEventListener('change', async () => {
        if (busy) return;
        const selected = new Set(getSelected());
        if (state.all && state.names?.every(name => selected.has(name))) {
            const matching = new Set(state.names);
            setSelected([...selected].filter(name => !matching.has(name)));
            state.all = false;
        } else if (!pageIds.every(name => selected.has(name))) {
            if (setSelected([...new Set([...selected, ...pageIds])]) !== false) state.all = false;
        } else {
            busy = true; render();
            try {
                const names = await loadMatching();
                // Do not apply a response from a table that has since been replaced.
                if (!header.isConnected) return;
                if (setSelected([...new Set([...getSelected(), ...names])]) !== false) {
                    state.names = names; state.all = true;
                }
            } catch (error) { onError(error); }
            finally { busy = false; }
        }
        render();
    });
    render();
    return render;
}
export function selectionKey(url) {
    const key = new URL(url);
    for (const name of ['page', 'per_page', 'sort', 'direction', 'list_sort', 'list_direction']) key.searchParams.delete(name);
    key.searchParams.sort();
    return key.href;
}

if (typeof window !== 'undefined') window.SMSelection = { bindSelectionCycle, selectionKey };
