// Native dialogs provide focus trapping, Escape dismissal and mobile sheets.
export function openListDialog(dialog, trigger) {
    if (!dialog) return;
    document.querySelectorAll('[data-list-dialog][open]').forEach(item => item.close());
    if (!dialog.dataset.feedbackCleanup) {
        dialog.dataset.feedbackCleanup = 'true';
        dialog.addEventListener('close', () => {
            const popup = window.Swal?.getContainer?.();
            if (popup && dialog.contains(popup)) window.Swal.close();
        });
    }
    dialog.showModal();
    if (window.innerWidth >= 768) {
        const groups = [...dialog.querySelectorAll('.sm-filter-sections details')];
        groups.forEach((group, index) => { group.open = index === 0; });
    }
    if (window.innerWidth >= 768 && !dialog.classList.contains('sm-list-dialog-filters')) {
        const bounds = trigger?.getBoundingClientRect();
        const width = dialog.getBoundingClientRect().width;
        const height = dialog.getBoundingClientRect().height;
        dialog.style.setProperty('--dialog-left', `${Math.max(16, Math.min((bounds?.right ?? (window.innerWidth + width) / 2) - width, window.innerWidth - width - 16))}px`);
        const top = dialog.classList.contains('sm-list-dialog-actions') ? bounds?.top : bounds?.bottom;
        dialog.style.setProperty('--dialog-top', `${Math.max(16, Math.min(top ?? 100, window.innerHeight - height - 16))}px`);
    }
}

document.addEventListener('click', event => {
    const summary = event.target.closest('.sm-filter-sections summary');
    if (summary && window.innerWidth >= 768) {
        event.preventDefault();
        summary.closest('.sm-filter-sections').querySelectorAll('details').forEach(group => { group.open = group === summary.parentElement; });
    }
    const open = event.target.closest('[data-open-dialog]');
    if (open) { event.preventDefault(); openListDialog(document.getElementById(open.dataset.openDialog), open); }
    const close = event.target.closest('[data-close-dialog]');
    if (close) { event.preventDefault(); close.closest('dialog')?.close(); }
    if (event.target.matches('[data-list-dialog]')) {
        const rect = event.target.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) event.target.close();
    }
    const rowAction = event.target.closest('.sm-row-action:not(:disabled)');
    if (rowAction) rowAction.closest('dialog.sm-list-dialog-actions')?.close();
    const clear = event.target.closest('[data-clear-filter-fields]');
    if (clear) {
        clear.form.querySelectorAll('[data-tag-editor]').forEach(editor => editor.dispatchEvent(new CustomEvent('sm-tags-clear')));
        clear.form.querySelectorAll('input:not([type="hidden"]), select').forEach(input => { if (input.type === 'checkbox' || input.type === 'radio') input.checked = false; else input.value = input.dataset.clearValue || ''; input.dispatchEvent(new Event('change', { bubbles: true })); });
    }
});
