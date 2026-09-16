window.SM = window.SM || {};
window.SM.productPostagePreview = (url, csrf) => ({
    options: [], busy: false, message: '', timer: null, controller: null, version: 0, lastInput: '', disposed: false,
    schedule(measurements) {
        // Keep request state outside Alpine's measurement-tracking effect.
        queueMicrotask(() => this.queue(measurements));
    },
    queue(measurements) {
        if (this.disposed) return;
        const key = JSON.stringify(measurements);
        if (key === this.lastInput) return;
        this.lastInput = key;
        clearTimeout(this.timer);
        this.controller?.abort();
        const version = ++this.version;
        this.options = [];
        this.busy = false;
        if (measurements.product_type !== 'physical') { this.message = ''; return; }
        const fields = ['length_mm', 'width_mm', 'height_mm', 'weight_grams'];
        const valid = fields.every(field => {
            const value = measurements[field];
            const number = Number(value);
            return value !== null && value !== undefined && String(value).trim() !== ''
                && Number.isInteger(number) && number >= 1
                && number <= (field === 'weight_grams' ? 100000000 : 10000);
        });
        if (!valid) { this.message = 'Enter valid packed dimensions (mm) and weight (g) to check postage.'; return; }
        this.message = '';
        this.busy = true;
        this.timer = setTimeout(() => this.load(measurements, version), 350);
    },
    async load(measurements, version) {
        const controller = new AbortController();
        this.controller = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(url, {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(measurements), signal: controller.signal,
            });
            if (!response.ok) throw new Error('Preview unavailable');
            const payload = await response.json();
            if (version !== this.version || this.disposed) return;
            this.options = payload.options;
            this.message = this.options.length ? '' : 'No postage channels are available.';
        } catch {
            if (version === this.version && !this.disposed) this.message = 'Could not check postage. Edit a measurement to try again.';
        } finally {
            clearTimeout(timeout);
            if (version === this.version && !this.disposed) this.busy = false;
        }
    },
    money(value) { return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value); },
    destroy() { this.disposed = true; clearTimeout(this.timer); this.controller?.abort(); },
});
