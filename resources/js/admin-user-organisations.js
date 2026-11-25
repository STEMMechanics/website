window.SM.whenAlpineReady(() => {
    Alpine.data('smCspAdminUserOrganisations', () => ({
        organisations: [],
        linkedIds: [],
        managerIds: [],
        primaryId: '',
        search: '',
        highlightedIndex: 0,
        ready: false,

        init() {
            try {
                this.organisations = JSON.parse(this.$el.dataset.organisationOptions || '[]')
                    .map(organisation => ({ ...organisation, id: String(organisation.id) }));
                this.linkedIds = JSON.parse(this.$el.dataset.initialLinked || '[]').map(String);
                this.managerIds = JSON.parse(this.$el.dataset.initialManagers || '[]').map(String)
                    .filter(id => this.linkedIds.includes(id));
                this.primaryId = String(this.$el.dataset.initialPrimary || '');
                if (this.primaryId && !this.linkedIds.includes(this.primaryId)) this.primaryId = '';
                this.ready = true;
            } catch {
                this.organisations = [];
                this.linkedIds = [];
                this.managerIds = [];
                this.primaryId = '';
                this.ready = false;
            }
        },

        get linkedOrganisations() {
            return this.linkedIds
                .map(id => this.organisations.find(organisation => organisation.id === id))
                .filter(Boolean);
        },

        get results() {
            const term = this.search.trim().toLowerCase();
            if (term.length < 2) return [];

            return this.organisations
                .filter(organisation => !this.linkedIds.includes(organisation.id))
                .filter(organisation => organisation.label.toLowerCase().includes(term))
                .slice(0, 25);
        },

        addOrganisation(organisation) {
            if (!organisation || this.linkedIds.includes(organisation.id)) return;

            this.linkedIds = [...this.linkedIds, organisation.id];
            if (!this.primaryId) this.primaryId = organisation.id;
            this.closeSearch();
        },

        removeOrganisation(id) {
            this.linkedIds = this.linkedIds.filter(linkedId => linkedId !== String(id));
            this.managerIds = this.managerIds.filter(managerId => managerId !== String(id));
            if (this.primaryId === String(id)) this.primaryId = this.linkedIds[0] || '';
        },

        move(step) {
            if (this.results.length === 0) return;
            const current = this.highlightedIndex < 0 ? 0 : this.highlightedIndex;
            this.highlightedIndex = (current + step + this.results.length) % this.results.length;
        },

        chooseHighlighted() {
            const organisation = this.results[this.highlightedIndex];
            if (organisation) this.addOrganisation(organisation);
        },

        closeSearch() {
            this.search = '';
            this.highlightedIndex = 0;
        },
    }));
});
