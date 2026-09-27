document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;

    const link = event.target.closest('[data-sidebar-navigation] a[role="menuitem"][href]');
    if (!(link instanceof HTMLAnchorElement)
        || event.defaultPrevented
        || event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
        || (link.target !== '' && link.target !== '_self')
        || link.hasAttribute('download')) {
        return;
    }

    link.closest('[data-sidebar-navigation]')
        ?.querySelectorAll('[data-sidebar-navigation-pending="true"]')
        .forEach((pendingLink) => pendingLink.removeAttribute('data-sidebar-navigation-pending'));

    link.setAttribute('data-sidebar-navigation-pending', 'true');
});
