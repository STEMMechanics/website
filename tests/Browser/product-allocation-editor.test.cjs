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
test('legacy percentages become dollars at each option price and save as independent fixed allocations', () => {
    const editor = setup(), base = editor.columns[0], variant = editor.columns[1];
    assert.equal(editor.values(base)[5].amount, '0.87');
    assert.equal(editor.values(base)[6].amount, '1.31');
    assert.equal(editor.values(variant)[5].amount, '4.51');
    assert.equal(editor.values(variant)[6].amount, '6.76');
    const payload = JSON.parse(editor.payload);
    assert.deepEqual(payload.base.percent, {});
    assert.deepEqual(payload.variants[0].rules.percent, {});
    assert.equal(payload.variants[0].inherit, false);
    editor.setValue(base, 2, '0.75');
    assert.equal(editor.values(variant)[2].amount, '0.50');
});
test('dollars format to two decimals and flag missing or excessive allocations', () => {
    const editor = setup(), base = editor.columns[0];
    editor.setValue(base, 2, '1'); editor.format(base, 2);
    assert.equal(editor.values(base)[2].amount, '1.00');
    assert.equal(editor.total(base).excessive, true);
    assert.equal(editor.total(base).allocated, 318);
    editor.setValue(base, 5, '0'); editor.setValue(base, 6, '0');
    assert.equal(editor.total(base).remaining, 168);
    editor.setValue(base, 2, '2.68');
    assert.equal(editor.total(base).missing, false);
});
test('allocations follow reordered variants and new options start with empty dollar allocations', () => {
    const editor = setup();
    editor.setValue(editor.columns[1], 2, '2.50');
    editor.variants.unshift({id: null, name: '5 Pack', price: '6.95'});
    const payload = JSON.parse(editor.payload);
    assert.equal(payload.variants[0].inherit, false);
    assert.equal(payload.variants[0].rules.fixed[2], 0);
    assert.equal(payload.variants[1].rules.fixed[2], 250);
    editor.variants.splice(0, 1);
    assert.equal(JSON.parse(editor.payload).variants[0].rules.fixed[2], 250);
});
test('legacy fixed plus percentage rules combine without hiding costs above sale price', () => {
    const editor = setup();
    editor.baseCells = editor.cells({fixed: {2: 50}, percent: {2: 4000, 6: 6000}});
    assert.equal(JSON.parse(editor.payload).base.fixed[2], 137);
    assert.equal(JSON.parse(editor.payload).base.fixed[6], 131);
    editor.baseCells = editor.cells({fixed: {2: 500}, percent: {6: 10000}});
    assert.equal(JSON.parse(editor.payload).base.fixed[2], 500);
    assert.equal(editor.total(editor.columns[0]).excessive, true);
});
