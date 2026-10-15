const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function editor(variants, variantErrors = []) {
    const context = { window: { SM: {} } };
    vm.runInNewContext(readFileSync('resources/js/product-variant-editor.js', 'utf8'), context);
    const state = { ...context.window.SM.productVariantEditor(), variants, variantErrors };
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

test('changing base and activity updates display order while retaining each variant data and expansion state', () => {
    const variants = [
        { id: 1, is_active: true, is_base: true, sort_order: 0, sku: 'ONE' },
        { id: 2, is_active: true, sort_order: 1, sku: 'TWO', allocation: { amount: 9 } },
        { id: 3, is_active: false, sort_order: 2, sku: 'THREE' },
    ];
    const state = editor(variants);
    variants[1].open = true;
    state.selectBaseVariant(variants[1]);
    assert.deepEqual(Array.from(state.orderedVariantRows(), row => row.variant.sku), ['TWO', 'ONE', 'THREE']);
    variants[1].is_active = false;
    state.variantActiveChanged(variants[1]);
    assert.equal(variants[0].is_base, true);
    assert.equal(variants[1].open, true);
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
