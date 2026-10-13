import { openListDialog } from './list-controls';

async function readJson(response) {
    if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Your session may have expired. Refresh the page and try again.');
    }
    const data = await response.json();
    if (!response.ok) {
        throw new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Please try again.');
    }
    return data;
}

export function storeOrderInvoiceAttachment(config) {
    return {
        invoices: [],
        searchOpen: false,
        search: '',
        suggestions: [],
        highlighted: -1,
        recentLoading: false,
        searchLoading: false,
        reviewLoading: false,
        saving: false,
        error: '',
        reviewHtml: '',
        recentController: null,
        searchController: null,
        reviewController: null,
        searchTimer: null,

        init() {
            if (config.autoOpen) this.$nextTick(() => this.open(this.$refs.trigger, config.initialInvoiceId));
        },

        dialog() {
            return this.$refs.popup.querySelector('dialog');
        },

        async open(trigger, invoiceId = 0) {
            if (this.saving) return;
            this.dismiss();
            this.invoices = [];
            this.searchOpen = false;
            this.search = '';
            this.suggestions = [];
            this.highlighted = -1;
            this.reviewHtml = '';
            this.error = '';
            openListDialog(this.dialog(), trigger);
            this.recentLoading = true;
            const controller = new AbortController();
            this.recentController = controller;
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(config.optionsUrl, {
                    signal: controller.signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                });
                const data = await readJson(response);
                if (this.recentController !== controller || !this.dialog().open) return;
                this.invoices = data.invoices;
                if (invoiceId) await this.review(invoiceId);
            } catch (error) {
                if (this.recentController === controller && this.dialog().open) {
                    this.error = error.name === 'AbortError' ? 'Loading took too long. Close this popup and try again.' : error.message;
                }
            } finally {
                clearTimeout(timeout);
                if (this.recentController === controller) {
                    this.recentController = null;
                    this.recentLoading = false;
                }
            }
        },

        invoiceDetails(invoice) {
            return [invoice.customer, invoice.company, invoice.date, invoice.payment].filter(Boolean).join(' · ');
        },

        toggleSearch() {
            this.searchOpen = !this.searchOpen;
            this.error = '';
            if (this.searchOpen) {
                this.$nextTick(() => this.$refs.searchInput.focus());
                if (this.search.trim()) this.scheduleSearch();
            } else {
                this.cancelSearch();
                this.$refs.searchToggle.focus();
            }
        },

        cancelSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = null;
            this.searchController?.abort();
            this.searchController = null;
            this.searchLoading = false;
            this.suggestions = [];
            this.highlighted = -1;
        },

        scheduleSearch() {
            this.cancelSearch();
            this.error = '';
            const query = this.search.trim();
            if (!query) return;
            this.searchLoading = true;
            this.searchTimer = setTimeout(() => {
                this.searchTimer = null;
                this.searchInvoices(query);
            }, 250);
        },

        async searchInvoices(query) {
            const controller = new AbortController();
            this.searchController = controller;
            const url = new URL(config.optionsUrl, window.location.href);
            url.searchParams.set('q', query);
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(url, {
                    signal: controller.signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                });
                const data = await readJson(response);
                if (this.searchController !== controller || this.search.trim() !== query || !this.dialog().open) return;
                this.suggestions = data.invoices;
                this.highlighted = this.suggestions.length ? 0 : -1;
            } catch (error) {
                if (this.searchController === controller && this.dialog().open) {
                    this.error = error.name === 'AbortError' ? 'Search took too long. Please try again.' : error.message;
                }
            } finally {
                clearTimeout(timeout);
                if (this.searchController === controller) {
                    this.searchController = null;
                    this.searchLoading = false;
                }
            }
        },

        moveSuggestion(step) {
            if (!this.suggestions.length) return;
            this.highlighted = (this.highlighted + step + this.suggestions.length) % this.suggestions.length;
        },

        chooseSuggestion() {
            const invoice = this.suggestions[this.highlighted];
            if (invoice) this.review(invoice.id);
        },

        async review(invoiceId) {
            if (this.saving) return;
            this.cancelSearch();
            this.reviewController?.abort();
            const controller = new AbortController();
            this.reviewController = controller;
            this.reviewLoading = true;
            this.error = '';
            const url = new URL(config.reviewUrl, window.location.href);
            url.searchParams.set('invoice_id', invoiceId);
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(url, {
                    signal: controller.signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                });
                const data = await readJson(response);
                if (this.reviewController !== controller || !this.dialog().open) return;
                this.reviewHtml = data.html;
                this.dialog().scrollTop = 0;
                this.$nextTick(() => this.dialog().querySelector('h3')?.focus());
            } catch (error) {
                if (this.reviewController === controller && this.dialog().open) {
                    this.error = error.name === 'AbortError' ? 'Loading took too long. Please try again.' : error.message;
                }
            } finally {
                clearTimeout(timeout);
                if (this.reviewController === controller) {
                    this.reviewController = null;
                    this.reviewLoading = false;
                }
            }
        },

        back() {
            if (this.saving) return;
            this.reviewHtml = '';
            this.error = '';
            if (this.searchOpen && this.search.trim()) this.scheduleSearch();
        },

        async save(form) {
            if (this.saving) return;
            this.saving = true;
            this.error = '';
            const body = new FormData(form);
            window.SM.setFormProcessing(form, true, { submitLabel: 'Attaching invoice…' });
            try {
                const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = await readJson(response);
                window.location.assign(data.redirect_url);
            } catch (error) {
                this.error = error.message;
                this.dialog().scrollTop = 0;
            } finally {
                window.SM.setFormProcessing(form, false);
                this.saving = false;
            }
        },

        dismiss() {
            this.recentController?.abort();
            this.recentController = null;
            this.reviewController?.abort();
            this.reviewController = null;
            this.recentLoading = false;
            this.reviewLoading = false;
            this.cancelSearch();
        },
    };
}

window.SM = window.SM || {};
window.SM.storeOrderInvoiceAttachment = storeOrderInvoiceAttachment;
