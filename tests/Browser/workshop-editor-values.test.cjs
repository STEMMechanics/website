const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const blade = fs.readFileSync('resources/views/admin/workshop/edit.blade.php', 'utf8');

function pricingEditor(automatic = true) {
    const context = { window: {} };
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
    const body = blade.match(/reprice\(force = false\) \{([\s\S]*?)\n {28}\}/)[1];
    const reprice = vm.runInNewContext(`(function(force = false) {${body}})`, { SM: context.window.SM });
    return {
        reprice, price: '37.50', automatic, previousPricingInputs: null,
        planId: '1', plan: { pricing_participants: 10, rules: [{ category_id: 1, basis: 'hour', rate_cents: 6000 }] },
        registration: 'tickets', manualStartsAt: '2024-06-01T09:30', manualEndsAt: '2024-06-01T11:30', maxTickets: 10,
        courseTeachingHours() { return (new Date(this.manualEndsAt) - new Date(this.manualStartsAt)) / 3600000; },
    };
}

test('opening or blurring an older workshop preserves its saved automatic price', () => {
    const editor = pricingEditor();
    editor.reprice();
    assert.equal(editor.price, '37.50');
    assert.equal(editor.breakdown.total, 12000);
    editor.reprice();
    assert.equal(editor.price, '37.50');
    editor.manualEndsAt = '2024-06-01T12:30';
    editor.reprice();
    assert.notEqual(editor.price, '37.50');
});

test('explicit price refresh works while a manual price survives schedule edits', () => {
    const editor = pricingEditor(false);
    editor.reprice();
    editor.manualEndsAt = '2024-06-01T12:30';
    editor.reprice();
    assert.equal(editor.price, '37.50');
    editor.reprice(true);
    assert.notEqual(editor.price, '37.50');
    assert.equal(editor.automatic, true);
});

test('initialisation retains saved closing dates for all older workshop types', () => {
    for (const type of ['physical', 'online', 'stemcraft']) {
        const values = {
            type: { value: type }, starts_at: { value: '2024-06-01T09:30' }, ends_at: { value: '2024-06-01T11:30' },
            closes_at: { value: '2024-05-31T17:00' }, publish_at: { value: '2024-05-01T09:00' },
        };
        const context = {
            document: { getElementsByName: name => values[name] ? [values[name]] : [], addEventListener() {} },
            SM: { toLocalISOString: date => date.toISOString().slice(0, 16) },
        };
        vm.runInNewContext(blade.slice(blade.lastIndexOf('<script>') + 8, blade.lastIndexOf('</script>')), context);
        assert.equal(values.closes_at.value, '2024-05-31T17:00', type);
        values.closes_at.value = '';
        context.syncWorkshopClosesAt(true);
        assert.notEqual(values.closes_at.value, '', type);
        if (type === 'stemcraft') assert.equal(values.closes_at.value, values.ends_at.value);
    }
});
