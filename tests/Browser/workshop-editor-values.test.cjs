const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const blade = fs.readFileSync('resources/views/admin/workshop/edit.blade.php', 'utf8');

test('reviewing a workshop shows the latest price after registration edits', async () => {
    const { JSDOM } = require('jsdom');
    const review = blade.match(/<dd[^>]*x-text="reviewValue\('price'\)[^>]*><\/dd>/)[0];
    const method = blade.match(/reviewValue\(name\) \{[\s\S]*?\n {12}\}/)[0];
    const dom = new JSDOM(`<!doctype html><form x-data="reviewFixture" x-ref="workshopForm"><div x-data="{ price: '' }"><input name="price" x-model="price"></div>${review}</form>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    try {
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.js', 'utf8'));
        const methods = window.eval(`({${method}})`);
        window.Alpine.data('reviewFixture', () => ({ editorStep: 'registration', ...methods }));
        window.Alpine.start();
        await window.Alpine.nextTick();
        const form = window.document.querySelector('form');
        const state = window.Alpine.$data(form);
        const input = form.elements.namedItem('price');
        for (const price of ['13.75', '22.00', '']) {
            state.editorStep = 'registration';
            await window.Alpine.nextTick();
            input.value = price;
            input.dispatchEvent(new window.Event('input', { bubbles: true }));
            await window.Alpine.nextTick();
            state.editorStep = 'review';
            await window.Alpine.nextTick();
            assert.equal(form.querySelector('dd').textContent, price || 'No price set');
        }
    } finally { window.close(); }
});

function scheduleEditor(type = 'physical', initial = {}) {
    const values = Object.fromEntries(Object.entries({ type, starts_at: '', ends_at: '', closes_at: '', publish_at: '2026-10-01T09:00', ...initial })
        .map(([name, value]) => [name, { value }]));
    const context = {
        document: { getElementsByName: name => values[name] ? [values[name]] : [], addEventListener() {} },
        SM: { toLocalISOString: date => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16) },
    };
    vm.runInNewContext(blade.slice(blade.indexOf('>', blade.lastIndexOf('<script')) + 1, blade.lastIndexOf('</script>')), context);
    const start = blade.indexOf('            updatedStartsAt(value) {');
    const end = blade.indexOf('            originalCourseSessions:', start);
    const editor = {
        ...vm.runInNewContext(`({${blade.slice(start, end)}})`, context),
        manualStartsAt: values.starts_at.value, manualEndsAt: values.ends_at.value,
        $dispatch() {},
        $nextTick(callback) {
            values.starts_at.value = this.manualStartsAt;
            values.ends_at.value = this.manualEndsAt;
            callback();
        },
    };
    function dispatch(name, event, value = values[name].value) {
        values[name].value = value;
        const field = blade.match(new RegExp(`<x-ui.input\\s[^>]*name="${name}"[^>]*\\/>`))[0];
        const handlers = [...field.matchAll(new RegExp(`x-on:${event}="([^"]*)"`, 'g'))];
        assert.equal(handlers.length, 1, `${name} has a single ${event} handler`);
        vm.runInNewContext(`(function($event) { with (this) { ${handlers[0][1]} } })`, context).call(editor, { target: values[name] });
    }
    return {
        editor, values,
        change: (name, value) => dispatch(name, 'change', value),
        blur: name => dispatch(name, 'blur'),
    };
}

test('leaving the start field fills date defaults using the final calendar selection', () => {
    for (const type of ['physical', 'online']) {
        for (const [start, end, closes] of [
            ['2026-10-20T00:30', '2026-10-20T01:30', '2026-10-19T22:30'],
            ['2026-12-31T23:30', '2027-01-01T00:30', '2026-12-31T21:30'],
        ]) {
            const state = scheduleEditor(type);
            state.change('starts_at', `${start.slice(0, 10)}T00:00`);
            assert.equal(state.editor.manualEndsAt, '');
            assert.equal(state.values.closes_at.value, '');
            state.change('starts_at', start);
            assert.equal(state.editor.manualStartsAt, start);
            assert.equal(state.editor.manualEndsAt, '');
            assert.equal(state.values.closes_at.value, '');
            state.blur('starts_at');
            assert.equal(state.editor.manualEndsAt, end, type);
            assert.equal(state.values.ends_at.value, end, type);
            assert.equal(state.values.closes_at.value, closes, type);
        }
    }
});

test('schedule changes preserve entered end and closing dates and refill only missing defaults', () => {
    const state = scheduleEditor();
    state.change('starts_at', '2026-10-20T10:00');
    state.blur('starts_at');
    state.change('ends_at', '2026-10-20T13:30');
    state.values.closes_at.value = '2026-10-19T17:00';
    state.change('starts_at', '2026-10-20T11:00');
    state.blur('starts_at');
    assert.equal(state.editor.manualEndsAt, '2026-10-20T13:30');
    assert.equal(state.values.closes_at.value, '2026-10-19T17:00');
    state.values.closes_at.value = '';
    state.change('ends_at', '2026-10-20T14:00');
    assert.equal(state.values.closes_at.value, '2026-10-20T09:00');
});

test('empty and invalid starts do not create dates, while STEMCraft still closes at the end', () => {
    const empty = scheduleEditor();
    for (const value of ['', 'invalid']) {
        empty.change('starts_at', value);
        empty.blur('starts_at');
        assert.equal(empty.editor.manualEndsAt, '');
        assert.equal(empty.values.closes_at.value, '');
    }
    const stemcraft = scheduleEditor('stemcraft');
    stemcraft.change('starts_at', '2026-10-20T10:00');
    stemcraft.blur('starts_at');
    assert.equal(stemcraft.values.closes_at.value, '2026-10-20T11:00');
    stemcraft.change('ends_at', '2026-10-20T12:00');
    assert.equal(stemcraft.values.closes_at.value, '2026-10-20T12:00');
});

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
        vm.runInNewContext(blade.slice(blade.indexOf('>', blade.lastIndexOf('<script')) + 1, blade.lastIndexOf('</script>')), context);
        assert.equal(values.closes_at.value, '2024-05-31T17:00', type);
        values.closes_at.value = '';
        context.syncWorkshopClosesAt(true);
        assert.notEqual(values.closes_at.value, '', type);
        if (type === 'stemcraft') assert.equal(values.closes_at.value, values.ends_at.value);
    }
});

test('workshop form bootstrap is available independently of the compiled pricing bundle', () => {
    const context = { window: { SM: {} } };
    vm.runInNewContext(fs.readFileSync('public/workshop-course.js', 'utf8'), context);
    const state = context.window.SM.courseEditor('workshop', null);
    assert.equal(state.workshopFormat, 'workshop');
    assert.equal(state.courseSessions.length, 0);
    assert.equal(typeof state.courseTeachingHours, 'function');
    assert.ok(blade.indexOf('/workshop-course.js?v=') < blade.indexOf('...SM.courseEditor('));
});

test('clearing an automatic price keeps the workshop free on blur and schedule changes', () => {
    const editor = pricingEditor();
    editor.reprice();
    editor.price = '';
    editor.automatic = false;
    editor.reprice();
    assert.equal(editor.price, '');
    assert.equal(editor.automatic, false);
    editor.manualEndsAt = '2024-06-01T12:30';
    editor.reprice();
    assert.equal(editor.price, '');
    assert.equal(editor.breakdown.total, 18000);
    editor.reprice(true);
    assert.notEqual(editor.price, '');
    assert.equal(editor.automatic, true);
});

test('opening a saved free workshop preserves a blank price', () => {
    for (const price of ['', null]) {
        const editor = pricingEditor(false);
        editor.price = price;
        editor.reprice();
        assert.equal(editor.price, price);
        assert.equal(editor.automatic, false);
    }
});

test('new automatic workshops still receive an initial suggested price', () => {
    const editor = pricingEditor(true);
    editor.price = '';
    editor.reprice();
    assert.notEqual(editor.price, '');
    assert.equal(editor.automatic, true);
});
