// Progressive enhancement: the server remains the source of filtering and pagination.
export function listUrl(form, submitter = null) {
    const url = new URL(form.action, window.location.href);
    url.search = new URLSearchParams(new FormData(form, submitter)).toString();
    for (const key of [...url.searchParams.keys()]) {
        if (key === 'page' || (key.endsWith('_page') && key !== 'per_page' && !key.endsWith('_per_page'))) url.searchParams.delete(key);
    }
    return url;
}

export function canLoadList(url, current = new URL(window.location.href)) {
    return url.origin === current.origin && url.pathname === current.pathname && !url.hash;
}

// Render only filter UI optimistically; totals and results still come from the server.
export function pendingFilters(url, fields) {
    return Object.entries(fields).flatMap(([key, field]) => {
        const values = [...url.searchParams].filter(([name]) => name === key || name.startsWith(`${key}[`)).map(([, value]) => value).filter(value => value !== '');
        if (!values.length || (field.default !== undefined && values.length === 1 && values[0] === String(field.default))) return [];
        const value = values[0];
        const display = field.type === 'array' ? (field.options ? values.map(value => field.options[value] ?? value).join(', ') : `${values.length} selected`) : field.options?.[value] ?? (field.type === 'boolean' ? (value === '0' ? 'No' : 'Yes') : value);
        return [{ key, label: `${field.label}: ${display}`, count: field.count !== false }];
    });
}

function clearFilter(url, key, field) {
    for (const name of [...url.searchParams.keys()]) {
        if (name === key || name.startsWith(`${key}[`) || name === 'page' || name.endsWith('_page')) url.searchParams.delete(name);
    }
    if (field.clear !== undefined && field.clear !== null) url.searchParams.set(key, field.clear);
}

export function previewListControls(root, url) {
    const restore = [];
    const schemas = [...root.querySelectorAll('[data-filter-controls]')].map(controls => {
        const fields = JSON.parse(controls.dataset.filterSchema);
        const chips = controls.querySelector('[data-filter-chips]');
        const badge = controls.querySelector('[data-filter-count]');
        const active = pendingFilters(url, fields);
        if (chips) {
            const previous = [...chips.childNodes], hidden = chips.hidden;
            restore.push(() => { chips.replaceChildren(...previous); chips.hidden = hidden; });
            const nodes = active.map(filter => {
                const link = document.createElement('a');
                const target = new URL(url);
                clearFilter(target, filter.key, fields[filter.key]);
                link.href = target.href;
                link.dataset.dynamicLink = '';
                link.className = 'sm-filter-chip';
                link.setAttribute('aria-label', `Remove filter: ${filter.label}`);
                link.append(document.createTextNode(`${filter.label} `));
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-xmark';
                icon.setAttribute('aria-hidden', 'true');
                link.append(icon);
                return link;
            });
            if (nodes.length) {
                const clear = document.createElement('a'), target = new URL(url);
                Object.entries(fields).forEach(([key, field]) => clearFilter(target, key, field));
                clear.href = target.href;
                clear.dataset.dynamicLink = '';
                clear.className = 'text-sm text-primary-color underline';
                clear.textContent = 'Clear filters';
                nodes.push(clear);
            }
            chips.replaceChildren(...nodes);
            chips.hidden = active.length === 0;
        }
        if (badge) {
            const text = badge.textContent, hidden = badge.hidden;
            restore.push(() => { badge.textContent = text; badge.hidden = hidden; });
            const count = active.filter(filter => filter.count).length;
            badge.textContent = String(count);
            badge.hidden = count === 0;
        }
        return { fields, search: controls.dataset.filterSearch };
    });
    // Presets compare filter values, ignoring pagination and sorting.
    for (const nav of root.querySelectorAll('.sm-preset-views')) {
        const schema = schemas[0];
        if (!schema) continue;
        const signature = target => JSON.stringify([...new Set([...Object.keys(schema.fields), schema.search].filter(Boolean))].sort().map(key => {
            const values = [...target.searchParams].filter(([name]) => name === key || name.startsWith(`${key}[`)).map(([, value]) => value).filter(Boolean);
            return [key, values.length ? values : schema.fields[key]?.default !== undefined ? [String(schema.fields[key].default)] : []];
        }));
        for (const link of nav.querySelectorAll('a[href]')) {
            const previous = link.getAttribute('aria-current');
            restore.push(() => previous === null ? link.removeAttribute('aria-current') : link.setAttribute('aria-current', previous));
            if (signature(new URL(link.href)) === signature(url)) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        }
    }
    return () => restore.reverse().forEach(undo => undo());
}

let undoPreview;
let controller, version = 0, debounce, searchSession = false;
const rootFor = name => [...document.querySelectorAll('[data-dynamic-list]')].find(root => root.dataset.dynamicList === name);
const setStatus = (root, busy, message = '') => {
    if (busy && root.getBoundingClientRect) {
        const region = [...root.querySelectorAll('[data-list-results], table, .sm-view-panels, [data-shop-results]')]
            .find(item => item.getBoundingClientRect().height > 0) || root.querySelector('[data-list-content]');
        const bounds = region.getBoundingClientRect();
        const rootBounds = root.getBoundingClientRect();
        // Centre over the visible results, below filters, without reserving a row above them.
        const visibleTop = Math.max(bounds.top, 100);
        const visibleBottom = Math.min(bounds.bottom, window.innerHeight);
        const centre = visibleBottom > visibleTop ? (visibleTop + visibleBottom) / 2 : bounds.top + Math.min(bounds.height / 2, 120);
        root.style.setProperty('--list-loader-top', `${Math.max(32, centre - rootBounds.top)}px`);
    }
    root.setAttribute('aria-busy', String(busy));
    root.querySelector('[data-list-status]').textContent = message;
};

export async function loadList(root, url, { historyMode = 'push', paging = false, search = false } = {}) {
    clearTimeout(debounce);
    controller?.abort();
    undoPreview?.();
    undoPreview = previewListControls(root, url);
    const requestController = new AbortController();
    controller = requestController;
    const signal = requestController.signal;
    let timedOut = false;
    const timeout = setTimeout(() => { timedOut = true; requestController.abort(); }, 20000);
    const requestVersion = ++version;
    const name = root.dataset.dynamicList;
    const active = document.activeElement;
    const focused = root.contains(active) && active instanceof HTMLInputElement ? {
        name: active.name, start: active.selectionStart, end: active.selectionEnd,
    } : null;
    // Bring the replacement area into view when paging from the footer.
    if (paging) root.querySelector('[data-list-content]')?.scrollIntoView({ block: 'start' });
    setStatus(root, true, 'Updating results…');
    try {
        const response = await fetch(url.href, {
            signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' },
        });
        if (signal.aborted || requestVersion !== version) return;
        // Authentication redirects and incompatible pages use their normal navigation flow.
        if (!response.ok || !canLoadList(new URL(response.url || url.href)) || !response.headers.get('content-type')?.includes('text/html')) {
            window.location.assign(url.href);
            return;
        }
        const html = await response.text();
        if (signal.aborted || requestVersion !== version) return;
        const page = new DOMParser().parseFromString(html, 'text/html');
        const replacement = [...page.querySelectorAll('[data-dynamic-list]')].find(item => item.dataset.dynamicList === name);
        const content = replacement?.querySelector('[data-list-content]');
        if (!content || content.querySelector('script, [wire\\:id]')) {
            window.location.assign(url.href);
            return;
        }
        const current = rootFor(name);
        if (!current) return;
        const target = current.querySelector('[data-list-content]');
        // Alpine's mutation observer destroys removed trees and initialises inserted controls.
        // Keep explicitly persistent controls (uploads, in-progress jobs, newsletter drafts).
        const preserved = [...(target.querySelectorAll?.('[data-list-preserve]') || [])];
        for (const existing of preserved) {
            const placeholder = [...content.querySelectorAll('[data-list-preserve]')]
                .find(item => item.dataset.listPreserve === existing.dataset.listPreserve);
            placeholder?.replaceWith(existing);
        }
        undoPreview = null;
        target.replaceChildren(...content.childNodes);
        document.title = page.title || document.title;
        if (historyMode !== 'none') {
            const mode = search && searchSession ? 'replaceState' : historyMode === 'replace' ? 'replaceState' : 'pushState';
            window.history[mode]({ ...window.history.state, dynamicList: name }, '', url.href);
        }
        searchSession = search;
        setStatus(current, false, 'Results updated.');
        window.SM?.updateAllThumbnails?.();
        document.dispatchEvent(new CustomEvent('sm:list-updated', { detail: { root: current, url: url.href } }));
        requestAnimationFrame(() => {
            if (requestVersion !== version) return;
            if (focused && !paging) {
                const input = [...current.querySelectorAll('input:not([type="hidden"])')].find(input => input.name === focused.name);
                input?.focus({ preventScroll: true });
                if (input && focused.start !== null) input.setSelectionRange(focused.start, focused.end);
            } else if (paging) {
                target.focus({ preventScroll: true });
                target.scrollIntoView({ block: 'start' });
            }
        });
    } catch (error) {
        if ((signal.aborted && !timedOut) || requestVersion !== version) return;
        undoPreview?.();
        undoPreview = null;
        if (historyMode === 'none') { window.location.assign(url.href); return; }
        setStatus(root, false, 'Could not update results. Please try again.');
        window.SM?.alert('Could not update results', 'Check your connection and try again.', 'danger');
    } finally {
        clearTimeout(timeout);
        if (requestVersion === version) rootFor(name)?.setAttribute('aria-busy', 'false');
    }
}

function initialiseLists() {
    const root = document.querySelector('[data-dynamic-list]');
    if (root) window.history.replaceState({ ...window.history.state, dynamicList: root.dataset.dynamicList }, '', window.location.href);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialiseLists);
else initialiseLists();

document.addEventListener('click', event => {
    if (event.defaultPrevented || (event.button ?? 0) !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    const root = link?.closest('[data-dynamic-list]');
    if (!root || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.hasAttribute('x-on:click.prevent')) return;
    const url = new URL(link.href);
    // Only list navigation is intercepted, never arbitrary action/download links.
    if (!link.closest('[data-dynamic-link], [data-view-tabs], nav[aria-label*="Pagination"], nav[aria-label*="pagination"]') || !canLoadList(url)) return;
    event.preventDefault();
    void loadList(root, url, { paging: url.searchParams.get('page') !== new URL(window.location.href).searchParams.get('page') });
}, { capture: true });

document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') return;
    const root = form.closest('[data-dynamic-list]') || (form.dataset.listForm ? rootFor(form.dataset.listForm) : null);
    if (!root || event.defaultPrevented) return;
    const url = listUrl(form, event.submitter);
    if (!canLoadList(url)) return;
    event.preventDefault();
    form.closest('dialog')?.close();
    void loadList(root, url);
});

document.addEventListener('input', event => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !(['search', 'q'].includes(input.name) || input.name.endsWith('_search')) || event.isComposing || input.closest('[data-list-dialog]')) return;
    const root = input.closest('[data-dynamic-list]');
    const form = input.form;
    if (!root || !form || form.method.toLowerCase() !== 'get') return;
    clearTimeout(debounce);
    controller?.abort();
    ++version;
    debounce = setTimeout(() => {
        const url = listUrl(form);
        if (canLoadList(url)) void loadList(root, url, { search: true });
    }, 350);
});

document.addEventListener('compositionstart', event => {
    const root = event.target instanceof HTMLInputElement ? event.target.closest('[data-dynamic-list]') : null;
    if (!root) return;
    clearTimeout(debounce);
    controller?.abort();
    ++version;
    undoPreview?.();
    undoPreview = null;
    setStatus(root, false);
});

document.addEventListener('compositionend', event => {
    if (event.target instanceof HTMLInputElement) event.target.dispatchEvent(new Event('input', { bubbles: true }));
});

window.addEventListener('popstate', event => {
    const root = event.state?.dynamicList ? rootFor(event.state.dynamicList) : null;
    if (root) void loadList(root, new URL(window.location.href), { historyMode: 'none' });
});
