<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
function sponsorshipSquareCheckout(config) {
        return {
            squareEnabled: Boolean(config.squareEnabled), invoiceAvailable: Boolean(config.invoiceAvailable), applicationId: config.applicationId || '', locationId: config.locationId || '', environment: config.environment, billingContactDefaults: config.billingContact || {},
            frequency: config.frequency || 'one_time', paymentMethod: config.invoiceAvailable && (config.paymentMethod === 'invoice' || !config.squareEnabled) ? 'invoice' : 'square',
            card: null, sourceId: '', errorMessage: '', isSubmitting: false,
            async init() { if (this.paymentMethod === 'square') await this.initSquare(); },
            async paymentMethodChanged() {
                if (this.paymentMethod === 'square' && !this.card) await this.initSquare();
            },
            billingContact() {
                const value = (name) => {
                    const field = this.$el.elements.namedItem(name);
                    const entered = field && typeof field.value === 'string' ? field.value.trim() : '';
                    const fallback = this.billingContactDefaults?.[name];
                    return entered || (typeof fallback === 'string' ? fallback.trim() : '');
                };
                const fullName = value('contact_name') || value('name');
                const nameParts = fullName.split(/\s+/).filter(Boolean);
                const contact = {};

                if (nameParts.length > 0) contact.givenName = nameParts.shift();
                if (nameParts.length > 0) contact.familyName = nameParts.join(' ');

                const email = value('email');
                if (email) contact.email = email;

                const country = value('country');
                const countryCode = this.countryCode(country);
                if (countryCode) contact.countryCode = countryCode;

                const addressLines = [value('billing_address'), value('billing_address2')].filter(Boolean);
                if (addressLines.length > 0) contact.addressLines = addressLines;

                const city = value('billing_city');
                const state = value('billing_state');
                const postalCode = value('billing_postcode');
                if (city) contact.city = city;
                if (state) contact.state = state;
                if (postalCode) contact.postalCode = postalCode;

                return contact;
            },
            countryCode(value) {
                const country = String(value || '').trim();
                if (/^[A-Za-z]{2}$/.test(country)) return country.toUpperCase();

                const aliases = {
                    australia: 'AU',
                    aus: 'AU',
                    'united states of america': 'US',
                    usa: 'US',
                    'united kingdom': 'GB',
                    uk: 'GB',
                    'great britain': 'GB',
                };
                const alias = aliases[country.toLowerCase()];
                if (alias) return alias;
                if (typeof Intl === 'undefined' || typeof Intl.DisplayNames !== 'function') return '';

                try {
                    const names = new Intl.DisplayNames(['en'], { type: 'region' });
                    const normalized = country.toLocaleLowerCase('en');
                    for (const first of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
                        for (const second of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
                            const code = first + second;
                            const name = names.of(code);
                            if (name && name.toLocaleLowerCase('en') === normalized) return code;
                        }
                    }
                } catch (error) {
                    return '';
                }

                return '';
            },
            async initSquare() {
                if (!this.squareEnabled || !this.applicationId || !this.locationId) return false;
                const ready = await this.waitForSdk();
                if (!ready) { this.errorMessage = 'Square payment form could not be loaded.'; return false; }
                try {
                    const payments = window.Square.payments(this.applicationId, this.locationId);
                    const cardOptions = this.environment === 'sandbox' && this.frequency === 'monthly'
                        ? { postalCode: '94103' }
                        : undefined;
                    this.card = await payments.card(cardOptions);
                    await this.card.attach(this.$refs.squareCardContainer);
                    return true;
                } catch (error) {
                    this.errorMessage = error?.message || 'Unable to load card payment form.';
                    return false;
                }
            },
            async submitForm(event) {
                if (this.isSubmitting) return;
                this.errorMessage = '';
                this.isSubmitting = true;
                if (this.paymentMethod === 'invoice') {
                    event.target.submit();
                    return;
                }
                if (!this.card && !(await this.initSquare())) { this.isSubmitting = false; return; }
                try {
                    const result = this.frequency === 'monthly'
                        ? await this.card.tokenize({ intent: 'STORE', billingContact: this.billingContact(), customerInitiated: true, sellerKeyedIn: false })
                        : await this.card.tokenize();
                    if (result.status !== 'OK') {
                        this.errorMessage = (result.errors || []).map(error => error.message).filter(Boolean).join(' | ') || 'Card validation failed.';
                        this.isSubmitting = false;
                        return;
                    }
                    this.sourceId = result.token;
                    const input = this.$el.querySelector('input[name="source_id"]');
                    if (input) input.value = this.sourceId;
                    event.target.submit();
                } catch (error) {
                    this.errorMessage = error?.message || 'Unable to tokenize your card.';
                    this.isSubmitting = false;
                }
            },
            async waitForSdk(maxWaitMs = 8000) {
                const start = Date.now();
                while (Date.now() - start < maxWaitMs) {
                    if (window.Square) return true;
                    await new Promise(resolve => setTimeout(resolve, 150));
                }
                return false;
            }
        };
    }
</script>
