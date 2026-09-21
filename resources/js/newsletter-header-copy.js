document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-newsletter-refresh]');
    if (!button) return;
    const panel = button.closest('[data-newsletter-presentation]');
    const field = panel.querySelector(`[name="${button.dataset.newsletterRefresh}"]`);
    const key = { subject: 'subject', hero_header: 'header', hero_cta: 'cta' }[button.dataset.newsletterRefresh];
    const order = panel.querySelector('[name="content_order"]').value;
    const presets = JSON.parse(panel.dataset.headerCopyOptions)[order] || [];
    const values = [...new Set(presets.map(preset => preset[key]).filter(Boolean))];
    const alternatives = values.filter(value => value !== field.value);
    const pool = alternatives.length ? alternatives : values;
    if (!pool.length) return;
    field.value = pool[Math.floor(Math.random() * pool.length)];
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
});
