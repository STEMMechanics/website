window.SM = window.SM || {};

window.SM.stockReceiptExpenseEditor = (supplier = '', expenseId = '', expenses = [], scope = 'stock-receipt') => ({
    supplier: String(supplier || ''),
    expenseId: String(expenseId || ''),
    scope: String(scope || 'stock-receipt'),
    catalog: Array.isArray(expenses) ? expenses : [],
    query: '',
    open: false,
    selected: 0,
    menuTop: 0,
    menuLeft: 0,
    menuWidth: 420,
    get linkedExpense() {
        return this.catalog.find((expense) => String(expense.id) === this.expenseId) || null;
    },
    get matches() {
        const query = this.query.trim().toLowerCase();

        return this.catalog
            .filter((expense) => !query || String(expense.search || expense.label || '').toLowerCase().includes(query))
            .slice(0, 20);
    },
    position(element, width = 420) {
        const rect = element.getBoundingClientRect();
        this.menuWidth = Math.min(width, window.innerWidth - 16);
        this.menuTop = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - 320));
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - this.menuWidth - 8));
    },
    browse(element) {
        this.position(element);
        this.query = '';
        this.selected = 0;
        this.open = !this.open;
    },
    editSupplier(value) {
        this.supplier = String(value || '');
        if (this.expenseId) {
            this.expenseId = '';
            window.dispatchEvent(new CustomEvent('stock-expense-selected', {
                detail: { expense: null, scope: this.scope },
            }));
        }
    },
    clearValue() {
        this.supplier = '';
        this.expenseId = '';
        this.query = '';
        this.selected = 0;
        this.open = false;
        window.dispatchEvent(new CustomEvent('stock-expense-selected', {
            detail: { expense: null, scope: this.scope },
        }));
    },
    move(step) {
        if (!this.matches.length) return;

        this.selected = Math.max(0, Math.min(this.matches.length - 1, this.selected + step));
    },
    choose(expense) {
        this.expenseId = expense?.id ? String(expense.id) : '';
        if (expense) this.supplier = String(expense.supplier || '');
        this.query = '';
        this.selected = 0;
        this.open = false;

        window.dispatchEvent(new CustomEvent('stock-expense-selected', {
            detail: { expense: expense || null, scope: this.scope },
        }));
    },
});

window.SM.stockReceiptMovementForm = (date = '', totalCost = '', expenseId = '', scope = 'stock-receipt') => ({
    movementDate: String(date || ''),
    totalCost: String(totalCost ?? ''),
    expenseId: String(expenseId || ''),
    expenseScope: String(scope || 'stock-receipt'),
    totalCostAutoFilled: false,
    get isReceipt() {
        return this.totalCost.trim() !== '' || this.expenseId !== '';
    },
    selectExpense(expense, scope) {
        if (String(scope || '') !== this.expenseScope) return;

        this.expenseId = expense?.id ? String(expense.id) : '';
        if (expense?.date) this.movementDate = String(expense.date);

        const amount = expense?.total_cost_ex_tax;
        if (amount !== undefined && amount !== null && (this.totalCost.trim() === '' || this.totalCostAutoFilled)) {
            this.totalCost = Number(amount).toFixed(2);
            this.totalCostAutoFilled = true;
        } else if (!expense && this.totalCostAutoFilled) {
            this.totalCost = '';
            this.totalCostAutoFilled = false;
        }
    },
    manualTotalCost() {
        this.totalCostAutoFilled = false;
    },
    formatTotalCost(input = null) {
        const value = this.totalCost.trim();
        if (value === '') return;

        const amount = Number(value);
        if (Number.isFinite(amount) && amount >= 0) {
            this.totalCost = amount.toFixed(2);
            if (input instanceof HTMLInputElement) input.value = this.totalCost;
        }
    },
});
