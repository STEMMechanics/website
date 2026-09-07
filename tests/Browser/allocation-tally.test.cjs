const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup(config, inputs = {}) {
    const listeners = {}, alerts = [];
    const context = { window: { SM: { banner: (...args) => alerts.push(args), lineCostAllocations: () => ({ 1: 3400 }) } }, document: { getElementById: id => inputs[id] } };
    vm.runInNewContext(fs.readFileSync('resources/js/allocation-tally.js', 'utf8').replace('export function', 'function'), context);
    const tally = context.allocationTally(config);
    tally.$el = { closest: () => ({ addEventListener: (name, fn) => listeners[name] = fn, removeEventListener() {} }) };
    tally.init();
    return { tally, listeners, alerts, sm: context.window.SM };
}
test('decimal allocations tally in cents and block mismatched submission', () => {
    const { tally, listeners, alerts } = setup({ values: { 1: '0.1', 2: '0.2' }, total: 30, exact: true });
    assert.equal(tally.remaining, 0);
    assert.equal(tally.valid, true);
    tally.format(1);
    assert.equal(tally.values[1], '0.10');
    tally.values[2] = '0.19';
    let prevented = false;
    listeners.submit({ preventDefault() { prevented = true; }, stopImmediatePropagation() {} });
    assert.equal(prevented, true);
    assert.equal(alerts.length, 1);
});
test('supplier defaults follow the net amount while overrides retain entered values', () => {
    const inputs = { total: { value: '110' }, gst: { value: '10' }, supplier: { value: ' Vendor ' } };
    const { tally } = setup({ values: { 1: '0', 2: '0' }, enabled: false, exact: true, totalInput: 'total', gstInput: 'gst', supplierInput: 'supplier', defaults: { vendor: { 2: 100 } } }, inputs);
    assert.equal(tally.values[2], '100.00');
    tally.enabled = true;
    inputs.total.value = '220';
    inputs.gst.value = '20';
    tally.refreshTotal();
    assert.equal(tally.remaining, 10000);
    assert.equal(tally.values[2], '100.00');
    assert.equal(tally.valid, false);
    tally.enabled = false;
    tally.refreshDefaults();
    assert.equal(tally.remaining, 0);
});

test('invoice repricing refreshes remaining immediately without overwriting manual allocations', () => {
    const { tally } = setup({ values: { 1: '34.00' }, total: 3091, enabled: false, exact: false });
    tally.previewInvoice({ items: [], total: 3091 }, []);
    assert.equal(tally.remaining, -309);
    tally.previewInvoice({ items: [], total: 3409 }, []);
    assert.equal(tally.remaining, 9);
    assert.equal(tally.previewDirty, true);
    tally.enabled = true;
    tally.values[1] = '20.00';
    tally.previewInvoice({ items: [], total: 4000 }, []);
    assert.equal(tally.values[1], '20.00');
    assert.equal(tally.remaining, 2000);
});

test('automatic rounding fills its destination only up to the plan allowance', () => {
    const { tally, sm } = setup({ values: { 1: '34.00', 2: '0.00' }, total: 3409, enabled: false, exact: false });
    sm.invoiceRoundingAllowance = () => 9;
    tally.previewInvoice({ items: [], total: 3409 }, [], { rounding_category_id: 2 });
    assert.equal(tally.values[2], '0.09');
    assert.equal(tally.remaining, 0);
    tally.previewInvoice({ items: [], total: 3500 }, [], { rounding_category_id: 2 });
    assert.equal(tally.remaining, 91);
    tally.enabled = true;
    tally.values[2] = '1.00';
    tally.previewInvoice({ items: [], total: 3500 }, [], { rounding_category_id: 2 });
    assert.equal(tally.values[2], '1.00');
});
