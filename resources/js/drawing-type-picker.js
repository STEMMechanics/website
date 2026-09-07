window.SM = window.SM || {};
window.SM.drawingTypePicker = (initial, totals) => {
    let controller;
    let displayed = initial;
    return {
        purpose: initial, totals, loading: false,
        money(cents) { return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(cents / 100); },
        destroy() { controller?.abort(); },
        async changeType() {
            controller?.abort();
            controller = new AbortController();
            const { signal } = controller;
            const selected = this.purpose;
            const url = new URL(window.location.href);
            url.searchParams.set('purpose', selected);
            url.searchParams.set('tab', 'drawings');
            url.searchParams.delete('page');
            this.loading = true;
            try {
                const response = await fetch(url, { signal, credentials: 'same-origin' });
                if (!response.ok || response.redirected) throw new Error('Could not load drawing history. Please try again.');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const history = page.querySelector('[data-drawing-history]');
                if (!history) throw new Error('Could not load drawing history. Please try again.');
                if (signal.aborted) return;
                this.$refs.history.replaceChildren(...history.childNodes);
                displayed = selected;
                window.history.pushState({ ...window.history.state, dynamicList: 'my-timesheet' }, '', url.href);
            } catch (error) {
                if (signal.aborted) return;
                this.purpose = displayed;
                window.SM.banner('Could not load history', error.message, 'danger');
            } finally {
                if (!signal.aborted) this.loading = false;
            }
        },
    };
};
