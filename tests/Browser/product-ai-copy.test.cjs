const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const plain = value => JSON.parse(JSON.stringify(value));

test('product descriptions render factual hyphen bullets as escaped green tick list items', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const toProductDescriptionHtml =');
    const end = source.indexOf('\n\nconst toWorkshopDescriptionHtml', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const context = {};
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.toProductDescriptionHtml = toProductDescriptionHtml;`, context);

    const html = context.toProductDescriptionHtml('Two AAA batteries provide a compact 3V power source.\n\n- Holds 2 × AAA batteries\n- Red and black connecting wires\n- <script>unsupported</script>');
    assert.match(html, /^<p>Two AAA batteries provide a compact 3V power source\.<\/p><ul data-list-style="ticks">/);
    assert.match(html, /<li><p>Holds 2 × AAA batteries<\/p><\/li>/);
    assert.match(html, /&lt;script&gt;unsupported&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>/);
});

test('product AI warning and specification results update only their intended fields', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const applyProductAiAction =');
    const end = source.indexOf('\n\nconst makePayload', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const field = { value: 'Current warning', events: [], dispatchEvent(event) { this.events.push(event.type); } };
    const events = [];
    const statuses = [];
    const context = {
        Event: class { constructor(type) { this.type = type; } },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        window: { dispatchEvent: event => events.push(event) },
        setStatus: (_root, message) => statuses.push(message),
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.applyProductAiAction = applyProductAiAction;`, context);

    const form = { elements: { namedItem: name => name === 'caution_message' ? field : null } };
    const root = {};
    assert.equal(context.applyProductAiAction(root, { dataset: { aiAction: 'product-warning' } }, { warning: 'Batteries are not included.' }, form), true);
    assert.equal(field.value, 'Batteries are not included.');
    assert.deepEqual(field.events, ['input', 'change']);
    assert.equal(statuses.at(-1), 'Warning drafted. Review it before saving.');

    assert.equal(context.applyProductAiAction(root, { dataset: { aiAction: 'product-warning' } }, { warning: '' }, form), true);
    assert.equal(field.value, 'Batteries are not included.');

    const details = [{ key: 'Battery type', value: '2 × AAA' }];
    assert.equal(context.applyProductAiAction(root, { dataset: { aiAction: 'product-specifications' } }, { product_details: details }, form), true);
    assert.equal(events[0].type, 'sm-product-specifications-ai');
    assert.deepEqual(plain(events[0].detail.details), [
        ...details,
        { key: 'SKU', value: '{sku}' },
    ]);
    assert.equal(statuses.at(-1), 'Specifications drafted. Review them before saving.');

    assert.equal(context.applyProductAiAction(root, { dataset: { aiAction: 'product-specifications' } }, { product_details: [] }, form), true);
    assert.deepEqual(plain(events[1].detail.details), [{ key: 'SKU', value: '{sku}' }]);
    assert.equal(statuses.at(-1), 'No new specifications were found. SKU is kept as the final detail.');

    assert.throws(() => context.applyProductAiAction(root, { dataset: { aiAction: 'product-specifications' } }, { product_details: [{ key: 'Missing value' }] }, form), /unreadable product specifications/);
});

test('product specification merge preserves or adds one final SKU row', () => {
    const source = fs.readFileSync('resources/views/admin/shop/product/edit.blade.php', 'utf8');
    const start = source.indexOf('                ensureBaseSkuDetail(details) {');
    const end = source.indexOf('\n                moveProductDetail(index, direction)', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const context = {};
    vm.createContext(context);
    vm.runInContext(`globalThis.detailMethods = ({\n${source.slice(start, end)}\n});`, context);

    const state = {
        productDetails: [{ key: 'Material', value: 'Paper' }],
        variants: [],
        ...context.detailMethods,
    };
    state.mergeAiProductDetails([{ key: 'Pack size', value: '150 straws' }]);
    assert.deepEqual(plain(state.productDetails), [
        { key: 'Material', value: 'Paper' },
        { key: 'Pack size', value: '150 straws' },
        { key: 'SKU', value: '{sku}' },
    ]);

    state.mergeAiProductDetails([{ key: 'Colour', value: 'Blue' }, { key: 'SKU', value: 'WRONG' }]);
    assert.deepEqual(plain(state.productDetails.at(-1)), { key: 'SKU', value: '{sku}' });
    assert.equal(state.productDetails.filter((detail) => detail.key.toLowerCase() === 'sku').length, 1);

    state.productDetails = state.normalizeBaseProductDetails([
        { key: 'Batteries includes', value: 'No' },
        { key: 'Batteries included', value: 'No' },
    ]);
    assert.deepEqual(plain(state.productDetails), [
        { key: 'Batteries included', value: 'No' },
        { key: 'SKU', value: '{sku}' },
    ]);

    state.mergeAiProductDetails([
        { key: 'Batteries includes', value: 'Yes' },
        { key: 'Batteries included', value: 'Yes' },
    ]);
    assert.deepEqual(plain(state.productDetails), [
        { key: 'Batteries included', value: 'Yes' },
        { key: 'SKU', value: '{sku}' },
    ]);

    state.productDetails = state.normalizeBaseProductDetails([
        { key: 'Pack size', value: '2 holders' },
    ]);
    state.variants = [{ name: '10 Pack', product_details: [] }];
    state.mergeAiProductDetails([{ key: 'Pack size', value: '10 holders' }]);
    assert.deepEqual(plain(state.productDetails), [
        { key: 'Pack size', value: '2 holders' },
        { key: 'SKU', value: '{sku}' },
    ]);

    state.variants = [{ name: '10 Pack', product_details: [{ key: 'Pack size', value: '10 holders' }] }];
    state.mergeAiProductDetails([{ key: 'Pack size', value: '10 holders' }]);
    assert.deepEqual(plain(state.productDetails), [
        { key: 'Pack size', value: '2 holders' },
        { key: 'SKU', value: '{sku}' },
    ]);
});
