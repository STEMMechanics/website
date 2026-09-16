let refreshing = false;
async function refreshOnlineVisitors() {
    const widgets = [...document.querySelectorAll('[data-online-visitors]')];
    if (!widgets.length || document.hidden || refreshing) return;
    const details = document.querySelector('[data-visitor-details]');
    const detailsError = document.querySelector('[data-visitor-details-error]');
    refreshing = true;
    try {
        const response = await fetch(details?.dataset.url || widgets[0].dataset.url, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: AbortSignal.timeout(10000),
        });
        if (!response.ok) throw new Error('Could not refresh visitors');
        const { count, html } = await response.json();
        if (count !== null && (!Number.isInteger(count) || count < 0)) throw new Error('Invalid count');
        if (details) {
            if (typeof html !== 'string') throw new Error('Invalid visitor details');
            details.innerHTML = html;
            detailsError.hidden = true;
        }
        widgets.forEach(widget => {
            widget.querySelector('[data-online-count]').textContent = count === null ? 'Unavailable' : count.toLocaleString();
            widget.querySelector('[data-online-error]').hidden = true;
        });
    } catch {
        if (detailsError) detailsError.hidden = false;
        widgets.forEach(widget => { widget.querySelector('[data-online-error]').hidden = false; });
    } finally {
        refreshing = false;
    }
}
setInterval(refreshOnlineVisitors, 30000);
document.addEventListener('visibilitychange', refreshOnlineVisitors);
