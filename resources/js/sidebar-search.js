// Filter the existing menu without changing its links or the site's search form.
document.querySelectorAll('[data-sidebar-search]').forEach((form) => {
    const input = form.querySelector('input[name="q"]');
    const menu = form.parentElement;
    const links = [...menu.querySelectorAll('a[role="menuitem"]')];
    const headings = [...menu.querySelectorAll('[data-sidebar-heading]')];
    const sections = new Map();
    let heading = null;
    for (const child of menu.children) {
        if (child.matches('[data-sidebar-heading]')) heading = child;
        if (links.includes(child)) sections.set(child, heading);
    }
    input.addEventListener('input', () => {
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
});
