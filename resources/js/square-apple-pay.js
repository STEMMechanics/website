// Keep Square SDK objects outside Alpine's reactive proxies.
window.SM = window.SM || {};
window.SM.squareApplePay = function (config) {
    let wallet = null;
    let request = null;
    let disposed = false;
    const evaluate = (expression) => {
        try {
            return window.Alpine.evaluate(config.element, expression);
        } catch (_) {
            return undefined;
        }
    };
    const setParentValue = (key, value) => {
        const parent = config.element?.parentElement;
        const data = parent ? window.Alpine.$data(parent) : null;
        if (data && key in data) data[key] = value;
    };
    const track = (stage) => config.element?.dispatchEvent(new CustomEvent('checkout-payment-event', {
        bubbles: true,
        detail: { stage },
    }));
    return {
        walletReady: false,
        walletBusy: false,
        walletError: '',
        walletAmount() { return Number(evaluate(config.amountExpression)); },
        walletEligible() { return Boolean(evaluate(config.eligibleExpression)) && this.walletAmount() > 0; },
        async init() {
            if (!config.enabled || !config.applicationId || !config.locationId) return;
            try {
                for (let attempt = 0; !window.Square?.payments && attempt < 50 && !disposed; attempt++) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
                if (disposed || !window.Square?.payments) return;
                const payments = window.Square.payments(config.applicationId, config.locationId);
                request = payments.paymentRequest({
                    countryCode: 'AU', currencyCode: 'AUD',
                    total: { label: 'STEMMechanics', amount: Math.max(0.01, this.walletAmount() || 0).toFixed(2) },
                });
                wallet = await payments.applePay(request);
                if (disposed) { await wallet.destroy(); return; }
                this.walletReady = true;
            } catch (_) {
                // Unsupported browsers/devices keep the existing card form.
                this.walletReady = false;
            }
        },
        async payWithApple(event, submitting) {
            if (!this.walletReady || this.walletBusy || submitting || !this.walletEligible()) return;
            const form = event.target.closest('form');
            if (!form || !form.reportValidity()) return;
            this.walletError = '';
            const amount = this.walletAmount().toFixed(2);
            const source = form.querySelector('input[name="source_id"]');
            if (!source) { this.walletError = 'Please refresh the page before paying.'; return; }
            source.value = '';
            setParentValue('sourceId', '');
            this.walletBusy = true;
            setParentValue('isSubmitting', true);
            let submitted = false;
            try {
                if (!request.update({ total: { label: 'STEMMechanics', amount } })) {
                    throw new Error('Unable to update the payment total. Please try again.');
                }
                // Must run in the click gesture, before any await or network request.
                const result = await wallet.tokenize();
                if (result.status === 'Cancel') {
                    track('payment_cancelled');
                    return;
                }
                if (result.status !== 'OK' || !result.token) {
                    throw new Error(result.errors?.[0]?.message || 'Unable to complete Apple Pay. Please try again or pay by card.');
                }
                if (!this.walletEligible() || amount !== this.walletAmount().toFixed(2)) {
                    throw new Error('Your order changed. Review the total and try again.');
                }
                setParentValue('sourceId', result.token);
                source.value = result.token;
                // Use the same server-side validation, totals and payment processing as cards.
                form.submit();
                submitted = true;
            } catch (error) {
                track('payment_failed');
                this.walletError = error?.message || 'Unable to complete Apple Pay. Please try again or pay by card.';
            } finally {
                if (!submitted) {
                    source.value = '';
                    setParentValue('sourceId', '');
                    this.walletBusy = false;
                    setParentValue('isSubmitting', false);
                }
            }
        },
        destroy() {
            disposed = true;
            wallet?.destroy().catch(() => {});
        },
    };
};
