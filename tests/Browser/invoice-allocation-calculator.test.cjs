const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const dialog = { showModal() {}, close() {} };
    const context = { attachDraggableDialog: () => () => {}, window: {}, document: { getElementById: () => dialog } };
    for (const file of ['allocation-tally', 'workshop-line', 'invoice-allocation-calculator']) {
        vm.runInNewContext(fs.readFileSync(`resources/js/${file}.js`, 'utf8').replace(/^import .*;$/m, '').replace('export function', 'function'), context);
    }
    return context;
}
const plan = { id: 1, participants: 10, rules: [
    { category_id: 1, basis: 'workshop', rate_cents: 1000 },
    { category_id: 2, basis: 'hour', rate_cents: 2000 },
    { category_id: 3, basis: 'participant', rate_cents: 100 },
    { category_id: 4, basis: 'travel', rate_cents: 500, suppliable: true },
    { category_id: 5, basis: 'venue_hour', rate_cents: 3000 },
] };
test('multiple groups calculate per-workshop costs, delivery, participants and billable travel in cents', () => {
    const ctx = setup();
    const result = ctx.calculateAllocationRows(plan, [
        { count: 3, hours: 1, participants: 10, travel: 0.5, supplied: {} },
        { count: 1, hours: 2, participants: 5, travel: 1, supplied: { 5: true } },
    ]);
    assert.deepEqual(JSON.parse(JSON.stringify(result)), { 1: 4000, 2: 10000, 3: 3500, 4: 5000, 5: 9000 });
});
test('supplied travel and legacy flat rules are respected', () => {
    const ctx = setup();
    const result = ctx.calculateAllocationRows({ rules: [...plan.rules, { category_id: 6, basis: 'flat', rate_cents: 125 }] }, [
        { count: 2, hours: 1, participants: 10, travel: 2, supplied: { 4: true, 5: true } },
    ]);
    assert.equal(result[4] || 0, 0);
    assert.equal(result[5], 0);
    assert.equal(result[6], 250);
});
test('invalid inputs and excessive results cannot be applied', () => {
    const ctx = setup();
    const valid = { count: 1, hours: 1, participants: 10, travel: 0, supplied: {} };
    for (const change of [{ count: 0 }, { count: 1.5 }, { hours: '' }, { hours: 0.001 }, { hours: Infinity }, { participants: -1 }, { travel: 0.1 }, { travel: -1 }]) {
        assert.equal(ctx.calculateAllocationRows(plan, [{ ...valid, ...change }]), null);
    }
    assert.equal(ctx.calculateAllocationRows(null, [valid]), null);
    assert.equal(ctx.calculateAllocationRows({ rules: [{ category_id: 1, basis: 'workshop', rate_cents: 1000000001 }] }, [valid]), null);
});
test('manual calculator adjustments apply as a copy and do not change the invoice until Apply', () => {
    const ctx = setup();
    const editor = ctx.window.SM.allocationTally({ values: { 1: '5.00' }, total: 10000, enabled: false });
    const calculator = ctx.window.SM.invoiceAllocationCalculator({ invoiceId: 'one', dialogId: 'calculator', plans: [{ id: 1, rules: [plan.rules[0]] }], planId: 1, categories: [{ id: 1, name: 'Cost' }] });
    calculator.$dispatch = (name, detail) => { assert.equal(name, 'allocation-calculated'); editor.applyCalculatorValues(detail); };
    calculator.launch(10000);
    assert.equal(calculator.values[1], '10.00');
    calculator.values[1] = '12.34';
    assert.equal(editor.values[1], '5.00');
    calculator.apply();
    assert.equal(editor.values[1], '12.34');
    assert.equal(editor.enabled, true);
    calculator.values[1] = '99.00';
    assert.equal(editor.values[1], '12.34');
});
test('minimise releases the dialog and restores the draft against the current invoice total', () => {
    const ctx = setup();
    const dialog = ctx.document.getElementById('calculator');
    let closed, focused = false;
    dialog.addEventListener = (name, callback) => { closed = callback; };
    dialog.removeEventListener = () => {};
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; closed(); };
    const calculator = ctx.window.SM.invoiceAllocationCalculator({ dialogId: 'calculator', plans: [plan], planId: 1, categories: [{ id: 1, name: 'Cost' }] });
    calculator.$nextTick = callback => callback();
    calculator.$refs = { restoreCalculator: { focus() { focused = true; } } };
    calculator.$dispatch = () => assert.fail('Minimise and restore must not apply the draft');
    calculator.init();
    calculator.launch(10000);
    calculator.addRow();
    calculator.rows[1].participants = 25;
    calculator.rows[0].supplied[5] = true;
    calculator.values[1] = '12.34';
    calculator.minimise();
    assert.equal(dialog.open, false);
    assert.equal(calculator.isOpen, false);
    assert.equal(calculator.minimised, true);
    assert.equal(focused, true);
    calculator.launch(20000);
    assert.equal(dialog.open, true);
    assert.equal(calculator.minimised, false);
    assert.equal(calculator.total, 20000);
    assert.equal(calculator.rows.length, 2);
    assert.equal(calculator.rows[1].participants, 25);
    assert.equal(calculator.rows[0].supplied[5], true);
    assert.equal(calculator.values[1], '12.34');
    // A delayed native close event must not disable an already-restored dialog.
    closed();
    assert.equal(calculator.isOpen, true);
    dialog.close();
    assert.equal(calculator.isOpen, false);
    assert.equal(calculator.minimised, false);
});
