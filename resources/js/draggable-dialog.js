// Keep a movable desktop dialog within the viewport; mobile retains its sheet layout.
export function attachDraggableDialog(dialog) {
    const header = dialog.querySelector('.sm-list-dialog-header');
    let drag = null, position = null;
    const desktop = () => window.innerWidth >= 768;
    const place = (left, top) => {
        const bounds = dialog.getBoundingClientRect();
        position = {
            left: Math.max(16, Math.min(left, window.innerWidth - bounds.width - 16)),
            top: Math.max(16, Math.min(top, window.innerHeight - bounds.height - 16)),
        };
        Object.assign(dialog.style, { margin: '0', right: 'auto', bottom: 'auto', left: `${position.left}px`, top: `${position.top}px` });
    };
    const stop = () => {
        if (drag && header.hasPointerCapture(drag.id)) header.releasePointerCapture(drag.id);
        drag = null;
    };
    const fit = () => {
        if (!desktop()) {
            stop();
            position = null;
            for (const property of ['margin', 'right', 'bottom', 'left', 'top']) dialog.style.removeProperty(property);
        } else if (dialog.open && position) {
            place(position.left, position.top);
        }
    };
    const start = event => {
        if (!desktop() || event.button !== 0 || event.target.closest('button, a, input, select')) return;
        const bounds = dialog.getBoundingClientRect();
        drag = { id: event.pointerId, x: event.clientX - bounds.left, y: event.clientY - bounds.top };
        header.setPointerCapture(event.pointerId);
        event.preventDefault();
    };
    const move = event => {
        if (drag?.id === event.pointerId) place(event.clientX - drag.x, event.clientY - drag.y);
    };
    header.addEventListener('pointerdown', start);
    header.addEventListener('pointermove', move);
    header.addEventListener('pointerup', stop);
    header.addEventListener('pointercancel', stop);
    header.addEventListener('lostpointercapture', stop);
    dialog.addEventListener('close', stop);
    window.addEventListener('resize', fit);
    const observer = new ResizeObserver(fit);
    observer.observe(dialog);
    return () => {
        stop();
        observer.disconnect();
        window.removeEventListener('resize', fit);
        dialog.removeEventListener('close', stop);
        header.removeEventListener('pointerdown', start);
        header.removeEventListener('pointermove', move);
        header.removeEventListener('pointerup', stop);
        header.removeEventListener('pointercancel', stop);
        header.removeEventListener('lostpointercapture', stop);
    };
}
