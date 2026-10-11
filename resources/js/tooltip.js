const tooltip = document.createElement('div');
tooltip.id = 'sm-ui-tooltip';
tooltip.setAttribute('role', 'tooltip');
tooltip.className = 'fixed z-[10000] max-w-64 rounded-sm border border-yellow-400 bg-yellow-200 p-1 text-xs text-yellow-900 shadow-md';
tooltip.hidden = true;
document.body.appendChild(tooltip);

let current = null;
let hideTimer = null;
let dismissed = null;
const element = (target) => target instanceof Element ? target : target instanceof Node ? target.parentElement : null;
const trigger = (target) => element(target)?.closest('[data-tooltip]');

const position = () => {
    if (!current || !current.isConnected || !current.getClientRects().length) return hide();
    const bounds = current.getBoundingClientRect();
    const gap = 8;
    const width = tooltip.offsetWidth;
    const height = tooltip.offsetHeight;
    tooltip.style.left = `${Math.max(gap, Math.min(bounds.left, window.innerWidth - width - gap))}px`;
    const below = bounds.bottom + gap;
    tooltip.style.top = `${Math.max(gap, below + height <= window.innerHeight - gap ? below : bounds.top - height - gap)}px`;
};

const hide = () => {
    clearTimeout(hideTimer);
    if (current) {
        const descriptions = (current.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== tooltip.id);
        if (descriptions.length) current.setAttribute('aria-describedby', descriptions.join(' '));
        else current.removeAttribute('aria-describedby');
    }
    current = null;
    tooltip.hidden = true;
};

const show = (control) => {
    clearTimeout(hideTimer);
    if (!control || control === dismissed || !control.getAttribute('data-tooltip')) return;
    if (current !== control) hide();
    current = control;
    const host = control.closest('dialog[open]') || document.body;
    if (tooltip.parentElement !== host) host.appendChild(tooltip);
    // Tooltip content is always text, including values supplied by users.
    tooltip.textContent = control.getAttribute('data-tooltip');
    const descriptions = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    descriptions.add(tooltip.id);
    control.setAttribute('aria-describedby', Array.from(descriptions).join(' '));
    tooltip.hidden = false;
    position();
};

const scheduleHide = () => {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        if (current?.contains(document.activeElement) || current?.matches(':hover') || tooltip.matches(':hover')) return;
        hide();
    }, 100);
};

document.addEventListener('pointerover', (event) => {
    if (tooltip.contains(element(event.target))) clearTimeout(hideTimer);
    else show(trigger(event.target));
});
document.addEventListener('pointerout', (event) => {
    if (current && (current.contains(element(event.relatedTarget)) || tooltip.contains(element(event.relatedTarget)))) return;
    if (trigger(event.target) === dismissed) dismissed = null;
    scheduleHide();
});
document.addEventListener('focusin', (event) => show(trigger(event.target)));
document.addEventListener('focusout', (event) => {
    if (trigger(event.target) === dismissed) dismissed = null;
    scheduleHide();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && current) {
        dismissed = current;
        hide();
    }
});
document.addEventListener('pointerdown', (event) => {
    if (event.pointerType === 'touch') {
        const control = trigger(event.target);
        if (control) show(control);
        else hide();
    }
});
window.addEventListener('scroll', position, { passive: true, capture: true });
window.addEventListener('resize', position, { passive: true });
