const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const context = { window: {} };
    for (const name of ['workshop-line', 'product-allocation', 'allocation-tally']) vm.runInNewContext(fs.readFileSync(`resources/js/${name}.js`, 'utf8').replace('export function', 'function'), context);
    return context;
}
test('product costs are recovered before margin and cannot exceed discounted revenue', () => {
    const ctx = setup();
    const rules = { fixed: { 2: 4000 }, percent: { 1: 4000, 6: 6000 } };
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.productTargets(rules, 2, 12000))), { 1: 1600, 2: 8000, 6: 2400 });
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.productTargets(rules, 1, 3000))), { 1: 0, 2: 3000, 6: 0 });
});
test('live invoice preview retains saved product rules across quantity changes, discounts and manual overrides', () => {
    const ctx = setup();
    const config = { values: { 1: '0', 2: '0', 6: '0' }, enabled: false, products: { 10: { product_id: 1, rules: { fixed: { 2: 4000 }, percent: { 1: 4000, 6: 6000 } } } } };
    const tally = ctx.window.SM.allocationTally(config);
    const items = [{ id: 10, source_id: 1, kind: 'product', quantity: 2, unit_price: 60 }, { kind: 'discount', quantity: 1, unit_price: -20 }];
    tally.previewInvoice({ items, total: 10000 }, []);
    assert.equal(tally.values[2], '80.00');
    assert.equal(tally.values[1], '8.00');
    assert.equal(tally.values[6], '12.00');
    tally.enabled = true;
    tally.values[2] = '1.23';
    tally.previewInvoice({ items, total: 9000 }, []);
    assert.equal(tally.values[2], '1.23');
});
