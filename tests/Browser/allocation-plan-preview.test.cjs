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
    assert.equal(result.costPerParticipant, 21.8);
    assert.equal(result.workshopGross, 200);
    assert.equal(result.travelGross, 42);
    assert.equal(result.invoiceGross, 242);
    assert.equal(result.ticket, 20);
    assert.equal(result.unitGross, 10);
});
test('supplied costs are excluded and zero travel stays zero', () => {
    const result = context.calculatePlanPreview(plan, 2, 10, 0, { 2: true });
    assert.equal(result.totalCost, 60);
    assert.equal(result.costPerParticipant, 6);
    assert.equal(result.workshopGross, 70);
    assert.equal(result.travelGross, 0);
    assert.equal(result.ticket, 7);
});

test('the hourly travel rate is available with zero travel without adding it to the quote', () => {
    const result = context.calculatePlanPreview({ ...plan, rounding_step: 0, travel_rounding_step: 0 }, 1, 10, 0);
    assert.equal(result.travelRate.suggested.unitEx, 76);
    assert.equal(result.travelRate.suggested.unitInc, 83.6);
    assert.equal(result.travelRounding, null);
    assert.equal(result.cleanTravelGross, 0);
    assert.equal(result.cleanInvoiceGross, 132);
});
test('invalid scenario or rate does not display a misleading total', () => {
    assert.equal(context.calculatePlanPreview(plan, 0, 10, 1), null);
    assert.equal(context.calculatePlanPreview({ ...plan, travel_cents: NaN }, 1, 10, 1), null);
    assert.equal(context.calculatePlanPreview({ ...plan, pricing_participants: 0 }, 1, 10, 1), null);
});

test('preview separates actual participant costs from the plan billing rate', () => {
    const referencePlan = { pricing_participants: 10, travel_cents: 0, rules: [
        { category_id: 1, basis: 'workshop', rate_cents: 8500 },
        { category_id: 2, basis: 'participant', rate_cents: 400 },
    ] };
    const ten = context.calculatePlanPreview(referencePlan, 1, 10, 0);
    const fifteen = context.calculatePlanPreview(referencePlan, 1, 15, 0);
    assert.equal(ten.costPerParticipant, 12.5);
    assert.equal(fifteen.totalCost, 145);
    assert.equal(fifteen.costPerParticipant, 145 / 15);
    assert.equal(ten.unitGross, 13.75);
    assert.equal(fifteen.unitGross, ten.unitGross);
    assert.equal(fifteen.cleanInvoiceGross, 206.25);
    assert.equal(fifteen.roundingNeeded, false);
    assert.equal(fifteen.pricingParticipants, 10);
});

test('plan preview explains cent adjustments and agrees with the invoice editor', () => {
    const result = context.calculatePlanPreview(plan, 2, 10, 2);
    assert.equal(result.roundingNeeded, true);
    assert.equal(result.workshopRounding.suggested.unitInc, 10.01);
    assert.equal(result.cleanWorkshopGross, 200.2);
    assert.equal(result.cleanTravelGross, 42.02);
    assert.equal(result.cleanInvoiceGross, 242.22);
    assert.equal(result.roundingIncrease, 0.22);
    const workshop = { kind: 'workshop', workshop_hours: 2, workshop_seats: 10, auto_pricing: true, gst_applicable: true };
    const travel = { kind: 'travel', travel_hours: 0.5, auto_pricing: true, gst_applicable: true };
    context.window.SM.updateWorkshopLine(workshop, { ...plan, clean_prices: true }, true);
    context.window.SM.updateWorkshopLine(travel, { ...plan, clean_prices: true }, true);
    assert.equal(context.window.SM.lineAmounts(workshop).gross, result.cleanWorkshopGross);
    assert.equal(context.window.SM.lineAmounts(travel).gross, result.cleanTravelGross);
});

test('unrounded plan calculations keep their precision until the clean rate is chosen', () => {
    const result = context.calculatePlanPreview({ rules: [{ basis: 'workshop', rate_cents: 1001 }], travel_cents: 0 }, 1, 10, 0);
    assert.ok(Math.abs(result.unitGross - 1.1011) < 0.0000001);
    assert.equal(result.workshopGross, 11.01);
    assert.equal(result.cleanInvoiceGross, 12.1);
});

test('a clean $12.50 cost stays clean with no increment and explains the $0.80 after a $0.50 increment', () => {
    const basePlan = { rules: [{ basis: 'participant', rate_cents: 1250 }], travel_cents: 0, rounding_step: 0 };
    const unrounded = context.calculatePlanPreview(basePlan, 1, 10, 0);
    assert.equal(unrounded.costPerParticipant, 12.5);
    assert.equal(unrounded.baseWorkshopRounding.clean, true);
    assert.equal(context.window.SM.formatUnitPrice(unrounded.baseWorkshopRounding.current.unitEx), '12.50');
    assert.equal(unrounded.baseWorkshopRounding.current.unitInc, 13.75);
    assert.equal(unrounded.roundingNeeded, false);
    assert.equal(unrounded.cleanInvoiceGross, 137.5);
    assert.equal(unrounded.totalRoundingIncrease, 0);
    const incremented = context.calculatePlanPreview({ ...basePlan, rounding_step: 50 }, 1, 10, 0);
    assert.equal(incremented.baseWorkshopRounding.clean, true);
    assert.equal(incremented.workshopRounding.clean, false);
    assert.equal(incremented.workshopRounding.current.unitInc, 14);
    assert.equal(incremented.workshopRounding.suggested.unitInc, 14.08);
    assert.equal(incremented.baseInvoiceGross, 137.5);
    assert.equal(incremented.planRoundingIncrease, 2.5);
    assert.equal(incremented.roundingIncrease, 0.8);
    assert.equal(incremented.totalRoundingIncrease, 3.3);
    assert.equal(incremented.cleanInvoiceGross, 140.8);
});

test('participant cost and billable seat-hour rate stay distinct for longer workshops', () => {
    const result = context.calculatePlanPreview({ rules: [{ basis: 'participant', rate_cents: 1250 }], travel_cents: 0 }, 2, 10, 0);
    assert.equal(result.costPerParticipant, 12.5);
    assert.equal(context.window.SM.formatUnitPrice(result.baseWorkshopRounding.current.unitEx), '6.25');
    assert.equal(result.baseWorkshopRounding.current.unitInc, 6.875);
    assert.equal(result.baseWorkshopRounding.clean, false);
    assert.equal(result.quantity, 20);
    assert.equal(result.roundingIncrease, 1.1);
    assert.equal(result.cleanInvoiceGross, 138.6);
});

test('cost allocations can be added and removed without losing another row or its submitted values', async () => {
    const { JSDOM } = require('jsdom');
    const source = fs.readFileSync('resources/views/admin/cost-centre/version-editor.blade.php', 'utf8');
    const initial = [
        { category_id: 1, basis: 'hour', rate_cents: 8500 },
        { category_id: 2, basis: 'participant', rate_cents: 400 },
        { category_id: 3, basis: 'travel', rate_cents: 1900 },
    ];
    const formTag = source.match(/<form\b(?:[^"'<>]|"[^"]*"|'[^']*')*>/)[0]
        .replace('@js($rules)', JSON.stringify(initial).replaceAll('"', '&quot;'));
    const rows = source.match(/<template x-for="\(rule, index\) in costRules"[\s\S]*?<\/template>/)[0]
        .replace(/@foreach[\s\S]*?@endforeach/, '<option value="1">Delivery</option><option value="2">Materials</option><option value="3">Travel</option>')
        .replaceAll('x-ui.select', 'select')
        .replace(/<x-ui\.money-input\b([^>]*?)\/>/g, '<input type="number" $1>')
        .replace(/<x-ui\.checkbox\b([^>]*?)\/>/g, '<input type="checkbox" $1>')
        .replace(/<x-ui\.row-action\b([^>]*?)\/>/g, '<button type="button" $1>Remove</button>');
    const dom = new JSDOM(`<!doctype html>${formTag}<table><tbody>${rows}</tbody></table>
        <input name="pricing_participants" value="10"><input name="preview_hours" value="1"><input name="preview_seats" value="10"><input name="preview_travel_units" value="0">
    </form>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    try {
        const settle = async () => { await window.Alpine.nextTick(); await new Promise(resolve => window.requestAnimationFrame(resolve)); };
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.js', 'utf8'));
        window.eval(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'));
        window.eval(fs.readFileSync('resources/js/allocation-plan-preview.js', 'utf8').replace(/^import .*\n/, '').replace('export function', 'function'));
        window.Alpine.start();
        await settle();
        const form = window.document.querySelector('form');
        const state = window.Alpine.$data(form);
        assert.equal(state.preview.cleanInvoiceGross, 137.5);
        form.querySelector('[data-cost-rule="1"] button').click();
        await settle();
        assert.deepEqual(Array.from(state.costRules, rule => rule.id), [0, 2]);
        assert.equal(form.elements.namedItem('rules[1][category_id]').value, '3');
        assert.equal(form.elements.namedItem('rules[1][basis]').value, 'travel');
        assert.equal(form.elements.namedItem('rules[1][rate]').value, '19.00');
        assert.equal(state.preview.cleanInvoiceGross, 93.5);
        state.addCostRule(form);
        await settle();
        assert.equal(window.document.activeElement, form.elements.namedItem('rules[2][category_id]'));
        for (const [name, value] of [['category_id', '2'], ['basis', 'participant'], ['rate', '4']]) {
            const input = form.elements.namedItem(`rules[2][${name}]`);
            input.value = value;
            input.dispatchEvent(new window.Event(input.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
        }
        await settle();
        assert.equal(state.preview.cleanInvoiceGross, 137.5);
        const submitted = new window.FormData(form);
        assert.equal(submitted.get('rules[1][rate]'), '19.00');
        assert.equal(submitted.get('rules[2][basis]'), 'participant');
        assert.deepEqual(Array.from(state.costRules, rule => rule.id), [0, 2, 3]);
        state.removeCostRule(0, form);
        state.removeCostRule(2, form);
        state.removeCostRule(3, form);
        await settle();
        assert.deepEqual(Array.from(state.costRules, rule => rule.id), [3]);
    } finally { window.Alpine.stopObservingMutations(); window.close(); }
});

test('cost per participant updates while typing without leaving the field', async () => {
    const { JSDOM } = require('jsdom');
    const source = fs.readFileSync('resources/views/admin/cost-centre/version-editor.blade.php', 'utf8');
    const formTag = source.match(/<form\b(?:[^"'<>]|"[^"]*"|'[^']*')*>/)[0].replace('@js($rules)', '[]');
    const dom = new JSDOM(`<!doctype html>${formTag}
        <input name="rules[0][category_id]" value="1"><input name="rules[0][basis]" value="workshop"><input name="rules[0][rate]" value="25.5">
        <input name="rules[1][category_id]" value="2"><input name="rules[1][basis]" value="hour"><input name="rules[1][rate]" value="105">
        <input name="rules[2][category_id]" value="3"><input name="rules[2][basis]" value="participant"><input name="rules[2][rate]" value="4.5">
        <input name="rules[3][category_id]" value="4"><input name="rules[3][basis]" value="travel"><input name="rules[3][rate]" value="34">
        <input name="travel_price" value="37.40"><input name="rounding_step" value="50"><input name="travel_rounding_step" value="0">
        <input name="pricing_participants" value="10"><input name="preview_hours" value="1"><input name="preview_seats" value="10"><input name="preview_travel_units" value="0">
        <output id="per-participant" x-text="money(preview?.costPerParticipant)"></output>
    </form>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    try {
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.js', 'utf8'));
        window.eval(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'));
        window.eval(fs.readFileSync('resources/js/allocation-plan-preview.js', 'utf8').replace(/^import .*\n/, '').replace('export function', 'function'));
        window.Alpine.start();
        await window.Alpine.nextTick();
        const output = window.document.getElementById('per-participant');
        assert.equal(output.textContent, '$17.55');
        for (const [name, value, expected] of [
            ['preview_seats', '20', '$11.03'],
            ['preview_hours', '2', '$16.28'],
            ['rules[1][rate]', '115', '$17.28'],
            ['preview_travel_units', '0.5', '$20.68'],
        ]) {
            const input = window.document.querySelector(`[name="${name}"]`);
            input.focus();
            input.value = value;
            input.dispatchEvent(new window.Event('input', { bubbles: true }));
            await window.Alpine.nextTick();
            assert.equal(window.document.activeElement, input);
            assert.equal(output.textContent, expected);
        }
        const form = window.document.querySelector('form');
        for (const [name, value] of [['preview_hours', '1'], ['preview_seats', '10'], ['preview_travel_units', '0'], ['rules[1][rate]', '54.5']]) {
            form.elements.namedItem(name).value = value;
        }
        const state = window.Alpine.$data(form);
        state.refresh(form);
        await window.Alpine.nextTick();
        assert.equal(form.elements.namedItem('rounding_step').value, '50');
        assert.equal(state.preview.planRoundingIncrease, 0);
        assert.equal(state.preview.cleanInvoiceGross, 137.5);
        assert.equal(state.preview.roundingNeeded, false);
        assert.equal(output.textContent, '$12.50');
        const baseline = form.elements.namedItem('pricing_participants');
        baseline.value = '20';
        baseline.dispatchEvent(new window.Event('input', { bubbles: true }));
        await window.Alpine.nextTick();
        assert.equal(state.preview.pricingParticipants, 20);
        assert.equal(state.preview.unitGross, 9.35);
        assert.equal(state.preview.cleanInvoiceGross, 93.5);
        assert.equal(output.textContent, '$12.50');
    } finally { window.close(); }
});
