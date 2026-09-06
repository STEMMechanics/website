import { loadList } from './dynamic-lists';
let busy = false;
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-list-reorder]')) return;
    event.preventDefault();
    if (busy) return;
    const root = form.closest('[data-dynamic-list]');
    if (!root) return;
    busy = true;
    const buttons = [...root.querySelectorAll('[data-list-reorder] button')];
    const previous = buttons.map(button => button.disabled);
    buttons.forEach(button => button.disabled = true);
    const icon = event.submitter?.querySelector('i');
    const iconClass = icon?.className;
    if (icon) icon.className = 'fa-solid fa-circle-notch fa-spin';
    form.setAttribute('aria-busy', 'true');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', body: new FormData(form), headers: { Accept: 'application/json' }, signal: controller.signal });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not change the order. Please try again.');
        if (root.isConnected) {
            const url = new URL(window.location.href);
            // Show the saved manual order, even if the table was sorted by another column.
            url.searchParams.delete('list_sort'); url.searchParams.delete('list_direction');
            await loadList(root, url, { historyMode: 'replace' });
        }
    } catch (error) {
        SM.banner('Could not update order', error.name === 'AbortError' ? 'The request timed out. Refresh the list to check the saved order before trying again.' : error.message, 'danger');
    } finally {
        clearTimeout(timeout);
        buttons.forEach((button, index) => button.disabled = previous[index]);
        if (icon) icon.className = iconClass;
        form.removeAttribute('aria-busy');
        busy = false;
    }
});
