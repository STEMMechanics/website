const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const listeners = {}, banners = [], loads = [], events = [];
    const button = { disabled: false };
    const dialog = { open: true, close() { this.open = false; } };
    const form = { dataset: {}, action: '/suppliers/1', matches: () => true, closest: () => dialog, querySelector: () => button };
    const context = {
        document: { addEventListener: (name, callback) => { listeners[name] = callback; }, querySelector: () => ({}), dispatchEvent: event => events.push(event) },
        FormData: class {}, URL, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        window: { location: { href: 'https://example.test/suppliers?search=venue&page=2' } },
        loadList: async (root, url, options) => loads.push([url.href, options.historyMode]),
        SM: { banner: (...args) => banners.push(args) },
        fetch: async () => ({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ message: 'Supplier saved.' }) }),
    };
    vm.runInNewContext(fs.readFileSync('resources/js/record-editor.js', 'utf8').replace(/^import .*;$/gm, ''), context);
    return { context, dialog, form, button, banners, loads, events, submit: () => listeners.submit({ target: form, preventDefault() {} }) };
}

test('popup save refreshes the current filtered page without full navigation', async () => {
    const app = setup();
    await app.submit();
    assert.equal(app.dialog.open, false);
    assert.equal(app.events[0].type, 'sm:record-saved');
    assert.equal(app.events[0].detail.message, 'Supplier saved.');
    assert.deepEqual(app.loads, [['https://example.test/suppliers?search=venue&page=2', 'replace']]);
    assert.equal(app.button.disabled, false);
    assert.equal(app.form.dataset.saving, undefined);
});

test('validation failure keeps the editor open and allows correction', async () => {
    const app = setup();
    app.context.fetch = async () => ({ ok: false, headers: { get: () => 'application/json' }, json: async () => ({ errors: { name: ['Supplier already exists.'] } }) });
    await app.submit();
    assert.equal(app.dialog.open, true);
    assert.equal(app.loads.length, 0);
    assert.equal(app.banners[0][1], 'Supplier already exists.');
    assert.equal(app.button.disabled, false);
});

test('invoice popup refreshes its allocation panel without a list or page navigation', async () => {
    const app = setup();
    const panel = { innerHTML: '', hasAttribute: name => name === 'data-record-refresh' };
    app.context.document.getElementById = id => id === 'invoice-cost-centres' ? panel : null;
    app.context.document.querySelector = () => null;
    app.context.fetch = async () => ({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ message: 'Saved.', target: 'invoice-cost-centres', html: '<p>Operational $100.00</p>' }) });
    await app.submit();
    assert.equal(panel.innerHTML, '<p>Operational $100.00</p>');
    assert.equal(app.dialog.open, false);
    assert.equal(app.loads.length, 0);
});
