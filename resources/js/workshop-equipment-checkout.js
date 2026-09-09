window.SM = window.SM || {};
window.SM.workshopEquipmentCheckout = (config) => ({
    selected: config.selected || {},
    variants: config.variants || {},
    quantity: config.quantity ?? 1,
    option(id) { return config.options[id]?.[this.variants[id] || 'base'] || { price: 0, available: false }; },
    money(amount) { return Number(amount).toLocaleString('en-AU', { style: 'currency', currency: 'AUD' }); },
    get total() {
        const quantity = Math.max(0, Number(this.quantity) || 0);
        const discounted = config.earlyBirdRemaining === null ? quantity : Math.min(quantity, Math.max(0, config.earlyBirdRemaining));
        let cents = discounted * Math.round(config.ticketPrice * 100) + (quantity - discounted) * Math.round(config.regularPrice * 100);
        for (const id of Object.keys(config.options)) {
            if (Number(this.selected[id]) > 0 && this.option(id).available) cents += Math.round(this.option(id).price * 100) * Math.max(0, Number(this.selected[id]) || 0);
        }
        return cents / 100;
    },
});
