window.SM = window.SM || {};
window.SM.workplanLayout = function () {
    let observer, media, frame;
    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(layout);
    };
    let root;
    const layout = () => {
        if (!root?.getBoundingClientRect().width) return;
        const sections = [...root.children];
        const gap = parseFloat(getComputedStyle(root).columnGap) || 0;
        const heights = sections.map(section => section.getBoundingClientRect().height);
        root.style.display = media.matches ? 'grid' : '';
        root.style.gridTemplateColumns = media.matches ? 'repeat(2, minmax(0, 1fr))' : '';
        root.style.gridAutoRows = media.matches ? '1px' : '';
        root.style.gridAutoFlow = media.matches ? 'row dense' : '';
        root.style.rowGap = media.matches ? '0px' : '';
        sections.forEach((section, index) => {
            // Keep upcoming work and follow-ups at the top; fill the shortest column below.
            section.style.marginBottom = media.matches ? '0px' : '';
            section.style.gridColumn = media.matches && index < 2 ? String(index + 1) : '';
            section.style.gridRowEnd = media.matches ? `span ${Math.ceil(heights[index] + gap)}` : '';
        });
    };
    return {
        init() {
            root = this.$el;
            media = window.matchMedia('(min-width: 48rem)');
            media.addEventListener('change', schedule);
            observer = new ResizeObserver(schedule);
            observer.observe(root);
            [...root.children].forEach(section => observer.observe(section));
            schedule();
        },
        destroy() {
            cancelAnimationFrame(frame);
            observer?.disconnect();
            media?.removeEventListener('change', schedule);
        },
    };
};
