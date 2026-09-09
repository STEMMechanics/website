window.SM = window.SM || {};
window.SM.workshopDelivery = config => ({
    quote: config.summary,
    ticketAmount: config.ticketAmount,
    method: config.summary.shipping_method_code || '',
    loading: false,
    quoteError: '',
    timer: null,
    controller: null,
    revision: 0,
    fingerprint: null,
    get isPickup() {
        const selected = (this.quote.shipping_methods || []).find(option => option.code === this.method);
        return selected ? Boolean(selected.is_pickup) : this.method === 'pickup';
    },
    money(amount) { return Number(amount).toLocaleString('en-AU', {style:'currency',currency:'AUD'}); },
    scheduleQuote() {
        const fingerprint = JSON.stringify(Array.from(new FormData(this.$el)).filter(([key]) => !['_token', 'action', 'confirmed_total'].includes(key)));
        if (fingerprint === this.fingerprint) return;
        this.fingerprint = fingerprint;
        clearTimeout(this.timer);
        this.controller?.abort();
        const revision = ++this.revision;
        this.loading = true;
        this.timer = setTimeout(() => this.refreshQuote(revision), 300);
    },
    async refreshQuote(revision) {
        this.controller = new AbortController();
        const data = new FormData(this.$el);
        data.set('action', 'quote');
        try {
            const response = await fetch(this.$el.getAttribute('action'), {method:'POST',body:data,credentials:'same-origin',signal:this.controller.signal,headers:{Accept:'application/json'}});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to update delivery. Please check the address.');
            if (revision !== this.revision) return;
            this.quote = result.summary;
            this.method = result.summary.shipping_method_code || '';
            this.quoteError = '';
        } catch(error) {
            if (revision === this.revision && error.name !== 'AbortError') this.quoteError = error.message;
        } finally {
            if (revision === this.revision) this.loading = false;
        }
    },
    destroy() { clearTimeout(this.timer); this.controller?.abort(); },
});
