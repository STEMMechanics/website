import { loadList } from './dynamic-lists';
import { openListDialog } from './list-controls';

let controller;
document.addEventListener('click', async event => {
    const link = event.target.closest('[data-record-editor]');
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const dialog = document.getElementById('record-editor');
    if (!dialog) return;
    event.preventDefault();
    controller?.abort();
    controller = new AbortController();
    const signal = controller.signal;
    const content = dialog.querySelector('[data-record-content]');
    content.replaceChildren(dialog.querySelector('[data-record-loader]').content.cloneNode(true));
    content.setAttribute('aria-busy', 'true');
    openListDialog(dialog, link);
    try {
        const response = await fetch(link.href, { signal, credentials: 'same-origin' });
        if (!response.ok || response.redirected) throw new Error('Refresh the page and try again.');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const form = doc.querySelector('[data-record-form]');
        if (!form) throw new Error('The editor could not be loaded. Refresh the page and try again.');
        if (signal.aborted || !dialog.open) return;
        content.replaceChildren(form);
        dialog.querySelector('h2').textContent = link.getAttribute('data-record-title') || link.textContent.trim() || link.getAttribute('aria-label') || 'Edit details';
        form.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
    } catch (error) {
        if (signal.aborted) return;
        content.replaceChildren();
        SM.banner('Could not open editor', error.message, 'danger', { target: dialog });
    } finally { if (!signal.aborted) content.setAttribute('aria-busy', 'false'); }
});
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('[data-record-form]') || !form.closest('#record-editor')) return;
    event.preventDefault();
    if (form.dataset.saving) return;
    form.dataset.saving = 'true';
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    const dialog = form.closest('dialog');
    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Refresh the page and try again.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Please try again.');
        document.dispatchEvent(new CustomEvent('sm:record-saved', { detail: data }));
        dialog.close();
        const target = data.target ? document.getElementById(data.target) : null;
        if (target?.hasAttribute('data-record-refresh') && typeof data.html === 'string') target.innerHTML = data.html;
        const root = document.querySelector('[data-dynamic-list]');
        if (root) await loadList(root, new URL(window.location.href), { historyMode: 'replace' });
        SM.banner('Saved', data.message, 'success');
    } catch (error) {
        SM.banner('Could not save changes', error.message, 'danger', { target: dialog.open ? dialog : undefined });
    } finally { button.disabled = false; delete form.dataset.saving; }
});
