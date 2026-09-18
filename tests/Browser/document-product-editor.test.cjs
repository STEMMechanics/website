const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function editor() {
    const window = {};
    const context = { window };
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
    context.SM = window.SM;
    vm.runInNewContext(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'), context);
    return { ...window.SM.productLineEditor([{ id: 1, title: 'Kit', price: 11, tax_rate: 0.1, has_option_choices: true,
        variants: [{id: 2, name: 'Large', price: 22}, {id: 3, name: 'Free', price: 0}, {id: 4, name: 'Inherited', price: null}] }]),
        lineItems: [{kind: 'product', source_id: '1', source_variant_id: '2', quantity: 2, details_json: {}, notes: '', saved_pricing: {gross: 11}}],
        serializeLineItems() {},
    };
}
test('variant selection updates price and invalidates saved pricing', () => {
    const e = editor(); e.applyProductSelection(0);
    assert.equal(e.lineItems[0].unit_price_inc_tax, '22.00');
    assert.equal(e.lineItems[0].saved_pricing, null);
    assert.equal(e.lineItems[0].description, 'Kit - Large');
    assert.equal(e.invoiceProductItem(e.lineItems[0]).details_json.variant_id, 2);
    assert.equal(e.invoiceProductItem(e.lineItems[0]).source_type, 'App\\Models\\Product');
});
test('zero priced variants and base-price fallbacks are distinct', () => {
    const e = editor();
    for (const [variant, price] of [['3', '0.00'], ['4', '11.00'], ['0', '11.00']]) {
        e.lineItems[0].source_variant_id = variant; e.applyProductSelection(0);
        assert.equal(e.lineItems[0].unit_price_inc_tax, price);
    }
});
test('store selection leaves notes blank and preserves notes entered by the user', () => {
    const e = editor();
    e.findProduct(1).summary = 'Product short description';
    e.findProduct(1).variants[0].summary = 'Variant short description';
    e.applyProductSelection(0);
    assert.equal(e.lineItems[0].notes, '');
    e.lineItems[0].notes = 'Please deliver on Friday';
    e.lineItems[0].source_variant_id = '3';
    e.applyProductSelection(0);
    assert.equal(e.lineItems[0].notes, 'Please deliver on Friday');
});
test('opening an existing invoice does not replace saved product metadata or manual pricing', () => {
    const e = editor(); const item = {kind: 'product', source_id: 1, source_variant_id: '2', unit_price_inc_tax: '19.25', details_json: {variant_id: 2}};
    assert.equal(e.invoiceProductItem(item), item);
    assert.equal(item.unit_price_inc_tax, '19.25');
});
test('changing type removes old product references', () => {
    const e = editor();e.applyProductSelection(0);e.lineItems[0] = e.invoiceProductItem(e.lineItems[0]);
    e.lineItems[0].kind = 'custom'; e.applyKind(0);
    assert.equal(e.lineItems[0].source_id, '');
    assert.equal(e.lineItems[0].source_type, null);
    assert.equal(e.lineItems[0].details_json.variant_id, undefined);
    assert.equal(e.lineItems[0].details_json.store_context, undefined);
});
test('clearing a product selection removes stale pricing and references', () => {
    const e = editor(); e.applyProductSelection(0);e.lineItems[0].source_id = '';e.applyProductSelection(0);
    assert.equal(e.lineItems[0].unit_price_inc_tax, '0.00');
    assert.equal(e.lineItems[0].description, '');
    assert.equal(e.lineItems[0].source_type, null);
});
