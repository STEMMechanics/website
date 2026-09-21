// Use the normal submit event so validation, confirmations and popup saves still run.
document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.isComposing || event.repeat || event.key !== 'Enter' || event.altKey || event.shiftKey) return;
    const target = event.target;
    if (!(target instanceof HTMLElement) || target.closest('[data-no-keyboard-submit]')) return;
    const shortcut = event.metaKey || event.ctrlKey;
    const textInput = target instanceof HTMLInputElement && ['text', 'search', 'email', 'url', 'tel', 'password', 'number'].includes(target.type);
    const multiline = target instanceof HTMLTextAreaElement || target.isContentEditable;
    if (!textInput && !(shortcut && multiline)) return;
    // Autocomplete and tag widgets own their Enter key behaviour.
    if (!shortcut && target.closest('[role="combobox"], [role="listbox"]')) return;
    const form = target.form || target.closest('form');
    if (!(form instanceof HTMLFormElement) || form.method === 'dialog') return;
    const submitter = [...form.elements].find((control) =>
        (control instanceof HTMLButtonElement || control instanceof HTMLInputElement)
        && ['submit', 'image'].includes(control.type)
        && control.getClientRects().length > 0 && getComputedStyle(control).visibility !== 'hidden');
    if (!submitter) return;
    event.preventDefault();
    if (submitter.matches(':disabled') || submitter.getAttribute('aria-disabled') === 'true' || form.dataset.saving === 'true' || form.getAttribute('aria-busy') === 'true') return;
    target.blur(); // Commit change-based fields before their submit handler reads them.
    form.requestSubmit(submitter);
});
