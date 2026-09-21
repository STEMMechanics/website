window.SM = window.SM || {};
window.SM.workshopSuggestions = config => ({
    selected: config.selected || [],
    busy: false,
    error: '',
    async change(id, allowPartial = false) {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        try {
            const response = await fetch(config.url, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf},
                body: JSON.stringify({action: this.selected.includes(id) ? 'remove' : 'add', workshop_id: id, allow_partial: allowPartial}),
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
    submitting: false,
    saveError: '',
    saveTimer: null,
    saving: null,
    savedSnapshot: null,
    navigationHandler: null,
    unloadHandler: null,
    init() {
        this.savedSnapshot = JSON.stringify(this.participants);
        if (config.draftUrl) {
            this.navigationHandler = async event => {
                const link = event.target.closest('a[href]');
                if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('href').startsWith('#')) return;
                if (JSON.stringify(this.participants) === this.savedSnapshot && !this.saving) return;
                event.preventDefault();
                if (await this.saveDraft()) window.location.assign(link.href);
            };
            this.unloadHandler = () => { this.saveDraft(); };
            document.addEventListener('click', this.navigationHandler, true);
            window.addEventListener('pagehide', this.unloadHandler);
        }
        this.$watch('participants', () => {
            this.syncCartSelection();
            if (config.draftUrl) {
                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => this.saveDraft(), 300);
            }
        });
        this.$nextTick(() => this.syncCartSelection());
    },
    async saveDraft() {
        if (this.submitting) return true;
        clearTimeout(this.saveTimer);
        if (this.saving) {
            if (!await this.saving) return false;
            return this.saveDraft();
        }
        const snapshot = JSON.stringify(this.participants);
        if (snapshot === this.savedSnapshot) return true;
        this.saving = (async () => {
            try {
                const response = await fetch(config.draftUrl, {
                    method:'POST', credentials:'same-origin', keepalive:true,
                    headers:{'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':config.csrf},
                    body:JSON.stringify({participants:JSON.parse(snapshot)}),
                });
                if (!response.ok) {
                    const result = await response.json().catch(() => ({}));
                    throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save your participant details. Please try again before leaving this page.');
                }
                this.savedSnapshot = snapshot;
                this.saveError = '';
                return true;
            } catch (error) {
                this.saveError = error.message || 'Could not save your participant details. Please try again before leaving this page.';
                return false;
            }
        })();
        const saved = await this.saving;
        this.saving = null;
        if (saved && snapshot !== JSON.stringify(this.participants)) return this.saveDraft();
        return saved;
    },
    async submitReview(event) {
        clearTimeout(this.saveTimer);
        if (this.submitting) return;
        this.submitting = true;
        if (this.saving) {
            event.preventDefault();
            await this.saving;
            event.target.requestSubmit();
        }
    },
    destroy() {
        clearTimeout(this.saveTimer);
        if (this.navigationHandler) document.removeEventListener('click', this.navigationHandler, true);
        if (this.unloadHandler) window.removeEventListener('pagehide', this.unloadHandler);
    },
    syncCartSelection() {
        window.dispatchEvent(new CustomEvent('workshop-selection-updated', {detail: {
            bookingId: config.bookingId,
            count: Object.keys(this.prices).reduce((sum, id) => sum + this.quantity(id), 0),
        }}));
    },
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
