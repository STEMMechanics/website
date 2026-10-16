const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function editor(remainder = null, defaults = {}) {
    const context = { window: {} };
    for (const name of ['product-material-quantity', 'allocation-remainder', 'product-materials', 'product-variant-editor', 'product-allocation', 'product-allocation-editor']) {
        vm.runInNewContext(fs.readFileSync(`resources/js/${name}.js`, 'utf8').replace(/^import .*;\n/gm, '').replace(/^export /gm, ''), context);
    }
    const state = {
        ...context.window.SM.productMaterials({ unitCost: .31, basePackUnits: 1, operationalCost: 6, timeCost: 3.5, automaticAllocation: true }),
        ...context.window.SM.allocationRemainder(remainder, defaults),
        ...context.window.SM.productVariantEditor(),
        productType: 'physical', productTaxRate: .1, sharedInventory: false, basePrice: '12.95',
        baseOptionDisplayName: () => 'Shared defaults', displayVariantName: variant => variant.name,
        variants: [
            { id: 1, name: '2 Pack', price: '4.95', inventory_units: 2, is_active: false, sort_order: 2 },
            { id: 2, name: '10 Pack', price: '15.95', inventory_units: 10, is_active: true, is_base: true, sort_order: 0 },
            { id: 3, name: '25 Pack', price: '37.95', inventory_units: 25, is_active: true, sort_order: 1 },
        ],
    };
    const allocation = context.window.SM.productAllocationEditor({ categories: [2, 5, 6, 8], centres: { stock: 8, consumables: 2, operational: 5, 'owner remuneration': 6 }, taxRate: .1,
        data: { base: { fixed: { 8: 330 }, percent: {} }, variants: { 1: { inherit: false, rules: { fixed: { 2: 9000 }, percent: { 8: 5000 } } } } } });
    Object.defineProperties(state, Object.getOwnPropertyDescriptors(allocation));
    state.initVariantEditor();
    state.init();
    return state;
}

test('only active selling variants appear, in public order, with stable original payload indices', () => {
    const state = editor();
    assert.deepEqual(Array.from(state.columns, column => column.name), ['10 Pack', '25 Pack']);
    assert.deepEqual(Array.from(state.columns, column => column.key), ['1', '2']);
    assert.equal(state.mobileColumn, '1');
    state.selectBaseVariant(state.variants[2]);
    assert.deepEqual(Array.from(state.columns, column => column.name), ['25 Pack', '10 Pack']);
    const payload = JSON.parse(state.payload);
    assert.equal(payload.base.fixed[8], 330);
    assert.equal(payload.variants[0].rules.fixed[2], 9000);
    assert.equal(payload.variants[0].rules.percent[8], 5000);
    assert.equal(payload.variants.length, 3);
});

test('cost recovery and business cash update live without manual balancing', () => {
    const state = editor();
    const column = state.columns[0];
    assert.equal(state.values(column)[8].amount, '3.10');
    assert.equal(state.values(column)[5].amount, '6.00');
    assert.equal(state.values(column)[6].amount, '3.50');
    assert.equal(state.total(column).remaining, 190);
    assert.equal(state.total(column).missing, false);
    assert.equal(state.summary, 'Automatically allocated');
    state.operationalCost = 7;
    assert.equal(state.total(column).remaining, 90);
    state.productTaxRate = 0;
    assert.equal(state.total(column).remaining, 235);
    assert.equal(state.setValue(column, 8, '9.99'), '3.10');
});

test('remainder can inherit, go to one centre, split by percentages, or remain as cash', () => {
    const state = editor(null, { 8: 10000 });
    const column = state.columns[0];
    assert.equal(state.values(column)[8].amount, '5.00');
    assert.equal(state.contribution(column, 8), '$3.10 cost + $1.90 remainder');
    assert.equal(state.total(column).remaining, 0);
    state.remainderMode = 'split';
    state.remainderPercent = { 8: 40, 2: 60 };
    assert.equal(state.values(column)[8].amount, '3.86');
    assert.equal(state.values(column)[2].amount, '1.14');
    assert.equal(state.total(column).allocated, 1450);
    state.remainderPercent = { 8: 50 };
    assert.equal(state.total(column).remaining, 95);
    state.remainderMode = 'cash';
    assert.equal(state.total(column).remaining, 190);
    assert.equal(state.remainderJson(), '{}');
    state.remainderMode = '6';
    assert.equal(state.values(column)[6].amount, '5.40');
    state.remainderMode = 'default';
    assert.equal(state.remainderJson(), 'null');
});

test('unknown costs, unavailable centres and excessive costs remain visible as problems', () => {
    const state = editor();
    const column = state.columns[0];
    state.unitCost = '';
    assert.equal(state.total(column).unknown, true);
    assert.match(state.summary, /need allocation review/);
    state.unitCost = .31;
    state.operationalCost = 20;
    assert.equal(state.total(column).excessive, true);
    assert.equal(state.values(column)[5].amount, '20.00');
    state.operationalCost = 6;
    state.remainderMode = '99999';
    assert.equal(state.total(column).unknown, true);
});

test('manual allocations survive toggling Automatic and edits apply only to the selected variant', () => {
    const state = editor();
    const before = state.payload;
    state.automaticAllocation = false;
    state.automaticAllocation = true;
    assert.equal(state.payload, before);
    state.automaticAllocation = false;
    state.setValue(state.columns[0], 2, '2.50');
    const payload = JSON.parse(state.payload);
    assert.equal(payload.variants[1].rules.fixed[2], 250);
    assert.equal(payload.variants[0].rules.fixed[2], 9000);
    assert.equal(payload.variants[0].rules.percent[8], 5000);
});

test('expense classification, explicit overrides, fallback costs and recipe components keep their centres', () => {
    const state = editor();
    const column = state.columns[0];
    state.purchaseCosts = { base: .31 };
    state.purchaseCostCentres = { base: 'consumables' };
    assert.equal(state.values(column)[2].amount, '3.10');
    state.purchaseCostCentre = 'stock';
    assert.equal(state.values(column)[8].amount, '3.10');
    state.purchaseCostCentre = 'auto';
    column.variant.unit_cost_ex_tax = 4;
    assert.equal(state.values(column)[8].amount, '4.00');
    state.recipeCatalog = [
        { id: 1, product_type: 'physical', pack_units: 1, unit_cost: .5, cost_centres: { stock: .5 } },
        { id: 2, product_type: 'physical', pack_units: 1, unit_cost: .2, cost_centres: { consumables: .2 } },
    ];
    state.recipeRows = [{ product_id: 1, quantity: 2 }, { product_id: 2, quantity: 3 }];
    assert.equal(state.values(column)[8].amount, '10.00');
    assert.equal(state.values(column)[2].amount, '6.00');
});

test('invalid split edits cannot be silently saved as inheritance or zero', () => {
    const state = editor();
    state.remainderMode = 'split';
    for (const invalid of [{ 8: 101 }, { 8: -1 }, { 8: 50, 2: 51 }, { 8: 1.001 }, { 8: 'abc' }]) {
        state.remainderPercent = invalid;
        assert.equal(state.remainderCashPercent(), null);
        assert.deepEqual(JSON.parse(state.remainderJson()), { invalid: null });
        assert.equal(state.total(state.columns[0]).unknown, true);
    }
});

test('untouched manual percentage rules preview the current price and stay intact until edited', () => {
    const state = editor();
    state.automaticAllocation = false;
    const variant = state.variants[1];
    const rules = { fixed: { 8: 310 }, percent: { 6: 10000 } };
    variant.allocation.sourceRules = rules;
    variant.allocation.original = { inherit: false, rules };
    assert.equal(state.values(state.columns[0])[6].amount, '11.40');
    variant.price = '22.00';
    assert.equal(state.values(state.columns[0])[6].amount, '16.90');
    assert.deepEqual(JSON.parse(state.payload).variants[1].rules, rules);
    state.setValue(state.columns[0], 6, '15.00');
    assert.equal(state.values(state.columns[0])[6].amount, '15.00');
    assert.deepEqual(JSON.parse(state.payload).variants[1].rules.percent, {});
});
