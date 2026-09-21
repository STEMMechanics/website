window.SM = window.SM || {};
window.SM.workshopSuggestions = config => ({
    selected: config.selected || [],
    busy: false,
    error: '',
    async change(id) {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        try {
            const response = await fetch(config.url, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf},
                body: JSON.stringify({action: this.selected.includes(id) ? 'remove' : 'add', workshop_id: id}),
            });
            if (response.status === 419) throw new Error('Your session expired. Reload the page and try again.');
            const result = await response.json().catch(() => ({message: 'Unable to update your booking. Please try again.'}));
            if (result.redirect) { window.location.assign(result.redirect); return; }
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Unable to update your booking. Please try again.');
            this.selected = result.selected;
            window.dispatchEvent(new CustomEvent('workshop-hold-updated', {detail: {expires_at: result.expires_at, booking_id: config.bookingId}}));
            window.dispatchEvent(new CustomEvent('workshop-cart-updated', {detail: result.bookings}));
        } catch (error) {
            this.error = error.message || 'Unable to update your booking. Please try again.';
        } finally {
            this.busy = false;
        }
    },
});
window.SM.workshopBookingReview = config => ({
    participants: config.participants.map(person => ({...person, workshops: person.workshops || []})),
    prices: config.prices,
    money(amount) { return new Intl.NumberFormat('en-AU', {style:'currency', currency:'AUD'}).format(amount); },
    addParticipant() {
        if (this.participants.length < 10) this.participants.push({firstname:'', surname:config.surname, workshops:[]});
    },
    quantity(id) { return this.participants.filter(person => person.workshops.includes(id)).length; },
    amount(id) {
        const count = this.quantity(id), price = this.prices[id];
        const early = price.early_price === null ? 0 : Math.min(count, price.early_places);
        return (count - early) * price.price + early * (price.early_price || 0);
    },
    get total() { return Object.keys(this.prices).reduce((sum, id) => sum + this.amount(id), 0); },
});
