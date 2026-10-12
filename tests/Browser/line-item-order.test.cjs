const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const context = { window: {} };
vm.runInNewContext(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'), context);

function editor(isLocked = false) {
    return {
        ...context.window.SM.lineItemsEditor(isLocked),
        lineItems: [
            { description: 'Materials', saved_line_index: 0, saved_pricing: { gross: 11 } },
            { kind: 'multi_workshop', description: '', saved_line_index: 1, saved_pricing: { gross: 330 },
                details_json: { multi_workshop: { rows: [{ description: 'Library workshop', workshop_hours: 1, workshop_seats: 12 }] } } },
            { description: 'Travel', saved_line_index: 2, saved_pricing: { gross: 34 } },
        ],
        savedOrders: [],
        serializeLineItems() { this.savedOrders.push(this.lineItems.map(item => item.saved_line_index)); },
    };
}

test('moving a line up and down carries workshop data and saved prices with it', () => {
    const state = editor();
    const group = state.lineItems[1];
    const snapshot = JSON.stringify(group);
    const key = state.lineItemKey(group);

    state.moveLineItem(1, -1);
    assert.equal(state.lineItems[0], group);
    assert.equal(state.lineItemKey(group), key);
    assert.equal(JSON.stringify(group), snapshot);
    assert.deepEqual(state.savedOrders[0], [1, 0, 2]);

    state.moveLineItem(0, 1);
    assert.equal(state.lineItems[1], group);
    assert.equal(state.lineItemKey(group), key);
    assert.equal(JSON.stringify(group), snapshot);
    assert.deepEqual(state.savedOrders[1], [0, 1, 2]);
    assert.equal(new Set(state.lineItems.map(item => state.lineItemKey(item))).size, 3);
});

test('moves outside the list or while locked do not change or serialize items', () => {
    for (const locked of [false, true]) {
        const state = editor(locked);
        const original = [...state.lineItems];
        for (const [index, direction] of [[0, -1], [2, 1], [-1, 1], [3, -1], [1, 0], [1, 2], ['1', -1]]) {
            state.moveLineItem(index, direction);
        }
        if (locked) state.moveLineItem(1, -1);
        assert.deepEqual(state.lineItems, original);
        assert.equal(state.savedOrders.length, 0);
    }
});

test('the mobile editor continues editing the same item when either neighbour moves', () => {
    const state = editor();
    const group = state.lineItems[1];
    state.mobileLineItemIndex = 1;
    state.moveLineItem(0, 1);
    assert.equal(state.mobileLineItemIndex, 0);
    assert.equal(state.lineItems[state.mobileLineItemIndex], group);
    state.moveLineItem(0, 1);
    assert.equal(state.mobileLineItemIndex, 1);
    assert.equal(state.lineItems[state.mobileLineItemIndex], group);
    state.moveLineItem(2, -1);
    assert.equal(state.mobileLineItemIndex, 2);
    assert.equal(state.lineItems[state.mobileLineItemIndex], group);
});
