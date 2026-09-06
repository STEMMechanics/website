import { loadList } from './dynamic-lists';
import { openListDialog } from './list-controls';
import { bindSelectionCycle, selectionKey } from './selection-cycle';
import { initialisePageUpload } from './page-upload';

const storageKey = 'admin-media-bulk-selection';
const notify = (title, message, tone, target = null) => SM.banner(title, message, tone, { target });
let selected = [], selecting = false, clearedFlash = false;
try { selected = [...new Set(JSON.parse(sessionStorage.getItem(storageKey) || '[]').filter(item => typeof item === 'string'))].slice(0, 5000); } catch {}
const refresh = () => {
    const root = document.querySelector('[data-dynamic-list="admin-media-index"]');
    return root ? loadList(root, new URL(window.location.href), { historyMode: 'replace' }) : Promise.resolve();
};
const saveSelection = () => { try { sessionStorage.setItem(storageKey, JSON.stringify(selected)); } catch {} };

async function requestJson(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 30000);
    try {
        const response = await fetch(url, { ...options, signal: controller.signal, credentials: 'same-origin', headers: {
            Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        } });
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Refresh the page and try again.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Please try again.');
        return data;
    } finally { clearTimeout(timer); }
}

function initialiseSelection() {
    const root = document.querySelector('[data-media-selection]');
    if (!root) return;
    if (root.hasAttribute('data-clear-selection') && !clearedFlash) { selected = []; clearedFlash = true; }
    const items = [...root.querySelectorAll('.admin-media-select-item')];
    const page = root.querySelector('#admin-media-select-page');
    const toggle = root.querySelector('[data-toggle-selection]');
    let renderHeader = () => {};
    const render = () => {
        saveSelection();
        root.dataset.selecting = String(selecting || selected.length > 0);
        items.forEach(input => { input.checked = selected.includes(input.value); });
        renderHeader();
        toggle.textContent = selecting || selected.length ? 'Cancel' : 'Select';
        root.querySelector('#admin-media-edit-selected').textContent = `Edit ${selected.length} ${selected.length === 1 ? 'item' : 'items'}`;
        root.querySelector('#admin-media-selection-toolbar').dataset.selected = String(selected.length > 0);
        root.querySelector('#admin-media-edit-selected').disabled = !selected.length;
        root.querySelector('#admin-media-bulk-inputs').replaceChildren(...selected.map(name => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'media_names[]'; input.value = name; return input;
        }));
    };
    const setSelection = names => {
        if (names.length > 5000) { notify('Selection limit', 'Select up to 5000 files at a time.', 'warning'); render(); return false; }
        selected = [...new Set(names)]; render();
    };
    items.forEach(input => input.addEventListener('change', () => setSelection(input.checked ? [...selected, input.value] : selected.filter(name => name !== input.value))));
    renderHeader = bindSelectionCycle({ header: page, pageIds: items.map(item => item.value), getSelected: () => selected, setSelected: setSelection,
        key: selectionKey(root.dataset.selectionUrl), loadMatching: async () => (await requestJson(root.dataset.selectionUrl)).names,
        onError: error => notify('Could not select files', error.message, 'danger') });
    toggle.addEventListener('click', () => { if (selecting || selected.length) { selected = []; selecting = false; } else selecting = true; render(); });
    render();
}

function initialiseMediaPage() {
    const uploadRoot = document.querySelector('[data-page-upload]');
    if (!document.querySelector('[data-dynamic-list="admin-media-index"]')) return;
    initialiseSelection();
    document.addEventListener('sm:list-updated', event => { if (event.detail.root.dataset.dynamicList === 'admin-media-index') initialiseSelection(); });
    if (uploadRoot) initialisePageUpload(uploadRoot, files => new Promise(resolve => {
        const status = document.getElementById('admin-media-bulk-upload-status');
        const text = document.getElementById('admin-media-bulk-upload-status-text');
        const percent = document.getElementById('admin-media-bulk-upload-status-percent');
        const bar = document.getElementById('admin-media-bulk-upload-status-bar');
        status.classList.remove('hidden'); text.textContent = `Preparing ${files.length} file(s)…`;
        const total = files.reduce((sum, file) => sum + file.size, 0) || 1;
        const fail = message => { status.classList.add('hidden'); notify('Upload failed', message, 'danger'); resolve(); };
        try {
            SM.upload(files, async response => {
                if (!response?.success) { fail('The upload did not complete. Please try again.'); return; }
                status.classList.add('hidden');
                notify('Upload complete', `${files.length} file(s) added to your media library.`, 'success');
                await refresh(); resolve();
            }, files.map(file => SM.toTitleCase(file.name)), {
                showModal: false, successDelayMs: 0,
                onProgress: ({ file, index, count, percent: progress }) => {
                    const completed = files.slice(0, index).reduce((sum, item) => sum + item.size, 0);
                    const overall = Math.round((completed + file.size * progress / 100) / total * 100);
                    text.textContent = `Uploading ${index + 1} of ${count}: ${file.name}`;
                    percent.textContent = `${overall}%`; bar.style.width = `${overall}%`;
                }, onError: fail,
            });
        } catch (error) { fail(error.message); }
    }));
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialiseMediaPage);
else initialiseMediaPage();

document.addEventListener('click', event => {
    const edit = event.target.closest('[data-edit-media]');
    if (edit) {
        const data = JSON.parse(edit.dataset.editMedia);
        const dialog = document.getElementById('media-edit-dialog');
        const form = dialog.querySelector('form'); form.dataset.url = data.url;
        for (const field of ['title', 'caption', 'tags', 'visibility']) form.elements[field].value = data[field] || '';
        openListDialog(dialog, edit);
    }
    const copy = event.target.closest('[data-copy-media]');
    if (copy) { copy.closest('dialog')?.close(); SM.copyToClipboard(copy.dataset.copyMedia); }
    const remove = event.target.closest('[data-delete-media]');
    if (remove) {
        remove.closest('dialog')?.close();
        SM.confirm('Delete media?', 'This permanently deletes the file and may affect places using it. This cannot be undone.', 'Delete file', async confirmed => {
            if (!confirmed) return;
            remove.disabled = true;
            try {
                await requestJson(remove.dataset.deleteMedia, { method: 'DELETE' });
                selected = selected.filter(name => name !== remove.dataset.mediaName); saveSelection();
                await refresh(); notify('Media deleted', 'The file has been removed.', 'success');
            } catch (error) { notify('Could not delete media', error.message, 'danger'); }
            finally { remove.disabled = false; }
        });
    }
});
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('[data-media-edit-form]')) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]'); button.disabled = true;
    try {
        await requestJson(form.dataset.url, { method: 'PATCH', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
        form.closest('dialog').close(); await refresh(); notify('Media updated', 'Your changes have been saved.', 'success');
    } catch (error) { notify('Could not save media', error.message, 'danger', form.closest('dialog')); }
    finally { button.disabled = false; }
});

window.bulkWorkshopEditor = function (workshops, initialAdded) {
        return {
            workshops,
            search: '',
            added: initialAdded.map(String),
            results() {
                const term = this.search.trim().toLowerCase();
                if (term.length < 2) return [];
                return this.workshops.filter((workshop) => workshop.search.includes(term) && !this.added.includes(workshop.id)).slice(0, 25);
            },
            addedWorkshops() {
                return this.workshops.filter((workshop) => this.added.includes(workshop.id));
            },
            add(id) {
                id = String(id);
                if (!this.added.includes(id)) this.added.push(id);
                this.search = '';
            },
            remove(id) {
                this.added = this.added.filter((item) => item !== String(id));
            },
        };
    }


const bulkOriginalValues = new WeakMap();
let bulkLoadVersion = 0;
function bulkFormValues(form) {
    const values = {};
    for (const [key, value] of new FormData(form)) {
        if (key.endsWith('[]')) (values[key.slice(0, -2)] ||= []).push(value);
        else values[key] = value;
    }
    return values;
}
document.addEventListener('submit', async event => {
    const form = event.target;
    if (form.id === 'admin-media-bulk-form') {
        event.preventDefault();
        const names = [...selected];
        if (!names.length) return;
        const version = ++bulkLoadVersion;
        const dialog = document.getElementById('media-bulk-edit-dialog');
        const content = dialog.querySelector('[data-bulk-editor-content]');
        content.replaceChildren(document.getElementById('media-bulk-loader').content.cloneNode(true));
        content.setAttribute('aria-busy', 'true');
        openListDialog(dialog, event.submitter);
        try {
            const data = await requestJson(form.action, { method: 'POST', body: JSON.stringify({ media_names: names }) });
            if (version !== bulkLoadVersion || !dialog.open) return;
            content.innerHTML = data.html;
            // Alpine initialises the shared tags/workshop controls after insertion.
            requestAnimationFrame(() => {
                const editor = content.querySelector('[data-media-bulk-edit-form]');
                if (version === bulkLoadVersion && editor) bulkOriginalValues.set(editor, bulkFormValues(editor));
            });
        } catch (error) {
            if (version === bulkLoadVersion && dialog.open) {
                content.replaceChildren();
                notify('Could not open bulk editor', error.message, 'danger', dialog);
            }
        } finally { if (version === bulkLoadVersion) content.setAttribute('aria-busy', 'false'); }
        return;
    }
    if (!form.matches('[data-media-bulk-edit-form]')) return;
    event.preventDefault();
    if (form.dataset.saving === 'true') return;
    if (form.contains(document.activeElement)) document.activeElement.blur();
    const values = bulkFormValues(form), original = bulkOriginalValues.get(form);
    if (!original) return;
    const changes = Object.fromEntries(Object.entries(values).filter(([key, value]) =>
        key === 'media_names' || (!['_token', '_method'].includes(key) && JSON.stringify(value) !== JSON.stringify(original[key]))));
    const button = form.querySelector('[type="submit"]');
    button.disabled = true; form.dataset.saving = 'true';
    const dialog = form.closest('dialog');
    try {
        const data = await requestJson(form.action, { method: 'PUT', body: JSON.stringify(changes) });
        const saved = new Set(values.media_names);
        selected = selected.filter(name => !saved.has(name)); saveSelection();
        if (dialog.contains(form)) dialog.close();
        await refresh();
        notify('Bulk edit complete', data.message, 'success');
    } catch (error) { notify('Could not save media', error.message, 'danger', dialog.open ? dialog : null); }
    finally { button.disabled = false; delete form.dataset.saving; }
});
