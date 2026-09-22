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

function expenseGstUpdater(inputs, tally) {
    const source = fs.readFileSync('resources/views/admin/expense/edit.blade.php', 'utf8');
    const updater = source.slice(source.indexOf('const updateGstFromTotal ='), source.indexOf('const resetPreviewVisibility ='));
    inputs.gst.dispatchEvent = event => {
        assert.equal(event.type, 'input');
        assert.equal(event.bubbles, true);
        tally.refreshTotal({ target: { id: 'gst' } });
    };
    return vm.runInNewContext(`${updater}\nupdateGstFromTotal;`, { totalInput: inputs.total, gstInput: inputs.gst, Event });
}

test('automatically calculated GST refreshes the net expense and supplier allocation', () => {
    const inputs = { total: { value: '12.15' }, gst: { value: '' }, supplier: { value: 'Vendor' } };
    const { tally } = setup({ values: { 5: '0' }, enabled: false, exact: true, totalInput: 'total', gstInput: 'gst', supplierInput: 'supplier', defaults: { vendor: { 5: 100 } } }, inputs);
    const updateGst = expenseGstUpdater(inputs, tally);
    updateGst();
    assert.equal(inputs.gst.value, '1.10');
    assert.equal(tally.total, 1105);
    assert.equal(tally.values[5], '11.05');
    assert.equal(tally.remaining, 0);

    inputs.total.value = '';
    updateGst();
    assert.equal(inputs.gst.value, '');
    assert.equal(tally.total, 0);
    assert.equal(tally.values[5], '0.00');
});

test('automatic GST refresh preserves manual allocations and flags the excess', () => {
    const inputs = { total: { value: '12.15' }, gst: { value: '' } };
    const { tally } = setup({ values: { 5: '12.15' }, enabled: true, exact: true, totalInput: 'total', gstInput: 'gst' }, inputs);
    expenseGstUpdater(inputs, tally)();
    assert.equal(tally.total, 1105);
    assert.equal(tally.values[5], '12.15');
    assert.equal(tally.remaining, -110);
    assert.equal(tally.valid, false);
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

test('remaining button adds the exact balance to an existing amount and is safe to repeat', () => {
    const { tally } = setup({ values: { 1: '10.01', 2: '20.00' }, total: 10001, exact: true });
    tally.allocateRemaining('1');
    assert.equal(tally.values[1], '80.01');
    assert.equal(tally.remaining, 0);
    tally.allocateRemaining('1');
    assert.equal(tally.values[1], '80.01');
    tally.total = 9000;
    tally.allocateRemaining('1');
    assert.equal(tally.values[1], '80.01');
});

test('remaining button respects automatic mode and ignores unknown fields', () => {
    const { tally } = setup({ values: { 1: '10.00' }, total: 10000, enabled: false });
    tally.allocateRemaining('1');
    assert.equal(tally.values[1], '10.00');
    tally.enabled = true;
    tally.allocateRemaining('2');
    assert.equal(tally.values[2], undefined);
});


test('percentage fill respects the override checkbox and fills exactly to 100 percent', () => {
    const { tally } = setup({ values: { 1: '33.33', 2: '0.00' }, total: 10000, exact: true, enabled: false });
    tally.allocateRemaining('2');
    assert.equal(tally.values[2], '0.00');
    tally.enabled = true;
    assert.equal(tally.valid, false);
    tally.allocateRemaining('2');
    assert.equal(tally.values[2], '66.67');
    assert.equal(tally.allocated, 10000);
    assert.equal(tally.valid, true);
});


test('workshop supplied choices recalculate defaults and remain effective when leaving manual override', () => {
    const { tally } = setup({ values: { 1: '0.00', 2: '5.00' }, enabled: false, total: 4000,
        workshopDefaults: { selected: { 1: true }, supplied: { 1: 0, 2: 500 }, notSupplied: { 1: 3000, 2: 500 } } });
    tally.supplied[1] = false;
    tally.refreshWorkshopDefaults();
    assert.equal(tally.values[1], '30.00');
    assert.equal(tally.remaining, 500);
    tally.enabled = true;
    tally.values[1] = '12.00';
    tally.supplied[1] = true;
    tally.refreshWorkshopDefaults();
    assert.equal(tally.values[1], '12.00');
    tally.enabled = false;
    tally.refreshWorkshopDefaults();
    assert.equal(tally.values[1], '0.00');
    assert.equal(tally.values[2], '5.00');
    assert.equal(tally.remaining, 3500);
});


test('allocation changes compare amounts in cents and reset when edits are reverted', () => {
    const { tally } = setup({ values: { 1: '30.00' }, enabled: true });
    assert.equal(tally.allocationChanged, false);
    tally.values[1] = '30';
    assert.equal(tally.allocationChanged, false);
    tally.values[1] = '30.01';
    assert.equal(tally.allocationChanged, true);
    tally.values[1] = '30.00';
    assert.equal(tally.allocationChanged, false);
});

test('supplied choices and override mode count as changes even when the amounts stay the same', () => {
    const { tally } = setup({ values: { 1: '0.00' }, enabled: false,
        workshopDefaults: { selected: { 1: false }, supplied: { 1: 0 }, notSupplied: { 1: 0 } } });
    tally.supplied[1] = true;
    assert.equal(tally.allocationChanged, true);
    tally.supplied[1] = false;
    assert.equal(tally.allocationChanged, false);
    tally.enabled = true;
    assert.equal(tally.allocationChanged, true);
    tally.enabled = false;
    tally.refreshWorkshopDefaults();
    assert.equal(tally.allocationChanged, false);
});

test('invoice preview excludes linked workshop receipts and costs', () => {
    const { tally, sm } = setup({ values: { 1: '0' }, enabled: false, total: 50000 });
    const linked = { kind: 'workshop', details_json: { workshop: { linked_workshop_id: 'session' } } };
    const travel = { kind: 'travel' };
    sm.lineAmounts = item => ({ net: item === linked ? 400 : 100 });
    sm.lineCostAllocations = items => { assert.deepEqual(Array.from(items), [travel]); return { 1: 10000 }; };
    tally.previewInvoice({ total: 50000, items: [linked, travel] }, []);
    assert.equal(tally.total, 10000);
    assert.equal(tally.values[1], '100.00');
});

test('workshop seat previews update defaults including zero seats and preserve manual overrides', () => {
    const { tally } = setup({ values: { 1: '20.00', 2: '30.00' }, enabled: false,
        workshopId: 'one', workshopInputs: { participants: 10, hours: 2 },
        workshopDefaults: { selected: {}, supplied: {}, notSupplied: {} },
        workshopRules: [{ category_id: 1, basis: 'participant', rate_cents: 200 }, { category_id: 2, basis: 'hour', rate_cents: 1500 }] });
    tally.previewWorkshop({ id: 'other', participants: 6, hours: 2 });
    assert.equal(tally.values[1], '20.00');
    tally.previewWorkshop({ id: 'one', participants: 6, hours: 2 });
    assert.equal(tally.values[1], '12.00');
    assert.equal(tally.values[2], '30.00');
    assert.equal(tally.allocationChanged, true);
    tally.previewWorkshop({ id: 'one', participants: 0, hours: 3 });
    assert.equal(tally.values[1], '0.00');
    assert.equal(tally.values[2], '45.00');
    tally.enabled = true;
    tally.values[1] = '99.00';
    tally.previewWorkshop({ id: 'one', participants: 8, hours: 2 });
    assert.equal(tally.values[1], '99.00');
    tally.enabled = false;
    tally.refreshWorkshopDefaults();
    assert.equal(tally.values[1], '16.00');
});

test('linked funding preview follows single and grouped invoice amounts without counting manual rows', () => {
    const { tally, sm } = setup({ workshopId: 'one', received: 0 });
    sm.lineAmounts = item => ({ net: item.net });
    tally.previewWorkshopFunding({ items: [{ kind: 'workshop', net: 160, details_json: { workshop: { linked_workshop_id: 'one' } } }] });
    assert.equal(tally.total, 16000);
    tally.previewWorkshopFunding({ items: [{ kind: 'multi_workshop', net: 300, workshops: [
        { workshop_hours: 2, workshop_seats: 5, details_json: { workshop: { linked_workshop_id: 'one' } } },
        { workshop_hours: 2, workshop_seats: 10 },
    ] }] });
    assert.equal(tally.total, 10000);
});
