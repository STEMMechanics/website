window.SM.whenAlpineReady(() => {
    Alpine.data('smCspAdminViewAs', () => ({
        email: '',
        suggestions: [],
        highlightedIndex: -1,
        requestId: 0,

        init() {
            this.email = this.$el.dataset.initialEmail || '';
        },

        async searchUsers() {
            const query = this.email.trim();
            const requestId = ++this.requestId;
            this.suggestions = [];
            this.highlightedIndex = -1;
            if (query.length < 2) {
                return;
            }

            try {
                const endpoint = this.$refs.email.dataset.suggestionsUrl;
                if (!endpoint) {
                    this.suggestions = [];
                    this.highlightedIndex = -1;
                    return;
                }

                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (requestId !== this.requestId) return;
                if (!response.ok) {
                    this.suggestions = [];
                    this.highlightedIndex = -1;
                    return;
                }

                const payload = await response.json();
                this.suggestions = Array.isArray(payload.users) ? payload.users : [];
                this.highlightedIndex = this.suggestions.length > 0 ? 0 : -1;
            } catch {
                if (requestId === this.requestId) {
                    this.suggestions = [];
                    this.highlightedIndex = -1;
                }
            }
        },

        chooseUser(email) {
            this.requestId += 1;
            this.email = email;
            this.suggestions = [];
            this.highlightedIndex = -1;
        },

        moveSuggestion(step) {
            if (this.suggestions.length === 0) return;

            const current = this.highlightedIndex < 0 ? 0 : this.highlightedIndex;
            this.highlightedIndex = (current + step + this.suggestions.length) % this.suggestions.length;
        },

        chooseHighlighted(event) {
            const user = this.suggestions[this.highlightedIndex];
            if (!user) return;

            event.preventDefault();
            this.chooseUser(user.email);
        },

        clearSuggestions() {
            this.requestId += 1;
            this.suggestions = [];
            this.highlightedIndex = -1;
        },
    }));
});
