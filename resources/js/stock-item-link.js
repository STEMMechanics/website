window.SM = window.SM || {};

window.SM.stockItemLinkEditor = (model, catalog = [], allowLinkedItemTextEdit = false) => ({
    model,
    catalog: Array.isArray(catalog) ? catalog : [],
    allowLinkedItemTextEdit: Boolean(allowLinkedItemTextEdit),
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

        return this.catalog
            .filter((option) => {
                if (option.status === 'archived' && String(option.id) !== currentStockItemId) return false;
                if (!query) return true;

                return `${option.name || ''} ${option.group_name || ''} ${option.variant_name || ''} ${option.sku || ''}`.toLowerCase().includes(query);
            })
            .slice(0, 12);
    },
    position(element, width = 320) {
        const rect = element.getBoundingClientRect();
        this.menuWidth = Math.min(width, window.innerWidth - 16);
        this.menuTop = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - 320));
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - this.menuWidth - 8));
    },
    editDescription(element) {
        if (this.model?.stock_item_id) {
            if (!this.allowLinkedItemTextEdit || element.value === this.linkedStockItem?.name) return;

            this.model.stock_item_id = null;
            this.model.stock_quantity = null;
            this.$dispatch('stock-item-link-changed');
        }

        this.query = element.value || '';
        this.selected = 0;
        this.position(element, Math.max(320, element.offsetWidth));
        this.open = !!this.query.trim();
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
    choose(option) {
        this.model.stock_item_id = option?.id || null;
        this.model.stock_quantity = null;
        this.model.item_name = option?.name || '';
        this.query = '';
        this.selected = 0;
        this.open = false;
        this.$dispatch('stock-item-link-changed');
    },
});
