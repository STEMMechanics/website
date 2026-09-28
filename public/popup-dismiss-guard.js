(() => {
    const isDismissiblePopup = (element) => {
        if (!element || typeof element.contains !== 'function') return false;

        if (String(element.tagName || '').toLowerCase() === 'dialog' && element.open) return true;
        if (element.hasAttribute?.('data-list-dialog') && element.open) return true;

        const attributes = Array.from(element.attributes || []);
        if (attributes.some(({ name }) => /^(?:@|x-on:)click\.(?:outside|self)(?:\.|$)/.test(name))) return true;

        const classes = new Set(String(element.className || '').split(/\s+/));
        const fullScreenOverlay = (classes.has('fixed') || classes.has('absolute')) && classes.has('inset-0');
        return fullScreenOverlay && (
            element.hasAttribute?.('x-show')
            || element.hasAttribute?.('aria-modal')
            || element.getAttribute?.('role') === 'dialog'
        );
    };

    const eventPath = (event) => typeof event.composedPath === 'function' ? event.composedPath() : [event.target];
    const hasDirective = (element, expression) => Array.from(element.attributes || []).some(({ name }) => expression.test(name));

    const pointerIsOutsidePopup = (popup, event) => {
        if (typeof event.clientX !== 'number' || typeof event.clientY !== 'number' || typeof popup.getBoundingClientRect !== 'function') return false;

        const bounds = popup.getBoundingClientRect();
        return event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom;
    };

    const isDismissalBackdrop = (element) => {
        const classes = new Set(String(element?.className || '').split(/\s+/));
        return (classes.has('fixed') || classes.has('absolute'))
            && classes.has('inset-0')
            && hasDirective(element, /^(?:@|x-on:)click(?:\.|$)/);
    };

    const isOutsidePopup = (popup, event) => {
        // Pointer capture can keep event.target inside the dialog after release outside it.
        if (pointerIsOutsidePopup(popup, event)) return true;

        if (String(popup.tagName || '').toLowerCase() === 'dialog' && event.target === popup) {
            const bounds = popup.getBoundingClientRect();
            return event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom;
        }
        if (event.target === popup && hasDirective(popup, /^(?:@|x-on:)click\.self(?:\.|$)/)) return true;

        if (eventPath(event).some((element) => {
            return element !== popup && popup.contains(element) && isDismissalBackdrop(element);
        })) return true;

        return !popup.contains(event.target);
    };

    let pointerStart = null;
    let mouseStart = null;
    let pendingDragClick = null;

    const clearPendingDragClick = () => {
        if (!pendingDragClick) return;
        window.clearTimeout(pendingDragClick.timer);
        pendingDragClick = null;
    };

    const onPressStart = (event) => {
        clearPendingDragClick();

        if (event.button !== 0 || (event.pointerType && !['mouse', 'pen'].includes(event.pointerType))) return null;

        const popups = eventPath(event).filter((element) => isDismissiblePopup(element) && !isOutsidePopup(element, event));
        if (!popups.length) return null;

        return { pointerId: event.pointerId, popups };
    };

    const onPressEnd = (start, event) => {
        if (!start || (start.pointerId != null && event.pointerId != null && start.pointerId !== event.pointerId)) return;

        const affectedPopups = start.popups.filter((popup) => isOutsidePopup(popup, event));
        if (!affectedPopups.length) return;

        clearPendingDragClick();
        pendingDragClick = {
            popups: affectedPopups,
            timer: window.setTimeout(clearPendingDragClick, 250),
        };
    };

    const onPointerDown = (event) => { pointerStart = onPressStart(event); };
    const onPointerUp = (event) => {
        const start = pointerStart;
        pointerStart = null;
        onPressEnd(start, event);
    };
    const onMouseDown = (event) => {
        // Keep a separate mouse record: text selection can cancel the pointer stream,
        // while the browser still emits mouseup and click for the same drag.
        mouseStart = onPressStart(event);
    };
    const onMouseUp = (event) => {
        const start = mouseStart;
        mouseStart = null;
        onPressEnd(start, event);
    };
    const onPointerCancel = () => { pointerStart = null; };
    const onClick = (event) => {
        if (!pendingDragClick) return;

        const { popups } = pendingDragClick;
        clearPendingDragClick();
        if (!popups.some((popup) => isOutsidePopup(popup, event))) return;

        event.preventDefault?.();
        event.stopImmediatePropagation?.();
    };

    document.addEventListener('pointerdown', onPointerDown, true);
    document.addEventListener('pointerup', onPointerUp, true);
    document.addEventListener('mousedown', onMouseDown, true);
    document.addEventListener('mouseup', onMouseUp, true);
    document.addEventListener('pointercancel', onPointerCancel, true);
    document.addEventListener('click', onClick, true);
})();
