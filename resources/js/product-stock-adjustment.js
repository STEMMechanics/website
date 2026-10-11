import { loadList } from './dynamic-lists';
import { openListDialog } from './list-controls';

let loadingController;
let currentStock = null;
let saving = false;
let endpoint = '';

const dialogForStock = () => document.getElementById('product-stock-adjustment');
const choiceFor = (dialog) => currentStock?.choices?.find((choice) => String(choice.variant_id ?? '') === dialog.querySelector('[data-stock-variant]').value);
const quantityLabel = (quantity, choice) => `${Number(quantity).toLocaleString('en-AU')} ${quantity === 1 ? choice.unit : choice.plural_unit}`;

function clearFeedback(dialog) {
    dialog.querySelectorAll('.sm-feedback-inline').forEach((feedback) => { feedback.hidden = true; });
}

function renderPreview(dialog) {
    const choice = choiceFor(dialog);
    const raw = dialog.querySelector('[data-stock-change-input]').value.trim();
    const change = raw === '' ? null : Number(raw);
    const validChange = Number.isInteger(change) && change !== 0 && Math.abs(change) <= 1000000;
    const tracked = Number.isInteger(choice?.quantity);
    const after = tracked && validChange ? choice.quantity + change : null;
    const validResult = after !== null && after >= 0 && after <= 2147483647;
    dialog.querySelector('[data-stock-current]').textContent = tracked ? quantityLabel(choice.quantity, choice) : 'Not tracked';
    dialog.querySelector('[data-stock-result]').textContent = validResult ? quantityLabel(after, choice) : '—';
    dialog.querySelector('[data-stock-save]').disabled = saving || !validResult;
    dialog.querySelector('[data-stock-variant]').disabled = saving || currentStock?.shared;
    const editorLink = dialog.querySelector('[data-stock-editor-link]');
    editorLink.classList.toggle('hidden', tracked);
    dialog.querySelector('[data-stock-unit-info]').textContent = currentStock?.shared
        ? `Whole sellable ${choice?.plural_unit || 'items'} · shared by all packs.`
        : `Sellable ${choice?.plural_unit || 'packs'} for this variant.`;
    const message = dialog.querySelector('[data-stock-preview-message]');
    message.classList.toggle('text-red-700', tracked && (!validChange || !validResult));
    message.textContent = !tracked
        ? 'Stock is not tracked for this option. Enable it in the product editor first.'
        : !validChange
            ? 'Enter a whole number other than zero.'
            : !validResult
                ? after < 0 ? 'This would remove more stock than is available.' : 'The resulting stock count is too large.'
                : change < 0
                    ? ''
                    : 'Waiting orders may receive new stock first.';
    message.classList.toggle('hidden', message.textContent === '');
    dialog.querySelector('[data-stock-change-input]').setAttribute('aria-invalid', String(tracked && (!validChange || !validResult)));
}

function renderStock(dialog, stock, selected = '') {
    currentStock = stock;
    const select = dialog.querySelector('[data-stock-variant]');
    select.replaceChildren(...stock.choices.map((choice) => new Option(choice.name, String(choice.variant_id ?? ''))));
    select.value = stock.choices.some((choice) => String(choice.variant_id ?? '') === selected)
        ? selected : String(stock.choices.find((choice) => choice.quantity !== null)?.variant_id ?? '');
    dialog.querySelector('[data-stock-product-title]').textContent = stock.title;
    dialog.querySelector('[data-stock-variant-field]').classList.toggle('hidden', stock.shared || stock.choices.length < 2);
    dialog.querySelector('[data-stock-editor-link]').href = stock.edit_url;
    dialog.querySelector('[data-stock-fields]').classList.remove('hidden');
    dialog.querySelector('[data-stock-fields]').disabled = false;
    renderPreview(dialog);
}

async function readResponse(response) {
    if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Your session may have expired. Refresh the page and try again.');
    }
    const data = await response.json();
    if (!response.ok) {
        const error = new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Please try again.');
        error.stockChanged = Boolean(data.errors?.expected_quantity);
        throw error;
    }
    return data;
}

async function loadStock(dialog, selected = '') {
    loadingController?.abort();
    const controller = new AbortController();
    loadingController = controller;
    dialog.querySelector('[data-stock-loading]').classList.remove('hidden');
    dialog.querySelector('[data-stock-fields]').disabled = true;
    dialog.querySelector('[data-stock-save]').disabled = true;
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
        const response = await fetch(endpoint, { signal: controller.signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        const data = await readResponse(response);
        if (!controller.signal.aborted && dialog.open) renderStock(dialog, data.stock, selected);
    } catch (error) {
        if (loadingController !== controller) return;
        throw error;
    } finally {
        clearTimeout(timeout);
        if (loadingController === controller) dialog.querySelector('[data-stock-loading]').classList.add('hidden');
    }
}

document.addEventListener('click', async (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-product-stock-adjustment]') : null;
    if (!trigger || saving) return;
    const dialog = dialogForStock();
    if (!dialog) return;
    event.preventDefault();
    endpoint = trigger.dataset.stockUrl;
    currentStock = null;
    dialog.querySelector('form').reset();
    dialog.querySelector('[data-stock-product-title]').textContent = trigger.dataset.stockTitle;
    dialog.querySelector('[data-stock-fields]').classList.add('hidden');
    dialog.querySelector('[data-stock-editor-link]').classList.add('hidden');
    clearFeedback(dialog);
    openListDialog(dialog, trigger);
    try {
        await loadStock(dialog);
        if (dialog.open && currentStock) {
            const input = dialog.querySelector('[data-stock-change-input]');
            input.focus();
            input.select();
        }
    } catch (error) {
        if (dialog.open) window.SM.banner('Could not load stock', error.name === 'AbortError' ? 'Loading took too long. Close this popup and try again.' : error.message, 'danger', { target: dialog });
    }
});

document.addEventListener('input', (event) => {
    if (event.target instanceof Element && event.target.matches('[data-stock-change-input]')) renderPreview(dialogForStock());
});
document.addEventListener('change', (event) => {
    if (event.target instanceof Element && event.target.matches('[data-stock-variant]')) renderPreview(dialogForStock());
});
document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-stock-change]') : null;
    if (!button || saving) return;
    const dialog = dialogForStock();
    const input = dialog.querySelector('[data-stock-change-input]');
    input.value = button.dataset.stockChange;
    renderPreview(dialog);
    input.focus();
    input.select();
});

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-product-stock-form]')) return;
    event.preventDefault();
    const dialog = dialogForStock();
    const choice = choiceFor(dialog);
    if (saving || dialog.querySelector('[data-stock-save]').disabled || !choice) return;
    const payload = {
        variant_id: choice.variant_id,
        expected_quantity: choice.quantity,
        change: Number(form.elements.namedItem('change').value),
        reason: form.elements.namedItem('reason').value,
    };
    const selected = dialog.querySelector('[data-stock-variant]').value;
    saving = true;
    clearFeedback(dialog);
    dialog.querySelector('[data-stock-fields]').disabled = true;
    dialog.querySelector('[data-stock-save]').disabled = true;
    dialog.querySelector('[data-stock-save]').textContent = 'Saving…';
    try {
        const response = await fetch(endpoint, {
            method: 'PATCH', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements.namedItem('_token').value },
            body: JSON.stringify(payload),
        });
        const data = await readResponse(response);
        currentStock = data.stock;
        dialog.close();
        window.SM.banner('Stock adjusted', data.message, 'success');
        const root = document.querySelector('[data-dynamic-list="admin-shop-product"]');
        if (root) await loadList(root, new URL(window.location.href), { historyMode: 'replace' });
    } catch (error) {
        if (error.stockChanged && dialog.open) {
            try { await loadStock(dialog, selected); } catch (_) { currentStock = null; }
        }
        window.SM.banner('Could not adjust stock', error.message, 'danger', { target: dialog.open ? dialog : undefined });
    } finally {
        saving = false;
        dialog.querySelector('[data-stock-save]').textContent = 'Save adjustment';
        dialog.querySelector('[data-stock-fields]').disabled = !currentStock;
        if (currentStock) renderPreview(dialog);
    }
});
