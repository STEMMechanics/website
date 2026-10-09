window.SM = window.SM || {};

window.SM.stockItemLinkEditor = (model, catalog = [], itemNameSuggestions = []) => ({
    model,
    catalog: Array.isArray(catalog) ? catalog : [],
    itemNameSuggestions: Array.isArray(itemNameSuggestions) ? itemNameSuggestions : [],
    query: '',
    open: false,
    selected: 0,
    menuTop: 0,
    menuLeft: 0,
    menuWidth: 320,
    get linkedStockItem() {
        const stockItemId = String(this.model?.stock_item_id ?? '');

        return this.catalog.find((option) => String(option.id) === stockItemId) || null;
    },
    get matches() {
        const query = this.query.trim().toLowerCase();
        const currentStockItemId = String(this.model?.stock_item_id ?? '');
        const stockItemNames = new Set(this.catalog.map((option) => String(option.name || '').trim().toLowerCase()));
        const uniqueBlueprintNames = [...new Map(this.itemNameSuggestions
            .map((name) => String(name || '').trim())
            .filter(Boolean)
            .map((name) => [name.toLowerCase(), name])).values()];
        const options = [
            ...this.catalog
                .filter((option) => option.status !== 'archived' || String(option.id) === currentStockItemId)
                .map((option) => ({ ...option, suggestionType: 'stock' })),
            ...uniqueBlueprintNames
                .filter((name) => !stockItemNames.has(name.toLowerCase()))
                .map((name, index) => ({
                    id: `blueprint-text-${index}`,
                    name,
                    status: 'active',
                    is_kit: false,
                    suggestionType: 'blueprint-text',
                })),
        ].filter((option) => {
            if (!query) return true;

            return `${option.name || ''} ${option.group_name || ''} ${option.variant_name || ''} ${option.sku || ''}`.toLowerCase().includes(query);
        });
        const matchRank = (option) => {
            const name = String(option.name || '').toLowerCase();
            if (name === query) return 0;
            if (name.startsWith(query)) return 1;
            return 2;
        };

        return options
            .sort((a, b) => matchRank(a) - matchRank(b) || String(a.name).localeCompare(String(b.name)))
            .slice(0, 12);
    },
    position(element, width = 320) {
        const rect = element.getBoundingClientRect();
        this.menuWidth = Math.min(width, window.innerWidth - 16);
        this.menuTop = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - 320));
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - this.menuWidth - 8));
    },
    editDescription(element) {
        if (this.model?.stock_item_id && element.value !== this.linkedStockItem?.name) {
            this.model.stock_item_id = null;
            this.model.stock_quantity = null;
            this.$dispatch('stock-item-link-changed');
        }

        this.query = element.value || '';
        this.selected = 0;
        this.position(element, Math.max(320, element.offsetWidth));
        this.open = this.matches.length > 0 || !!this.query.trim();
    },
    browse(element) {
        this.position(element);
        this.query = '';
        this.selected = 0;
        this.open = !this.open;
    },
    move(step) {
        if (!this.matches.length) return;

        this.selected = Math.max(0, Math.min(this.matches.length - 1, this.selected + step));
    },
    handleEnter(event) {
        if (!this.open) return;

        event.preventDefault();
        event.stopPropagation();

        if (event.metaKey || event.ctrlKey) {
            if (this.matches[this.selected]) this.choose(this.matches[this.selected]);
            return;
        }

        this.open = false;
    },
    choose(option) {
        const isBlueprintTextSuggestion = option?.suggestionType === 'blueprint-text';
        this.model.stock_item_id = isBlueprintTextSuggestion ? null : (option?.id || null);
        this.model.stock_quantity = null;
        this.model.item_name = option?.name || '';
        this.query = '';
        this.selected = 0;
        this.open = false;
        this.$dispatch('stock-item-link-changed');
    },
});
