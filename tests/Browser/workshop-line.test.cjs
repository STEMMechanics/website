const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
vm.runInNewContext(fs.readFileSync('public/workshop-course.js', 'utf8'), context);
const update = context.updateWorkshopLine;
test('hours and seats calculate quantity without losing other metadata', () => {
    const item = { kind: 'workshop', workshop_hours: '2', workshop_seats: '15', venue_supplied: false, details_json: { reference: 'keep' } };
    update(item);
    assert.equal(item.quantity, 30);
    assert.equal(item.details_json.workshop.hours, 2);
    assert.equal(item.details_json.workshop.seats, 15);
    assert.equal(item.details_json.workshop.venue_supplied, false);
    assert.equal(item.details_json.reference, 'keep');
    item.workshop_hours = '1.5';
    update(item);
    assert.equal(item.quantity, 22.5);
});
test('legacy quantities are not inferred and travel uses explicit charged units', () => {
    const legacy = { kind: 'workshop', quantity: 30, workshop_hours: '', workshop_seats: '' };
    update(legacy);
    assert.equal(legacy.quantity, 30);
    assert.equal(legacy.details_json, undefined);
    const travel = { kind: 'travel', quantity: 8, travel_units: '3' };
    update(travel);
    assert.equal(travel.quantity, 3);
    assert.equal(travel.details_json.travel.billable_units, 3);
});

test('plan price counts fixed costs once and participant costs once per seat', () => {
    const plan = { rules: [{ basis: 'workshop', rate_cents: 1000 }, { basis: 'hour', rate_cents: 6000 }, { basis: 'participant', rate_cents: 500 }, { basis: 'venue_hour', rate_cents: 4500 }] };
    const item = { kind: 'workshop', auto_pricing: true, workshop_hours: 2, workshop_seats: 10, venue_supplied: true, gst_applicable: true };
    update(item, plan);
    assert.equal(item.quantity, 20);
    assert.equal(item.unit_price, '9.00');
    item.venue_supplied = false;
    update(item, plan);
    assert.equal(item.unit_price, '13.50');
    update(item, plan, true);
    assert.equal(item.unit_price_inc_tax, '14.85');
});
test('saved prices and manual overrides are preserved', () => {
    const item = { kind: 'workshop', workshop_hours: 2, workshop_seats: 10, unit_price: '25.00' };
    update(item, { rules: [{ basis: 'hour', rate_cents: 6000 }] });
    assert.equal(item.unit_price, '25.00');
    item.auto_pricing = false;
    update(item, { rules: [{ basis: 'hour', rate_cents: 6000 }] });
    assert.equal(item.unit_price, '25.00');
});
test('travel price respects invoice versus quote tax basis and free-of-GST lines', () => {
    const item = { kind: 'travel', auto_pricing: true, travel_units: 2, gst_applicable: true };
    update(item, { travel_cents: 3400 });
    assert.equal(item.unit_price, '30.91');
    update(item, { travel_cents: 3400 }, true);
    assert.equal(item.unit_price_inc_tax, '34.00');
    item.gst_applicable = false;
    update(item, { travel_cents: 3400 }, false);
    assert.equal(item.unit_price, '34.00');
});

test('supplied categories reduce only suppliable costs and refresh controls are independent', () => {
    const plan = { rules: [{ category_id: 1, basis: 'hour', rate_cents: 6000 }, { category_id: 2, basis: 'participant', rate_cents: 500, suppliable: true }] };
    const item = { kind: 'workshop', workshop_hours: 2, workshop_seats: 10, supplied_categories: { 1: true, 2: true }, unit_price: '99.00' };
    context.window.SM.registerLinePlan(item, plan, false);
    context.window.SM.refreshLineQuantity(item);
    assert.equal(item.quantity, 20);
    assert.equal(item.unit_price, '99.00');
    context.window.SM.refreshLinePrice(item);
    assert.equal(item.unit_price, '6.00');
    assert.equal(item.details_json.workshop.supplied_categories[2], true);
    item.supplied_categories[2] = false;
    update(item);
    assert.equal(item.unit_price, '8.50');
});

test('inclusive rounding produces clean customer prices without ex-GST multiplication drift', () => {
    const plan = { rounding_step: 50, rules: [{ category_id: 1, basis: 'participant', rate_cents: 1770 }] };
    const item = { kind: 'workshop', auto_pricing: true, workshop_hours: 1, workshop_seats: 17, gst_applicable: true };
    update(item, plan);
    assert.equal(item.details_json.inclusive_unit_price, 19.5);
    assert.equal(item.unit_price, '17.73');
    const amounts = context.window.SM.lineAmounts(item);
    assert.equal(amounts.net, 301.36);
    assert.equal(amounts.tax, 30.14);
    assert.equal(context.window.SM.suggestTicketPrice(plan, 1, 10), '19.50');
    assert.equal(context.window.SM.suggestTicketPrice({ rounding_step: 10, rules: [{ basis: 'participant', rate_cents: 1200 }] }, 1, 30), '13.20');
});

test('invoice reference breakdown combines workshop and travel costs and respects supplied items', () => {
    const rules = [{ category_id: 1, basis: 'hour', rate_cents: 6000 }, { category_id: 2, basis: 'participant', rate_cents: 500, suppliable: true }, { category_id: 1, basis: 'travel', rate_cents: 1500 }];
    const result = context.window.SM.lineCostAllocations([
        { kind: 'workshop', workshop_hours: 2, workshop_seats: 15, supplied_categories: { 2: true } },
        { kind: 'travel', travel_units: 2 },
        { kind: 'custom', quantity: 1, unit_price: 100 },
    ], rules);
    assert.equal(result[1], 15000);
    assert.equal(result[2], undefined);
});

test('travel has separate inclusive rounding and preserves exact totals', () => {
    const item = { kind: 'travel', auto_pricing: true, travel_units: 2, gst_applicable: true };
    update(item, { travel_cents: 3400, rounding_step: 10, travel_rounding_step: 500 });
    assert.equal(item.details_json.inclusive_unit_price, 35);
    assert.equal(item.unit_price, '31.82');
    const amounts = context.window.SM.lineAmounts(item);
    assert.equal(amounts.net, 63.64);
    assert.equal(amounts.tax, 6.36);
});

test('ticket price fills blanks, preserves existing prices, and never changes other registrations', () => {
    const plan = { rounding_step: 50, rules: [{ basis: 'participant', rate_cents: 1770 }] };
    const price = (registration, current, seats = 10, force = false) => context.window.SM.workshopPrice(plan, registration, current, '2026-09-13T10:00', '2026-09-13T11:00', seats, force);
    assert.equal(price('tickets', ''), '19.50');
    assert.equal(price('tickets', 'Free'), 'Free');
    assert.equal(price('tickets', '0'), '0');
    assert.equal(price('tickets', '22.00'), '22.00');
    assert.equal(price('tickets', '22.00', 10, true), '19.50');
    assert.equal(price('tickets', '', ''), '');
    assert.equal(price('none', ''), '');
    assert.equal(price('none', '22.00', 10, true), '22.00');
});

test('ticket pricing caps attendance at the plan baseline and includes delivery duration', () => {
    const plan = { pricing_participants: 10, rounding_step: 50, rules: [
        { basis: 'workshop', rate_cents: 8000 }, { basis: 'hour', rate_cents: 9000 }, { basis: 'participant', rate_cents: 70 },
    ] };
    const price = (seats, end = '11:00') => context.window.SM.workshopPrice(plan, 'tickets', '', '2026-09-13T10:00', `2026-09-13T${end}`, seats);
    assert.equal(price(10), '19.50');
    assert.equal(price(20), '19.50');
    assert.equal(price(5), '38.50');
    assert.equal(price(10, '12:00'), '29.50');
    assert.equal(price(20, '12:00'), '29.50');
});

test('workshop breakdown uses selected rules, duration and capped attendance', () => {
    const plan = { pricing_participants: 10, rules: [{ category_id: 6, basis: 'hour', rate_cents: 6000 }, { category_id: 2, basis: 'participant', rate_cents: 500 }] };
    const breakdown = context.window.SM.ticketCostBreakdown(plan, '2026-09-07T10:00', '2026-09-07T12:00', 20);
    assert.equal(breakdown.participants, 10);
    assert.equal(breakdown.categories[6], 12000);
    assert.equal(breakdown.categories[2], 5000);
    assert.equal(breakdown.total, 17000);
    assert.equal(context.window.SM.ticketCostBreakdown(plan, '', '', 5).total, 0);
    assert.equal(context.window.SM.ticketCostBreakdown(plan, '2026-09-07T10:00', '2026-09-07T12:00', 5).total, 14500);
    const online = { ...plan, rules: [plan.rules[0]] };
    assert.equal(context.window.SM.ticketCostBreakdown(online, '2026-09-07T10:00', '2026-09-07T12:00', 20).total, 12000);
});

test('full capacity comparison keeps fixed costs and increases per-participant costs without changing pricing attendance', () => {
    const plan = { pricing_participants: 10, rules: [{ category_id: 1, basis: 'hour', rate_cents: 6000 }, { category_id: 2, basis: 'participant', rate_cents: 500 }] };
    const baseline = context.window.SM.ticketCostBreakdown(plan, '2026-09-07T10:00', '2026-09-07T11:00', 15);
    const maximum = context.window.SM.ticketCostBreakdown(plan, '2026-09-07T10:00', '2026-09-07T11:00', 15, false);
    assert.equal(baseline.participants, 10);
    assert.equal(maximum.participants, 15);
    assert.equal(baseline.categories[1], maximum.categories[1]);
    assert.equal(baseline.total, 11000);
    assert.equal(maximum.total, 13500);
});
test('travel defaults use ex-GST allocation costs instead of a stale inclusive price', () => {
    const item = { kind: 'travel', travel_units: 9, auto_pricing: true, gst_applicable: true };
    update(item, { travel_cents: 3400, travel_rounding_step: 50, rules: [
        { category_id: 3, basis: 'travel', rate_cents: 1900 },
        { category_id: 6, basis: 'travel', rate_cents: 1500 },
    ] });
    assert.equal(item.details_json.inclusive_unit_price, 37.5);
    const amounts = context.window.SM.lineAmounts(item);
    assert.equal(amounts.net + amounts.tax, 337.5);
    assert.ok(amounts.net >= 306);
});
test('travel is entered and billed in hours while allocations retain quarter-hour units', () => {
    const item = { kind: 'travel', travel_hours: 0.25, auto_pricing: true };
    update(item, { travel_rounding_step: 50, rules: [{ category_id: 1, basis: 'travel', rate_cents: 3400 }] });
    assert.equal(item.quantity, 0.25);
    assert.equal(item.details_json.travel.billable_units, 1);
    assert.equal(item.details_json.travel.quantity_basis, 'hours');
    assert.equal(item.details_json.inclusive_unit_price, 150);
    const amounts = context.window.SM.lineAmounts(item);
    assert.equal(amounts.net + amounts.tax, 37.5);
    assert.equal(context.window.SM.lineCostAllocations([item], [{ category_id: 1, basis: 'travel', rate_cents: 3400 }])[1], 3400);
});
test('legacy travel units convert to hours without changing their stored total or converting twice', () => {
    const item = { kind: 'travel', quantity: 9, unit_price: 30.91, details_json: { travel: { billable_units: 9 }, inclusive_unit_price: 34 } };
    context.window.SM.hydrateTravelLine(item);
    assert.equal(item.quantity, 2.25);
    assert.equal(item.unit_price, '123.64');
    assert.equal(item.details_json.inclusive_unit_price, 136);
    context.window.SM.hydrateTravelLine(item);
    assert.equal(item.quantity, 2.25);
    assert.equal(context.window.SM.lineAmounts(item).net + context.window.SM.lineAmounts(item).tax, 306);
});

 test('invoice rounding includes all five workshops and hourly travel without changing prices', () => {
    const rules = [
        { category_id: 2, basis: 'participant', rate_cents: 450 },
        { category_id: 4, basis: 'workshop', rate_cents: 1500 },
        { category_id: 5, basis: 'workshop', rate_cents: 800 },
        { category_id: 6, basis: 'hour', rate_cents: 6000 },
        { category_id: 7, basis: 'workshop', rate_cents: 600 },
        { category_id: 3, basis: 'travel', rate_cents: 1900 },
        { category_id: 6, basis: 'travel', rate_cents: 1500 },
    ];
    const items = Array.from({ length: 5 }, () => ({ kind: 'workshop', workshop_hours: 1, workshop_seats: 10, quantity: 10, unit_price: '13.64' }));
    items.push({ kind: 'travel', travel_hours: 2.25, quantity: 2.25, unit_price: '136.36' });
    const before = JSON.stringify(items);
    assert.equal(context.window.SM.invoiceRoundingAllowance(items, rules, { rounding_step: 50, travel_rounding_step: 50 }), 1262);
    assert.equal(JSON.stringify(items), before);
    assert.equal(context.window.SM.invoiceRoundingAllowance([{ kind: 'workshop', quantity: 10, unit_price: 50 }], rules, { rounding_step: 50 }), 0);
});

test('multi delivery generates notes and prices each workshop independently', () => {
    const plan = { rules: [{ category_id: 1, basis: 'hour', rate_cents: 6000 }, { category_id: 2, basis: 'participant', rate_cents: 500 }, { category_id: 3, basis: 'venue_hour', rate_cents: 4500 }], rounding_step: 50 };
    const item = { kind: 'multi_workshop', auto_pricing: true, gst_applicable: true, workshops: [
        { description: 'Term 1 library', workshop_date: '2026-09-01', workshop_hours: 1, workshop_seats: 10, venue_supplied: true },
        { description: 'Term 1 hall', workshop_hours: 2, workshop_seats: 15, venue_supplied: false },
    ] };
    update(item, plan);
    assert.equal(item.quantity, 40);
    assert.match(item.notes, /01\/09\/2026 - Term 1 library - \(1 hr \/ 10 seats\)/);
    const separate = item.workshops.map(row => { const value = { ...row, kind: 'workshop', auto_pricing: true }; update(value, plan); return context.window.SM.lineAmounts(value); });
    const expected = separate.reduce((sum, value) => sum + value.net + value.tax, 0);
    assert.equal(Math.round(item.details_json.inclusive_unit_price * item.quantity * 100) / 100, Math.round(expected * 100) / 100);
    const targets = context.window.SM.lineCostAllocations([item], plan.rules);
    assert.equal(targets[1], 18000);
    assert.equal(targets[2], 12500);
    assert.equal(targets[3], 9000);
    item.auto_pricing = false;
    item.unit_price = '999.00';
    item.workshops[0].workshop_hours = 2;
    update(item, plan);
    assert.equal(item.unit_price, '999.00');
    assert.match(item.notes, /2 hr \/ 10 seats/);
});

test('saved one-group delivery adopts seat hours without changing its total twice', () => {
    const item = { kind: 'multi_workshop', quantity: 1, unit_price: '500.00', details_json: { multi_workshop: { rows: [
        { description: 'Library', workshop_hours: 1, workshop_seats: 40 }
    ] } } };
    update(item);
    assert.equal(item.quantity, 40);
    assert.equal(item.unit_price, '12.50');
    assert.equal(context.window.SM.lineAmounts(item).net, 500);
    update(item);
    assert.equal(context.window.SM.lineAmounts(item).net, 500);
});

test('course ticket pricing and allocation use teaching hours rather than eight weeks elapsed', () => {
    const plan = { pricing_participants: 10, rules: [{ category_id: 1, basis: 'hour', rate_cents: 6000 }] };
    const start = '2026-10-01T10:00', end = '2026-11-19T11:00';
    const breakdown = context.window.SM.ticketCostBreakdown(plan, start, end, 10, true, 8);
    assert.equal(breakdown.total, 48000);
    assert.equal(context.window.SM.workshopPrice(plan, 'tickets', '', start, end, 10, true, 8),
        context.window.SM.workshopPrice(plan, 'tickets', '', '2026-10-01T10:00', '2026-10-01T18:00', 10, true));
    const editor = { ...context.window.SM.courseEditor('course', [
        { starts_at: '2026-10-01T10:00', ends_at: '2026-10-01T11:00' },
        { starts_at: '2026-10-08T10:00', ends_at: '2026-10-08T11:30' },
    ]) };
    assert.equal(editor.courseTeachingHours(), 2.5);
    editor.courseSessions.pop();
    assert.equal(editor.courseTeachingHours(), 1);
});

test('regenerating course sessions uses the shared confirmation and preserves cancelled edits', async () => {
    context.SM = context.window.SM;
    context.crypto = require('node:crypto');
    context.SM.toLocalISOString = date => date.toISOString();
    const original = [{ id: 'saved-session', starts_at: '2026-10-01T10:00', ends_at: '2026-10-01T11:00' }];
    const editor = { ...context.SM.courseEditor('course', original), manualStartsAt: '2026-10-01T10:00', $dispatch() {} };
    context.SM.confirm = async () => ({ isConfirmed: false });
    await editor.generateSessions();
    assert.equal(editor.courseSessions, original);
    context.SM.confirm = async () => ({ isConfirmed: true });
    await editor.generateSessions();
    assert.equal(editor.courseSessions.length, 8);
    assert.equal(editor.courseTeachingHours(), 8);
});
