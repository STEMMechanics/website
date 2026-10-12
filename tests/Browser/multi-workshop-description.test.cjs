const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

for (const document of ['quote', 'invoice']) {
    test(`${document} serialization retains multi-workshop entries with blank text and zero amounts`, () => {
        const context = { window: {} };
        vm.createContext(context);
        vm.runInContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
        context.SM = context.window.SM;
        vm.runInContext(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'), context);
        const blade = fs.readFileSync(`resources/views/admin/${document}/edit.blade.php`, 'utf8');
        const start = blade.indexOf(document === 'quote' ? '                preparedItem(item) {' : '                serializeLineItems() {');
        const end = blade.indexOf(document === 'quote' ? '                removeLineItem(index) {' : '                addLineItem() {', start);
        assert.notEqual(start, -1);
        assert.notEqual(end, -1);
        const methods = vm.runInContext(`({${blade.slice(start, end)}})`, context);
        const editor = {
            ...context.SM.productLineEditor([]),
            ...methods,
            $refs: { lineItemsJson: { value: '' } },
            $dispatch() {},
            subtotalAmountFormatted() { return '0.00'; },
            lineItems: [{
                kind: 'multi_workshop', description: '', notes: '', quantity: 0, unit_price_inc_tax: 0,
                details_json: { multi_workshop: { quantity_basis: 'seat_hours', rows: [
                    { description: 'Library workshop', workshop_hours: 1, workshop_seats: 0 },
                ] } },
            }],
        };

        editor.serializeLineItems();
        const saved = JSON.parse(editor.$refs.lineItemsJson.value);
        assert.equal(saved.length, 1);
        assert.equal(saved[0].description, '');
        assert.equal(saved[0].quantity, 0);
        assert.equal(saved[0].details_json.multi_workshop.rows[0].description, 'Library workshop');
        assert.equal(editor.lineItems.length, 1);
    });
}
