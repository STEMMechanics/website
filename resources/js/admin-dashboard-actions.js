const actionArticleClasses = 'group relative flex h-full min-w-0 flex-col rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-primary-color/40 hover:shadow-md';
const actionCardClasses = 'flex min-h-24 min-w-0 flex-1 cursor-pointer items-start gap-3 rounded-2xl p-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-color focus-visible:ring-offset-2';
const iconToneClasses = {
    sky: 'bg-sky-50 text-sky-700 group-hover:bg-sky-100',
    pink: 'bg-pink-50 text-pink-700 group-hover:bg-pink-100',
    violet: 'bg-violet-50 text-violet-700 group-hover:bg-violet-100',
    emerald: 'bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100',
    amber: 'bg-amber-50 text-amber-700 group-hover:bg-amber-100',
};

const renderActionCard = (action) => {
    if (!action || typeof action.title !== 'string' || typeof action.description !== 'string' || typeof action.url !== 'string' || typeof action.icon !== 'string') return null;
    const url = new URL(action.url, window.location.origin);
    if (url.origin !== window.location.origin) return null;

    const article = document.createElement('article');
    article.className = actionArticleClasses;
    article.dataset.actionCard = '';

    const link = document.createElement('a');
    link.href = url.href;
    link.className = actionCardClasses;

    const iconWrap = document.createElement('span');
    iconWrap.className = `flex size-10 shrink-0 items-center justify-center rounded-xl text-lg transition ${iconToneClasses[action.tone] || iconToneClasses.sky}`;
    iconWrap.setAttribute('aria-hidden', 'true');
    const icon = document.createElement('i');
    icon.className = action.icon;
    iconWrap.append(icon);

    const copy = document.createElement('span');
    copy.className = 'min-w-0';
    const title = document.createElement('span');
    title.className = `block text-base font-semibold leading-snug text-gray-900${action.title_no_wrap ? ' whitespace-nowrap' : ''}`;
    title.textContent = action.title;
    const details = action.attendance_details;
    if (details && typeof details.workshop === 'string' && typeof details.schedule === 'string' && typeof details.location === 'string') {
        const workshop = document.createElement('span');
        workshop.className = 'mt-1.5 block text-sm font-semibold leading-snug text-gray-900';
        workshop.textContent = details.workshop;
        const schedule = document.createElement('span');
        schedule.className = 'mt-1.5 block text-sm leading-snug text-gray-700';
        schedule.textContent = details.schedule;
        const location = document.createElement('span');
        location.className = 'mt-1 block text-sm leading-snug text-gray-700';
        location.textContent = details.location;
        copy.append(title, workshop, schedule, location);
    } else {
        const description = document.createElement('span');
        description.className = 'mt-1.5 block text-sm leading-snug text-gray-700';
        description.textContent = action.description;
        copy.append(title, description);
    }
    link.append(iconWrap, copy);
    article.append(link);

    if (typeof action.dismiss_key === 'string' && action.dismiss_key !== '') {
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-color';
        dismiss.dataset.dismissDashboardAction = '';
        dismiss.dataset.actionKey = action.dismiss_key;
        dismiss.setAttribute('aria-label', 'Hide this BAS action');
        const dismissIcon = document.createElement('i');
        dismissIcon.className = 'fa-solid fa-eye-slash';
        dismissIcon.setAttribute('aria-hidden', 'true');
        const dismissText = document.createElement('span');
        dismissText.textContent = 'Hide action';
        dismiss.append(dismissIcon, dismissText);
        const footer = document.createElement('div');
        footer.className = 'flex justify-end border-t border-gray-100 px-3 py-1.5';
        footer.append(dismiss);
        article.append(footer);
    }

    return article;
};

const updateActionColumns = (container, count) => {
    container.style.setProperty('--sm-dashboard-action-columns', String(Math.min(4, Math.max(2, Math.ceil(count / 2)))));
};

document.querySelectorAll('[data-dashboard-action-cards]').forEach((container) => {
    let lastUpdated = Date.now();
    let requestInFlight = false;
    const refresh = async () => {
        if (requestInFlight || document.hidden) return;
        requestInFlight = true;
        try {
            const response = await fetch(container.dataset.refreshUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (!Array.isArray(payload.actions)) return;
            const cards = payload.actions.map(renderActionCard).filter(Boolean);
            if (!cards.length) return;
            container.replaceChildren(...cards);
            updateActionColumns(container, cards.length);
            lastUpdated = Date.now();
        } catch {
            // Keep the server-rendered cards when a background refresh cannot complete.
        } finally {
            requestInFlight = false;
        }
    };

    container.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-dismiss-dashboard-action]') : null;
        if (!(button instanceof HTMLButtonElement)) return;
        event.preventDefault();
        event.stopPropagation();
        if (button.disabled) return;

        button.disabled = true;
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(container.dataset.dismissUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ action_key: button.dataset.actionKey }),
            });
            if (!response.ok) throw new Error('Could not hide action.');
            button.closest('[data-action-card]')?.remove();
            updateActionColumns(container, container.querySelectorAll('[data-action-card]').length);
            await refresh();
        } catch {
            button.disabled = false;
            button.title = 'Could not hide this action. Try again.';
        }
    });

    window.setInterval(refresh, 5 * 60 * 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - lastUpdated >= 5 * 60 * 1000) refresh();
    });
});
