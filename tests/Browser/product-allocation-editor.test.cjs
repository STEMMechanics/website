const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const context = { window: {} };
    for (const name of ['product-allocation', 'product-allocation-editor']) {
        vm.runInNewContext(fs.readFileSync(`resources/js/${name}.js`, 'utf8').replace(/^import .*;\n/gm, '').replace('export function', 'function'), context);
    }
    const config = { categories: [2, 5, 6], taxRate: .1, data: { base: { fixed: {2: 50}, percent: {5: 4000, 6: 6000} }, variants: {} } };
    const editor = context.window.SM.productAllocationEditor(config);
    Object.assign(editor, { variants: [{id: 1, name: '10 Pack', price: '12.95', is_active: true}], basePrice: '2.95', baseOptionDisplayName: () => '2 Pack', displayVariantName: variant => variant.name });
    editor.init();
    return editor;
}
test('variants show live inherited base values until editing one cell creates an independent override', () => {
    const editor = setup();
    const base = editor.columns[0], variant = editor.columns[1];
    assert.equal(editor.values(variant)[2].amount, '0.50');
    editor.setValue(base, 2, '0.75');
    assert.equal(editor.values(variant)[2].amount, '0.75');
    editor.setValue(variant, 2, '2.50');
    editor.setValue(base, 2, '0.50');
    assert.equal(editor.values(variant)[2].amount, '2.50');
    assert.equal(editor.total(base).net, 268);
    assert.equal(editor.total(variant).net, 1177);
    assert.equal(editor.total(variant).remaining, 0);
    assert.equal(editor.summary, 'All options allocated');
    editor.useBase(variant);
    assert.equal(editor.values(variant)[2].amount, '0.50');
});
test('percentage toggle changes the rule type, money formats on blur and totals flag missing or excessive allocations', () => {
    const editor = setup(), base = editor.columns[0];
    editor.setValue(base, 2, '1');
    editor.format(base, 2);
    assert.equal(editor.values(base)[2].amount, '1.00');
    editor.toggleType(base, 2, true);
    let payload = JSON.parse(editor.payload);
    assert.equal(payload.base.percent[2], 100);
    assert.equal(payload.base.fixed[2], undefined);
    assert.equal(editor.total(base).excessive, true);
    editor.setValue(base, 5, '0'); editor.setValue(base, 6, '0');
    assert.equal(editor.total(base).missing, true);
    editor.setValue(base, 2, '100');
    assert.equal(editor.total(base).missing, false);
});
test('variant allocation follows the variant when reordered or removed and new variants inherit', () => {
    const editor = setup();
    editor.setValue(editor.columns[1], 2, '2.50');
    editor.variants.unshift({id: null, name: '5 Pack', price: '6.95'});
    let payload = JSON.parse(editor.payload);
    assert.equal(payload.variants[0].inherit, true);
    assert.equal(payload.variants[1].rules.fixed[2], 250);
    editor.variants.splice(0, 1);
    assert.equal(JSON.parse(editor.payload).variants[0].rules.fixed[2], 250);
});
test('existing rules with fixed and percentage values on the same cost centre are preserved', () => {
    const editor = setup();
    editor.baseCells = editor.cells({fixed: {2: 50}, percent: {2: 4000, 6: 6000}});
    assert.equal(editor.values(editor.columns[0])[2].extraPercent, '40.00');
    assert.equal(JSON.parse(editor.payload).base.fixed[2], 50);
    assert.equal(JSON.parse(editor.payload).base.percent[2], 4000);
});
