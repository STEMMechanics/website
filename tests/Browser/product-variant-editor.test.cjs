const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function editor(variants, variantErrors = [], config = {}, fetch = undefined) {
    const context = { window: { SM: {} }, fetch, AbortSignal };
    vm.runInNewContext(readFileSync('resources/js/product-variant-editor.js', 'utf8'), context);
    const state = { ...context.window.SM.productVariantEditor(config), variants, variantErrors };
    state.initVariantEditor();
    return state;
}

test('base selection is exclusive and never changes variant fields', () => {
    const variants = [{ id: 1, is_active: true, is_base: true, price: 4.95, sku: 'SMALL' }, { id: 2, is_active: true, price: 14.95, sku: 'LARGE' }];
    const state = editor(variants);
    state.selectBaseVariant(variants[1]);
    assert.equal(variants[0].is_base, false);
    assert.equal(variants[1].is_base, true);
    assert.equal(variants[0].price, 4.95);
    assert.equal(variants[1].sku, 'LARGE');
});

test('retiring the base chooses the next active option in sort order and wraps', () => {
    const variants = [{ id: 1, sort_order: 10, is_active: true, is_base: true }, { id: 2, sort_order: 20, is_active: false }, { id: 3, sort_order: 30, is_active: true }];
    const state = editor(variants);
    variants[0].is_active = false;
    state.variantActiveChanged(variants[0]);
    assert.equal(variants[2].is_base, true);
    variants[0].is_active = true;
    variants[2].is_active = false;
    state.variantActiveChanged(variants[2]);
    assert.equal(variants[0].is_base, true);
    variants[0].is_active = false;
    state.variantActiveChanged(variants[0]);
    assert.equal(variants.some(variant => variant.is_base), false);
});

test('variant cards have stable identities and open for new rows and validation errors', () => {
    const variants = [{ id: 1, is_active: true }, { id: 2, is_active: true }, { id: null, is_active: true }];
    const state = editor(variants, ['variants.1.price']);
    assert.equal(variants[0].open, false);
    assert.equal(variants[1].open, true);
    assert.equal(variants[2].open, true);
    const key = variants[1].editor_key;
    state.selectBaseVariant(variants[1]);
    state.removeVariant(0);
    assert.equal(variants[0].editor_key, key);
    assert.equal(variants[0].is_base, true);
});

test('restored form flags keep distinct variants from becoming the original option', () => {
    const variants = [
        { id: 1, is_active: '1', is_base: '1', original_option: '1' },
        { id: 2, is_active: '1', is_base: '0', original_option: '0' },
        { id: null, is_active: '0', is_base: '0', original_option: '0' },
    ];
    const state = editor(variants, ['variants.1.automatic_pricing']);
    assert.deepEqual(variants.map(variant => variant.original_option), [true, false, false]);
    assert.equal(state.variantDeletionDisabledReason(variants[1]), '');
    assert.equal(variants[1].open, true);
    assert.equal(variants[2].is_active, false);
});

test('deactivating a non-base variant collapses it while allowing it to be reopened', () => {
    const variants = [{ id: 1, is_active: true, is_base: true }, { id: 2, is_active: true }];
    const state = editor(variants);
    variants[1].open = true;
    variants[1].is_active = false;
    state.variantActiveChanged(variants[1]);
    assert.equal(variants[1].open, false);
    variants[1].open = true;
    variants[1].is_active = true;
    state.variantActiveChanged(variants[1]);
    assert.equal(variants[1].open, true);
    assert.equal(variants[0].is_base, true);
});

test('display order pins the base first and inactive variants last without changing form indices', () => {
    const variants = [
        { id: 1, sort_order: 0, is_active: false },
        { id: 2, sort_order: 5, is_active: true },
        { id: 3, sort_order: 10, is_active: true, is_base: true },
        { id: 4, sort_order: 15, is_active: true },
    ];
    const state = editor(variants);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.id), [3, 2, 4, 1]);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.index), [2, 1, 3, 0]);
    assert.deepEqual(variants.map(variant => variant.id), [1, 2, 3, 4]);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.sort_order), [0, 1, 2, 3]);
});

test('arrows reorder within active and inactive groups and respect the base boundary', () => {
    const variants = [
        { id: 1, is_active: true, is_base: true, sort_order: 0 },
        { id: 2, is_active: true, sort_order: 1 },
        { id: 3, is_active: true, sort_order: 2 },
        { id: 4, is_active: false, sort_order: 3 },
        { id: 5, is_active: false, sort_order: 4 },
    ];
    const state = editor(variants);
    state.moveVariant(1, 1);
    state.moveVariant(4, -1);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.id), [1, 3, 2, 5, 4]);
    assert.match(state.variantMoveDisabledReason(variants[0], 1), /base/);
    assert.match(state.variantMoveDisabledReason(variants[2], -1), /base/);
    assert.match(state.variantMoveDisabledReason(variants[1], 1), /Inactive/);
    assert.match(state.variantMoveDisabledReason(variants[4], -1), /Inactive/);
    assert.match(state.variantMoveDisabledReason(variants[3], 1), /last/);
    state.moveVariant(2, -1);
    state.moveVariant(4, -1);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.id), [1, 3, 2, 5, 4]);
});

test('changing base and activity updates display order and collapses the retired variant', () => {
    const variants = [
        { id: 1, is_active: true, is_base: true, sort_order: 0, sku: 'ONE' },
        { id: 2, is_active: true, sort_order: 1, sku: 'TWO', allocation: { amount: 9 } },
        { id: 3, is_active: false, sort_order: 2, sku: 'THREE' },
    ];
    const state = editor(variants);
    variants[1].open = true;
    state.selectBaseVariant(variants[1]);
    assert.equal(variants[1].open, true);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.sku), ['TWO', 'ONE', 'THREE']);
    variants[1].is_active = false;
    state.variantActiveChanged(variants[1]);
    assert.equal(variants[0].is_base, true);
    assert.equal(variants[1].open, false);
    assert.equal(variants[1].allocation.amount, 9);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.sku), ['ONE', 'TWO', 'THREE']);
    variants[2].is_active = true;
    state.variantActiveChanged(variants[2]);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.sku), ['ONE', 'THREE', 'TWO']);
});

test('deletion explains blocked actions and removes only an unused non-base variant', () => {
    const variants = [
        { id: 1, is_active: true, is_base: true },
        { id: 2, is_active: false, original_option: true },
        { id: 3, is_active: true, deletion_disabled_reason: 'Used by an order.' },
        { id: 4, is_active: true },
    ];
    const state = editor(variants);
    assert.match(state.variantDeletionDisabledReason(variants[0]), /base/);
    assert.match(state.variantDeletionDisabledReason(variants[1]), /original/);
    assert.equal(state.variantDeletionDisabledReason(variants[2]), 'Used by an order.');
    state.removeVariant(0);
    state.removeVariant(1);
    state.removeVariant(2);
    assert.equal(variants.length, 4);
    state.removeVariant(3);
    assert.deepEqual(variants.map(variant => variant.id), [1, 2, 3]);
});

test('duplicating copies unsaved settings independently and clears identity, stock and reservations', async () => {
    const source = {
        id: 1, name: '10 Pack', sku: 'AAA-10', is_active: true, is_base: true, original_option: true,
        description: 'Current unsaved notes', product_details: [{ key: 'Colour', value: 'Black' }],
        price: '19.95', unit_cost_ex_tax: '0.25', inventory_units: 10,
        bulk_discount_percent: '15', automatic_pricing: true, weight_grams: 120,
        length_mm: 100, width_mm: 50, height_mm: 20, inventory_quantity: 7, low_stock_threshold: 2,
        awaiting_fulfilment: 3, reserved_quantity: 4, deletion_disabled_reason: 'Used by an order.',
        allow_backorder: true, backorder_shipping_estimate_type: 'dynamic', backorder_shipping_offset_days: 7,
        allocation: { cells: { 1: { amount: '2.50' } }, dirty: true },
    };
    let request;
    const state = editor([source, { id: 2, name: '25 Pack', is_active: true }], [], { copySkuUrl: '/copy-sku', csrf: 'test-csrf' }, async (url, options) => {
        request = { url, ...options, body: JSON.parse(options.body) };
        return { ok: true, json: async () => ({ sku: 'AAA-10-COPY' }) };
    });
    state.baseSku = 'AAA';
    const copy = await state.duplicateVariant(0);
    assert.equal(request.url, '/copy-sku');
    assert.equal(request.method, 'POST');
    assert.equal(request.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.deepEqual(request.body, { sku: 'AAA-10', reserved_skus: ['AAA', 'AAA-10', ''] });
    assert.equal(copy.id, null);
    assert.equal(copy.name, '10 Pack - COPY');
    assert.equal(copy.sku, 'AAA-10-COPY');
    assert.equal(copy.is_base, false);
    assert.equal(copy.original_option, false);
    assert.equal(copy.open, true);
    assert.notEqual(copy.editor_key, source.editor_key);
    assert.equal(copy.inventory_quantity, 0);
    assert.equal(copy.awaiting_fulfilment, 0);
    assert.equal(copy.reserved_quantity, 0);
    assert.equal(state.variantDeletionDisabledReason(copy), '');
    for (const field of ['description', 'price', 'unit_cost_ex_tax', 'inventory_units',
        'bulk_discount_percent', 'automatic_pricing', 'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'low_stock_threshold', 'allow_backorder', 'backorder_shipping_estimate_type', 'backorder_shipping_offset_days']) {
        assert.equal(copy[field], source[field], field);
    }
    copy.product_details[0].value = 'Red';
    copy.allocation.cells[1].amount = '9.00';
    assert.equal(source.product_details[0].value, 'Black');
    assert.equal(source.allocation.cells[1].amount, '2.50');
    assert.equal(source.inventory_quantity, 7);
    assert.equal(source.is_base, true);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.name), ['10 Pack', '10 Pack - COPY', '25 Pack']);
    assert.equal(state.variants[1].id, 2, 'existing form indices stay stable');
});

test('repeated duplication reserves unsaved SKUs and numbers case-insensitive title collisions', async () => {
    const requests = [];
    const state = editor([
        { id: 1, name: '10 Pack', sku: 'AAA-10', is_active: true, is_base: true },
        { id: 2, name: '10 pack - copy', sku: 'aaa-10-copy', is_active: false },
    ], [], {}, async (_url, options) => {
        const body = JSON.parse(options.body);
        requests.push(body);
        return { ok: true, json: async () => ({ sku: `AAA-10-COPY-${requests.length + 1}` }) };
    });
    const first = await state.duplicateVariant(0);
    const second = await state.duplicateVariant(0);
    assert.equal(first.name, '10 Pack - COPY-2');
    assert.equal(second.name, '10 Pack - COPY-3');
    assert.equal(second.sku, 'AAA-10-COPY-3');
    assert.equal(requests[1].reserved_skus.includes(first.sku), true);
    assert.notEqual(first.editor_key, second.editor_key);
});

test('copying an inactive untracked variant keeps it inactive without assigning a base', async () => {
    const state = editor([{ id: 1, name: 'Retired', sku: '', is_active: false, inventory_quantity: null }], [], {},
        async () => ({ ok: true, json: async () => ({ sku: 'variant-COPY' }) }));
    const copy = await state.duplicateVariant(0);
    assert.equal(copy.is_active, false);
    assert.equal(copy.is_base, false);
    assert.equal(copy.inventory_quantity, '');
    assert.equal(copy.sku, 'variant-COPY');
    assert.equal(state.variants.some(variant => variant.is_base), false);
});

test('copy names respect the field limit and blank names get a useful fallback', () => {
    const state = editor([{ name: '🧰'.repeat(120), is_active: true }]);
    const name = state.variantCopyName(state.variants[0], 0);
    assert.equal(Array.from(name).length, 120);
    assert.equal(name.endsWith(' - COPY'), true);
    state.variants.push({ name, is_active: true });
    const next = state.variantCopyName(state.variants[0], 0);
    assert.equal(Array.from(next).length, 120);
    assert.equal(next.endsWith(' - COPY-2'), true);
    assert.equal(state.variantCopyName({ name: ' ' }, 2), 'Variant 3 - COPY');
});

test('failed SKU checks leave the form intact, show feedback and permit a retry', async () => {
    let attempt = 0;
    const state = editor([{ id: 1, name: 'Pack', sku: 'PACK', is_active: true }], [], {}, async () => {
        attempt++;
        if (attempt === 1) return { ok: false };
        if (attempt === 2) throw new Error('Network unavailable');
        return { ok: true, json: async () => ({ sku: 'PACK-COPY' }) };
    });
    for (let index = 0; index < 2; index++) {
        assert.equal(await state.duplicateVariant(0), null);
        assert.equal(state.variants.length, 1);
        assert.match(state.variantCopyError, /try again/);
        assert.equal(state.duplicatingVariantKey, null);
    }
    assert.notEqual(await state.duplicateVariant(0), null);
    assert.equal(state.variantCopyError, '');
});

test('a pending copy blocks extra clicks and uses the captured settings', async () => {
    let resolve;
    const state = editor([{ id: 1, name: 'Pack', sku: 'PACK', price: '19.95', is_active: true }], [], {},
        () => new Promise(done => { resolve = done; }));
    const pending = state.duplicateVariant(0);
    assert.equal(state.duplicatingVariantKey, 'saved-1');
    assert.equal(await state.duplicateVariant(0), null);
    state.variants[0].price = '29.95';
    resolve({ ok: true, json: async () => ({ sku: 'PACK-COPY' }) });
    const copy = await pending;
    assert.equal(copy.price, '19.95');
    assert.equal(state.variants.length, 2);
});

test('a removed source or a newly conflicting SKU cannot add a stale copy', async () => {
    for (const change of ['remove', 'conflict']) {
        let resolve;
        const state = editor([{ id: 1, name: 'Pack', sku: 'PACK', is_active: true }, { id: 2, is_active: true }], [], {},
            () => new Promise(done => { resolve = done; }));
        const pending = state.duplicateVariant(1);
        if (change === 'remove') state.removeVariant(1);
        else state.variants[0].sku = 'pack-copy';
        resolve({ ok: true, json: async () => ({ sku: 'PACK-COPY' }) });
        assert.equal(await pending, null);
        assert.equal(state.variants.length, change === 'remove' ? 1 : 2);
        assert.equal(state.duplicatingVariantKey, null);
        if (change === 'conflict') assert.match(state.variantCopyError, /try again/);
    }
});
