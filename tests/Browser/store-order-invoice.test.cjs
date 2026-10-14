const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function deferred() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

function json(data, ok = true) {
    return { ok, headers: { get: () => 'application/json' }, json: async () => data };
}

function setup() {
    const requests = [];
    const redirects = [];
    const processing = [];
    const dialog = { open: true, scrollTop: 20, querySelector: () => ({ focus() {} }) };
    const context = {
        AbortController, URL, setTimeout, clearTimeout,
        FormData: class { constructor(form) { this.fields = form.fields; } },
        openListDialog: () => { dialog.open = true; },
        fetch: (url, options) => {
            const response = deferred();
            requests.push({ url: String(url), options, ...response });
            return response.promise;
        },
        window: {
            SM: { setFormProcessing: (form, busy) => processing.push(busy) },
            location: { href: 'http://localhost/admin/store/orders/1', assign: (url) => redirects.push(url) },
        },
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('resources/js/store-order-invoice.js', 'utf8')
        .replace(/^import .*;\n/m, '').replace('export function', 'function'), context);
    const editor = context.window.SM.storeOrderInvoiceAttachment({
        optionsUrl: 'http://localhost/admin/store/orders/1/invoices/options',
        reviewUrl: 'http://localhost/admin/store/orders/1/invoices/attach',
    });
    editor.$refs = { popup: { querySelector: () => dialog } };
    editor.$nextTick = (callback) => callback();
    return { editor, requests, redirects, processing, dialog };
}

test('an older search response cannot replace newer suggestions or the keyboard choice', async () => {
    const { editor, requests } = setup();
    editor.search = 'old invoice';
    const olderSearch = editor.searchInvoices('old invoice');
    editor.cancelSearch();
    editor.search = 'new invoice';
    const newerSearch = editor.searchInvoices('new invoice');
    assert.equal(requests[0].options.signal.aborted, true);
    assert.equal(new URL(requests[1].url).searchParams.get('q'), 'new invoice');
    requests[1].resolve(json({ invoices: [{ id: 20 }, { id: 21 }] }));
    await newerSearch;
    requests[0].resolve(json({ invoices: [{ id: 10 }] }));
    await olderSearch;
    assert.equal(editor.suggestions[0].id, 20);
    assert.equal(editor.suggestions.length, 2);
    let chosen;
    editor.review = (id) => { chosen = id; };
    editor.moveSuggestion(1);
    editor.chooseSuggestion();
    assert.equal(chosen, 21);
});

test('closing and reopening the popup ignores the previous invoice list response', async () => {
    const { editor, requests, dialog } = setup();
    const firstOpen = editor.open({});
    dialog.open = false;
    editor.dismiss();
    const secondOpen = editor.open({});
    requests[1].resolve(json({ invoices: [{ id: 30 }] }));
    await secondOpen;
    requests[0].resolve(json({ invoices: [{ id: 10 }] }));
    await firstOpen;
    assert.equal(editor.invoices[0].id, 30);
    assert.equal(editor.recentLoading, false);
});

test('attachment validation keeps the review, prevents duplicate submissions and allows a corrected retry', async () => {
    const { editor, requests, redirects, processing } = setup();
    editor.reviewHtml = '<form>Selected invoice and item mapping</form>';
    const form = { action: 'http://localhost/admin/store/orders/1/invoices', fields: { invoice_id: 44, shipment_plan: 'same_shipment' } };
    const firstSave = editor.save(form);
    await editor.save(form);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.body.fields.invoice_id, 44);
    requests[0].resolve(json({ errors: { items: ['Choose a valid product.'] } }, false));
    await firstSave;
    assert.equal(editor.error, 'Choose a valid product.');
    assert.equal(editor.reviewHtml, '<form>Selected invoice and item mapping</form>');
    assert.equal(editor.saving, false);
    assert.equal(redirects.length, 0);
    const retry = editor.save(form);
    requests[1].resolve(json({ redirect_url: 'http://localhost/admin/store/orders/1' }));
    await retry;
    assert.deepEqual(redirects, ['http://localhost/admin/store/orders/1']);
    assert.deepEqual(processing, [true, false, true, false]);
});
