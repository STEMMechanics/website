let refreshing = false;
async function refreshOnlineVisitors() {
    const widgets = [...document.querySelectorAll('[data-online-visitors]')];
    if (!widgets.length || document.hidden || refreshing) return;
    refreshing = true;
    try {
        const response = await fetch(widgets[0].dataset.url, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: AbortSignal.timeout(10000),
        });
        if (!response.ok) throw new Error('Could not refresh visitors');
        const { count } = await response.json();
        if (count !== null && (!Number.isInteger(count) || count < 0)) throw new Error('Invalid count');
        widgets.forEach(widget => {
            widget.querySelector('[data-online-count]').textContent = count === null ? 'Unavailable' : count.toLocaleString();
            widget.querySelector('[data-online-error]').hidden = true;
        });
    } catch {
        widgets.forEach(widget => { widget.querySelector('[data-online-error]').hidden = false; });
    } finally {
        refreshing = false;
    }
}
setInterval(refreshOnlineVisitors, 30000);
document.addEventListener('visibilitychange', refreshOnlineVisitors);
