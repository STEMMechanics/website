const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    let submit;
    const alerts = [], refreshes = [], storage = new Map([['selection', '["first","second"]']]);
    const content = { dataset: { bulkLoader: 'loader', bulkSelectionKey: 'selection', bulkSelectionField: 'ids[]', bulkList: 'items' }, innerHTML: '', replaceChildren() { this.innerHTML = ''; }, setAttribute() {} };
    const dialog = { open: false, querySelector: () => content, contains: () => true, close() { this.open = false; } };
    class Form {
        constructor(open = false) { this.open = open; this.dataset = open ? { bulkOpen: 'dialog' } : {}; this.action = '/bulk'; this.entries = [['ids[]', 'first']]; this.button = { disabled: false }; }
        matches(selector) { return selector === '[data-bulk-open]' ? this.open : !this.open; }
        querySelector() { return this.button; }
        closest() { return dialog; }
    }
    const context = {
        HTMLFormElement: Form, URL, AbortController, setTimeout, clearTimeout,
        FormData: class { constructor(form) { this.entries = form.entries; } getAll(key) { return this.entries.filter(([name]) => name === key).map(([, value]) => value); } },
        sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        document: { addEventListener: (_, callback) => { submit = callback; }, getElementById: id => id === 'dialog' ? dialog : { content: { cloneNode: () => ({}) } }, querySelectorAll: () => [{ dataset: { dynamicList: 'items' } }] },
        window: { location: { href: 'https://example.test/items?filter=current' } },
        SM: { banner: (...args) => alerts.push(args) },
        openListDialog: target => { target.open = true; },
        loadList: async (_, url) => refreshes.push(url.href),
        fetch: async () => ({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ html: '<form>editor</form>', message: 'Saved' }) }),
    };
    vm.runInNewContext(fs.readFileSync('resources/js/bulk-editor.js', 'utf8').replace(/^import .*;\n/gm, ''), context);
    return { context, Form, content, dialog, alerts, refreshes, storage, submit: form => submit({ target: form, preventDefault() {} }) };
}

test('bulk editor opens in a dialog and loads the editor without navigating', async () => {
    const app = setup();
    const loading = app.submit(new app.Form(true));
    assert.equal(app.dialog.open, true);
    await loading;
    assert.equal(app.content.innerHTML, '<form>editor</form>');
});

test('saving clears only saved selections and refreshes the filtered list', async () => {
    const app = setup(), form = new app.Form();
    app.dialog.open = true;
    await app.submit(form);
    assert.equal(app.dialog.open, false);
    assert.equal(app.storage.get('selection'), '["second"]');
    assert.deepEqual(app.refreshes, ['https://example.test/items?filter=current']);
    assert.equal(form.button.disabled, false);
});

test('validation failure keeps the editor and selection for correction', async () => {
    const app = setup(), form = new app.Form();
    app.dialog.open = true;
    app.context.fetch = async () => ({ ok: false, headers: { get: () => 'application/json' }, json: async () => ({ errors: { status: ['Invalid status'] } }) });
    await app.submit(form);
    assert.equal(app.dialog.open, true);
    assert.equal(app.storage.get('selection'), '["first","second"]');
    assert.equal(app.alerts[0][1], 'Invalid status');
    assert.equal(form.button.disabled, false);
    assert.equal(app.refreshes.length, 0);
});

test('closing the popup while loading does not insert a late response', async () => {
    const app = setup();
    let finish;
    app.context.fetch = () => new Promise(resolve => { finish = () => resolve({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ html: 'late editor' }) }); });
    const loading = app.submit(new app.Form(true));
    app.dialog.close();
    finish();
    await loading;
    assert.equal(app.content.innerHTML, '');
});
