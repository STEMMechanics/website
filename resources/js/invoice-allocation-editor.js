document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('[data-allocation-inline]')) return;
    event.preventDefault();
    if (form.dataset.saving) return;
    form.dataset.saving = 'true';
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Refresh the page and try again.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Please try again.');
        if (typeof data.html === 'string') form.parentElement.innerHTML = data.html;
        SM.banner('Saved', data.message, 'success');
    } catch (error) {
        SM.banner('Could not save allocation', error.message, 'danger');
    } finally {
        button.disabled = false;
        delete form.dataset.saving;
        form.removeAttribute('aria-busy');
    }
});
document.addEventListener('click', async event => {
    const link = event.target.closest('[data-allocation-load]');
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    const form = link.closest('form');
    if (form.dataset.saving) return;
    form.dataset.saving = 'true';
    form.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(link.href, { credentials: 'same-origin' });
        if (!response.ok || response.redirected) throw new Error('The plan could not be loaded. Please try again.');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = doc.querySelector('[data-allocation-inline]');
        if (!replacement) throw new Error('The allocation editor could not be loaded.');
        form.replaceWith(replacement);
    } catch (error) {
        SM.banner('Could not load plan', error.message, 'danger');
    } finally { delete form.dataset.saving; form.removeAttribute('aria-busy'); }
});
