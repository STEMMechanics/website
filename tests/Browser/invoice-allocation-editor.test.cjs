const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const handlers = {}, inputs = [], window = {SM: {}};
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
            createElement: () => { const input = {dataset: {}, remove() { inputs.splice(inputs.indexOf(input), 1); }}; return input; },
        },
        FormData: class { constructor(form) { return form.entries; } },
    });
    return {handlers, allocation, invoice, inputs, attach: window.SM.attachInvoiceAllocation, submissions: () => submissions};
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
