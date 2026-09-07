const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
vm.runInNewContext(fs.readFileSync('resources/js/allocation-plan-preview.js', 'utf8').replace(/^import .*\n/, '').replace('export function', 'function'), context);
const plan = { travel_cents: 3400, rounding_step: 50, travel_rounding_step: 100, rules: [
    { category_id: 1, basis: 'workshop', rate_cents: 1000 },
    { category_id: 2, basis: 'hour', rate_cents: 6000, suppliable: true },
    { category_id: 3, basis: 'participant', rate_cents: 500 },
    { category_id: 4, basis: 'travel', rate_cents: 1900 },
] };
test('preview distinguishes invoice seat-hour rounding from ticket rounding', () => {
    const result = context.calculatePlanPreview(plan, 2, 10, 2);
    assert.equal(result.totalCost, 218);
    assert.equal(result.workshopGross, 200);
    assert.equal(result.travelGross, 42);
    assert.equal(result.invoiceGross, 242);
    assert.equal(result.ticket, 20);
    assert.equal(result.unitGross, 10);
});
test('supplied costs are excluded and zero travel stays zero', () => {
    const result = context.calculatePlanPreview(plan, 2, 10, 0, { 2: true });
    assert.equal(result.totalCost, 60);
    assert.equal(result.workshopGross, 70);
    assert.equal(result.travelGross, 0);
    assert.equal(result.ticket, 7);
});
test('invalid scenario or rate does not display a misleading total', () => {
    assert.equal(context.calculatePlanPreview(plan, 0, 10, 1), null);
    assert.equal(context.calculatePlanPreview({ ...plan, travel_cents: NaN }, 1, 10, 1), null);
});
