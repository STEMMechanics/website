import { loadList } from './dynamic-lists';
import { openListDialog } from './list-controls';

async function send(form) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
        const response = await fetch(form.action, {
            method: 'POST', body: new FormData(form), credentials: 'same-origin', signal: controller.signal,
            headers: { Accept: 'application/json' },
        });
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Refresh the page and try again.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Please try again.');
        return data;
    } finally { clearTimeout(timeout); }
}

let loadVersion = 0;
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.matches('[data-bulk-open]')) {
        event.preventDefault();
        const dialog = document.getElementById(form.dataset.bulkOpen);
        const content = dialog.querySelector('[data-bulk-editor-content]');
        const version = ++loadVersion;
        content.replaceChildren(document.getElementById(content.dataset.bulkLoader).content.cloneNode(true));
        content.setAttribute('aria-busy', 'true');
        openListDialog(dialog, event.submitter);
        try {
            const data = await send(form);
            if (version === loadVersion && dialog.open) content.innerHTML = data.html;
        } catch (error) {
            if (version === loadVersion && dialog.open) {
                content.replaceChildren();
                SM.banner('Could not open bulk editor', error.message, 'danger', { target: dialog });
            }
        } finally { if (version === loadVersion) content.setAttribute('aria-busy', 'false'); }
    } else if (form.matches('[data-bulk-save]')) {
        event.preventDefault();
        if (form.dataset.saving) return;
        form.dataset.saving = 'true';
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        const dialog = form.closest('dialog');
        const content = dialog.querySelector('[data-bulk-editor-content]');
        try {
            const data = await send(form);
            // Clear only the IDs saved by this editor, preserving selections made in another view.
            const saved = new Set(new FormData(form).getAll(content.dataset.bulkSelectionField));
            const key = content.dataset.bulkSelectionKey;
            try { sessionStorage.setItem(key, JSON.stringify(JSON.parse(sessionStorage.getItem(key) || '[]').filter(id => !saved.has(String(id))))); } catch {}
            if (dialog.contains(form)) dialog.close();
            const root = [...document.querySelectorAll('[data-dynamic-list]')].find(item => item.dataset.dynamicList === content.dataset.bulkList);
            if (root) await loadList(root, new URL(window.location.href), { historyMode: 'replace' });
            SM.banner('Bulk edit complete', data.message, 'success');
        } catch (error) {
            SM.banner('Could not save changes', error.message, 'danger', { target: dialog.open ? dialog : undefined });
        } finally { button.disabled = false; delete form.dataset.saving; }
    }
});
