import { defaultProductQuantityUnit } from './product-material-quantity';

window.SM = window.SM || {};

window.SM.productItemLinkEditor = (model, catalog = [], itemNameSuggestions = [], requireSelection = false) => ({
    model,
    catalog: Array.isArray(catalog) ? catalog : [],
    itemNameSuggestions: Array.isArray(itemNameSuggestions) ? itemNameSuggestions : [],
    requireSelection,
    query: '',
    open: false,
    selected: requireSelection ? 0 : -1,
    menuTop: 0,
    menuLeft: 0,
    menuWidth: 320,
    menuMaxHeight: 320,
    get linkedProduct() {
        const productId = String(this.model?.product_id ?? '');

        return this.catalog.find((option) => String(option.id) === productId) || null;
    },
    get matches() {
        const query = this.query.trim().toLowerCase();
        const currentProductId = String(this.model?.product_id ?? '');
        const uniqueBlueprintNames = [...new Map(this.itemNameSuggestions
            .map((name) => String(name || '').trim())
            .filter(Boolean)
            .map((name) => [name.toLowerCase(), name])).values()];
        const options = [
            ...this.catalog
                .filter((option) => option.status !== 'archived' || String(option.id) === currentProductId)
                .map((option) => ({ ...option, suggestionType: 'product' })),
            ...uniqueBlueprintNames
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

        const sortedOptions = options.sort(
            (a, b) => matchRank(a) - matchRank(b) || String(a.name).localeCompare(String(b.name)),
        );
        const blueprintTextMatches = sortedOptions
            .filter((option) => option.suggestionType === 'blueprint-text')
            .slice(0, 4);
        const productMatches = sortedOptions
            .filter((option) => option.suggestionType === 'product')
            .slice(0, Math.max(0, 12 - blueprintTextMatches.length));

        return [...productMatches, ...blueprintTextMatches]
            .sort((a, b) => matchRank(a) - matchRank(b) || String(a.name).localeCompare(String(b.name)));
    },
    position(element, width = 320) {
        const rect = element.getBoundingClientRect();
        this.menuWidth = Math.min(width, window.innerWidth - 16);
        this.menuTop = rect.bottom + 4;
        this.menuMaxHeight = Math.max(96, Math.min(320, window.innerHeight - this.menuTop - 8));
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - this.menuWidth - 8));
    },
    editDescription(element) {
        const value = String(element.value || '');
        this.model.item_name = value;

        if (this.model?.product_id && value !== this.linkedProduct?.name) {
            this.model.product_id = null;
            this.model.product_variant_id = null;
            this.model.cut_size_id = null;
            this.model.length = null;
            this.model.length_unit = null;
            this.model.quantity_unit = null;
            this.$dispatch('product-item-link-changed');
        }

        this.query = value;
        this.selected = this.requireSelection ? 0 : -1;
        this.position(element, Math.max(320, element.offsetWidth));
        this.open = true;
    },
    browse(element) {
        if (this.open) {
            this.close();
            return;
        }

        const input = this.$refs.productItemInput || element;
        this.position(input, Math.max(320, input.offsetWidth));
        this.query = String(input.value || '');
        this.selected = this.requireSelection ? 0 : -1;
        this.open = true;
        input.focus({ preventScroll: true });
    },
    move(event, step) {
        if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;

        event.preventDefault();
        event.stopPropagation();

        if (!this.open) {
            this.browse(event.target);
            this.selected = -1;
        }

        const count = this.matches.length;
        if (!count) {
            this.selected = -1;
            return;
        }

        this.selected = this.selected < 0 || this.selected >= count
            ? (step > 0 ? 0 : count - 1)
            : (this.selected + step + count) % count;
        this.$nextTick(() => {
            this.$refs.productItemOptions?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
        });
    },
    handleEnter(event) {
        if (!this.open || event.isComposing) return;

        event.preventDefault();
        event.stopPropagation();

        const option = this.matches[this.selected];
        if (option || this.requireSelection || event.metaKey || event.ctrlKey) {
            const selection = option || this.matches[0];
            if (selection) this.choose(selection);
            return;
        }

        this.close();
    },
    close() {
        this.open = false;
        this.selected = -1;
    },
    choose(option) {
        const isBlueprintTextSuggestion = option?.suggestionType === 'blueprint-text';
        this.model.product_id = isBlueprintTextSuggestion ? null : (option?.id || null);
        this.model.product_variant_id = null;
        this.model.cut_size_id = null;
        this.model.length = null;
        this.model.length_unit = null;
        this.model.quantity_unit = isBlueprintTextSuggestion ? null : defaultProductQuantityUnit(option);
        this.model.item_name = option?.name || '';
        this.query = '';
        this.close();
        this.$dispatch('product-item-link-changed');
        this.$refs.productItemInput?.focus();
    },
});
