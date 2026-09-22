const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const handlers = {}, inputs = [], workshops = [], window = {SM: {}};
    let submissions = 0;
    const allocation = {dataset: {allocationChanged: '1'}, entries: [['budget_id', '4'], ['targets[2]', '35.00'], ['_token', 'token']], matches: () => true};
    const invoice = {
        requestSubmit() { submissions++; },
        querySelectorAll: () => [...inputs],
        appendChild(input) { inputs.push(input); },
    };
    vm.runInNewContext(fs.readFileSync('resources/js/invoice-allocation-editor.js', 'utf8'), {
        window,
        document: {
            addEventListener: (name, handler) => handlers[name] = handler,
            getElementById: () => invoice,
            querySelector: () => allocation,
            querySelectorAll: () => workshops,
            createElement: () => { const input = {dataset: {}, remove() { inputs.splice(inputs.indexOf(input), 1); }}; return input; },
        },
        FormData: class { constructor(form) { return form.entries; } },
    });
    return {handlers, allocation, invoice, inputs, workshops, workspace: window.SM.invoiceAllocationWorkspace, attach: window.SM.attachInvoiceAllocation, submissions: () => submissions};
}
test('enter in allocation editor submits the main invoice form', async () => {
    const state = setup(); let prevented = false;
    await state.handlers.submit({target: state.allocation, preventDefault() { prevented = true; }});
    assert.equal(prevented, true);
    assert.equal(state.submissions(), 1);
});
test('main save includes changed allocation fields once, with nested names', () => {
    const state = setup();
    state.attach(state.invoice); state.attach(state.invoice);
    assert.deepEqual(state.inputs.map(input => [input.name, input.value]), [['allocation[budget_id]', '4'], ['allocation[targets][2]', '35.00']]);
});
test('untouched allocation is left to automatic invoice allocation sync', () => {
    const state = setup(); state.allocation.dataset.allocationChanged = '0';
    state.attach(state.invoice);
    assert.equal(state.inputs.length, 0);
});


test('invoice save includes workshop plans even when there is no changed standalone allocation', () => {
    const state = setup(); state.allocation.dataset.allocationChanged = '0';
    state.workshops.push({dataset: {workshopAllocation: 'straw', allocationChanged: '1'}, entries: [['source_hash', 'abc'], ['override', '1'], ['targets[2]', '80.00'], ['_token', 'token']]});
    state.attach(state.invoice);
    assert.deepEqual(state.inputs.map(input => [input.name, input.value]), [['workshop_allocations[straw][source_hash]', 'abc'], ['workshop_allocations[straw][override]', '1'], ['workshop_allocations[straw][targets][2]', '80.00']]);
});

test('combined totals update by cost centre without counting workshop plans twice', () => {
    const state = setup();
    const workspace = state.workspace({plans: {invoice: {1: '25.00'}, straw: {1: '50.00', 2: '10.00'}}, income: 10000, scope: 'overview'});
    assert.equal(workspace.total, 8500);
    workspace.updatePlan({key: 'straw', values: {1: '60.00', 2: '10.00'}});
    assert.equal(workspace.categoryTotal(1), 8500);
    assert.equal(workspace.total, 9500);
    workspace.scope = 'straw';
    assert.equal(workspace.planTotal('invoice'), 2500);
});

test('inspection marker remains for saved plans exceeding funding and follows live edits', () => {
    const state = setup();
    const workspace = state.workspace({plans: {workshop: {}}, income: 10000});
    workspace.updatePlan({key: 'workshop', values: {1: '120.00'}, funding: 10000, inspect: false});
    assert.equal(workspace.needsInspection('workshop'), true);
    assert.equal(workspace.inspectionReason('workshop'), 'Needs inspection: allocation exceeds funding');
    workspace.updatePlan({key: 'workshop', values: {1: '90.00'}, funding: 10000, inspect: false});
    assert.equal(workspace.needsInspection('workshop'), true);
    assert.equal(workspace.inspectionReason('workshop'), 'Needs inspection: funding remains unallocated');
    workspace.updatePlan({key: 'workshop', values: {1: '100.00'}, funding: 10000, inspect: false});
    assert.equal(workspace.needsInspection('workshop'), false);
    workspace.updatePlan({key: 'workshop', values: {1: '100.00'}, funding: 10000, inspect: true});
    assert.equal(workspace.needsInspection('workshop'), true);
});

test('invoice items shortfall marks its own tab even when workshops cover the overall total', () => {
    const state = setup();
    const workspace = state.workspace({plans: {invoice: {}, workshop: {1: '50.00'}}, income: 20000});
    workspace.updatePlan({key: 'invoice', values: {1: '60.00'}, funding: 5000});
    assert.equal(workspace.needsInspection('invoice'), true);
    assert.equal(workspace.total < workspace.income, true);
    workspace.updatePlan({key: 'invoice', values: {1: '50.00'}, funding: 5000});
    assert.equal(workspace.needsInspection('invoice'), false);
});

test('balanced workshop edits clear the issue marker before saving', () => {
    const state = setup();
    const workspace = state.workspace({plans: {workshop: {}}, income: 11818});
    workspace.updatePlan({key: 'workshop', values: {1: '147.50'}, funding: 11818, inspect: false});
    assert.equal(workspace.needsInspection('workshop'), true);
    workspace.updatePlan({key: 'workshop', values: {1: '69.18', 2: '15.00', 3: '28.00', 4: '6.00'}, funding: 11818, inspect: false});
    assert.equal(workspace.needsInspection('workshop'), false);
});

test('invoice items with an unallocated cent need inspection until balanced', () => {
    const workspace = setup().workspace({plans: {invoice: {}}, income: 10000});
    workspace.updatePlan({key: 'invoice', values: {1: '99.99'}, funding: 10000});
    assert.equal(workspace.needsInspection('invoice'), true);
    assert.equal(workspace.inspectionReason('invoice'), 'Needs inspection: funding remains unallocated');
    workspace.updatePlan({key: 'invoice', values: {1: '99.99', 2: '0.01'}, funding: 10000});
    assert.equal(workspace.needsInspection('invoice'), false);
});
