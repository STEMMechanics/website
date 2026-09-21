// Filter the existing menu without changing its links or the site's search form.
document.querySelectorAll('[data-sidebar-search]').forEach((form) => {
    const input = form.querySelector('input[name="q"]');
    const menu = form.parentElement;
    const links = [...menu.querySelectorAll('a[role="menuitem"]')];
    const headings = [...menu.querySelectorAll('[data-sidebar-heading]')];
    const status = form.querySelector('[data-sidebar-selection-status]');
    let activeLink = null;
    const select = (link) => {
        activeLink?.removeAttribute('data-sidebar-keyboard-selected');
        activeLink = link;
        activeLink?.setAttribute('data-sidebar-keyboard-selected', 'true');
        if (status) status.textContent = link ? `${link.textContent.trim()} selected. Press Enter to open.` : '';
        link?.scrollIntoView({ block: 'nearest' });
    };
    const sections = new Map();
    let heading = null;
    for (const child of menu.children) {
        if (child.matches('[data-sidebar-heading]')) heading = child;
        if (links.includes(child)) sections.set(child, heading);
    }
    input.addEventListener('input', () => {
        select(null);
        const words = input.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        for (const link of links) {
            const text = `${sections.get(link)?.dataset.sidebarHeading || ''} ${link.textContent}`.toLocaleLowerCase();
            const visible = words.every((word) => text.includes(word));
            // Inline display also overrides utility classes such as flex/block.
            link.style.display = visible ? '' : 'none';
        }
        for (const section of headings) {
            section.style.display = links.some((link) => sections.get(link) === section && link.style.display !== 'none') ? '' : 'none';
        }
        menu.querySelector('[data-sidebar-empty]').hidden = links.some((link) => link.style.display !== 'none');
    });
    input.addEventListener('keydown', (event) => {
        if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const visible = links.filter((link) => link.style.display !== 'none');
            if (!visible.length) return;
            const index = visible.indexOf(activeLink);
            const next = index < 0
                ? (event.key === 'ArrowDown' ? 0 : visible.length - 1)
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
            select(visible[next]);
        } else if (event.key === 'Escape' && activeLink) {
            event.preventDefault();
            event.stopPropagation();
            select(null);
        } else if (event.key === 'Enter' && activeLink) {
            event.preventDefault();
            activeLink.click();
        }
    });
    input.addEventListener('blur', () => select(null));
});
