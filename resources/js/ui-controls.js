const navigationProtocols = new Set(['http:', 'https:', 'mailto:', 'tel:', 'sms:']);

const eventElement = (target) => target instanceof Element ? target : target instanceof Node ? target.parentElement : null;
const safeHref = (value) => {
    if (typeof value !== 'string') return null;
    const href = value.trim();
    if (!href || /[\u0000-\u001f\u007f\\]/.test(href)) return null;
    try {
        return navigationProtocols.has(new URL(href, document.baseURI).protocol) ? href : null;
    } catch {
        return null;
    }
};

// Native disabled buttons already block clicks. ARIA-disabled links need the same behaviour.
const guardControl = (event) => {
    const control = eventElement(event.target)?.closest('[data-ui-button]');
    if (!control) return;

    let blocked = control.matches(':disabled') || control.getAttribute('aria-disabled') === 'true';
    if (!blocked && control instanceof HTMLAnchorElement && control.hasAttribute('href')) {
        try {
            blocked = !navigationProtocols.has(new URL(control.getAttribute('href'), document.baseURI).protocol);
        } catch {
            blocked = true;
        }
    }
    if (blocked) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
};
document.addEventListener('click', guardControl, true);
document.addEventListener('auxclick', guardControl, true);

// Filter controls submit their own form without embedding native inline handlers.
document.addEventListener('change', (event) => {
    const control = eventElement(event.target)?.closest('[data-submit-on-change]');
    if (event.defaultPrevented || !control || control.matches(':disabled') || !control.form) return;
    control.form.requestSubmit();
});

// Formatting toolbars use one Tab stop; arrows move between visible, enabled controls.
const toolbars = new WeakSet();
window.SM = window.SM || {};
window.SM.safeHref = safeHref;
window.SM.initToolbar = (toolbar) => {
    if (!(toolbar instanceof HTMLElement) || toolbars.has(toolbar)) return;
    toolbars.add(toolbar);

    const buttons = () => Array.from(toolbar.querySelectorAll('button')).filter((button) =>
        !button.matches(':disabled') && button.getAttribute('aria-disabled') !== 'true'
        && button.getClientRects().length > 0
    );
    const setEntry = (entry) => toolbar.querySelectorAll('button').forEach((button) => {
        button.tabIndex = button === entry ? 0 : -1;
    });
    const refresh = () => {
        const available = buttons();
        setEntry(available.find((button) => button === document.activeElement)
            || available.find((button) => button.tabIndex === 0) || available[0]);
    };

    toolbar.addEventListener('focusin', (event) => {
        const button = eventElement(event.target)?.closest('button');
        if (button && toolbar.contains(button)) setEntry(button);
    });
    toolbar.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey
            || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const current = eventElement(event.target)?.closest('button');
        const available = buttons();
        const index = available.indexOf(current);
        if (index === -1) return;

        const rtl = getComputedStyle(toolbar).direction === 'rtl';
        const forward = event.key === (rtl ? 'ArrowLeft' : 'ArrowRight');
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? available.length - 1
            : (index + (forward ? 1 : -1) + available.length) % available.length;
        event.preventDefault();
        setEntry(available[next]);
        available[next].focus();
    });
    new MutationObserver(refresh).observe(toolbar, {
        subtree: true, childList: true, attributes: true, attributeFilter: ['style', 'hidden', 'disabled', 'aria-disabled'],
    });
    refresh();
};
