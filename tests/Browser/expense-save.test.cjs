const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function element() {
    const listeners = {};
    const classes = new Set(['hidden']);
    const attributes = new Map();
    return {
        hidden: true, disabled: false, files: [], value: '',
        getAttribute: name => attributes.get(name) ?? null,
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
        classList: {
            add: (...names) => names.forEach(name => classes.add(name)),
            remove: (...names) => names.forEach(name => classes.delete(name)),
            toggle: (name, on) => on ? classes.add(name) : classes.delete(name),
            contains: name => classes.has(name),
        },
        addEventListener: (name, fn) => (listeners[name] ||= []).push(fn),
        dispatchEvent(event) { return Promise.all((listeners[event.type] || []).map(fn => fn(event))); },
        replaceChildren(...children) { this.children = children; },
        focus() { this.focused = true; },
        scrollIntoView() {},
        setCustomValidity(message) { this.validationMessage = message; },
        reportValidity() {},
    };
}

function setup(fetch) {
    const ids = Object.fromEntries([
        'expense-form', 'expense-receipt-file', 'expense-save-button', 'expense-save-label',
        'expense-save-loading', 'expense-save-loading-text',
        'dropzone', 'filename', 'meta', 'state', 'clear',
    ].map(id => [id, element()]));
    const form = ids['expense-form'];
    form.hasAttribute = name => name === 'data-sm-file-upload-managed';
    form.action = '/admin/expense';
    const input = ids['expense-receipt-file'];
    const file = new File(['receipt bytes'], 'Scan.jpeg', { type: 'image/jpeg', lastModified: 123456 });
    input.name = 'receipt_document_file';
    input.type = 'file';
    input.files = [file];
    const fieldValues = { supplier: 'Example Supplier', description: 'Fuel', invoice_id: '123', paid_on: '2026-09-20', total_amount: '51.03', gst_amount: '4.64', allocation_editor: '1' };
    const controls = Object.fromEntries(Object.entries(fieldValues).map(([name, value]) => [name, Object.assign(element(), { name, value })]));
    controls.receipt_document_file = input;
    const errors = {};
    const wrappers = Object.fromEntries([...Object.keys(controls), 'splits'].map(name => {
        errors[name] = Object.assign(element(), { id: `${name}-error` });
        return [name, { dataset: { validationField: name }, querySelector: selector => selector === 'label[for]' ? ids.dropzone : errors[name] }];
    }));
    for (const [name, control] of Object.entries(controls)) control.closest = selector => selector === 'form' ? form : wrappers[name];
    form.elements = Object.values(controls);
    form.querySelectorAll = selector => selector === '[data-validation-error]' ? Object.values(errors) : Object.values(wrappers);
    const notifications = [];
    const ready = [];
    let redirect;
    const context = {
        URLSearchParams, Blob, File, Error, console,
        SM: { alert: (...args) => notifications.push(args) },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        FormData: class {
            constructor() {
                const data = new FormData();
                for (const [name, value] of Object.entries(fieldValues)) data.set(name, value);
                data.set(input.name, input.files[0]);
                return data;
            }
        },
        fetch,
        window: { location: { pathname: '/admin/expense/create', search: '', assign: url => { redirect = url; } } },
        document: {
            readyState: 'loading',
            getElementById: id => ids[id] || null,
            querySelector: () => null,
            createElement: element,
            addEventListener: (name, fn) => { if (name === 'DOMContentLoaded') ready.push(fn); },
        },
    };
    vm.createContext(context);
    let component = fs.readFileSync('resources/views/components/ui/file-upload.blade.php', 'utf8').split(/<script[^>]*>/)[1].split('</script>')[0];
    const values = {
        '$inputId': 'expense-receipt-file', '$dropzoneId': 'dropzone', '$fileNameId': 'filename',
        '$fileMetaId': 'meta', '$fileStateId': 'state', '$fileClearId': 'clear',
        '$placeholderText': 'Choose a receipt', '$maxUploadBytes': 1000000, '$maxUploadSize': '1 MB',
        "'Max upload size: '.$maxUploadSize": 'Max upload size: 1 MB',
    };
    component = component.replace(/@js\(([^)]*)\)/g, (_, key) => JSON.stringify(values[key]));
    vm.runInContext(component, context);
    ready.splice(0).forEach(fn => fn());
    let editor = fs.readFileSync('resources/views/admin/expense/edit.blade.php', 'utf8').split(/<script[^>]*>/)[1].split('</script>')[0];
    editor = editor.replace('@json($errors->any())', 'false').replace('@json(!isset($expense))', 'true')
        .replace('@js($documentViewUrl)', 'null').replace('@js($documentName)', "''");
    vm.runInContext(editor, context);
    return {
        ids, input, file, controls, errors, notifications, redirect: () => redirect,
        submit: () => form.dispatchEvent({ type: 'submit', preventDefault() {} }),
    };
}

test('422 displays every validation error, stops uploading, retains the receipt and allows retry', async () => {
    let finish;
    let requests = 0;
    const app = setup(async () => {
        requests++;
        if (requests === 1) return new Promise(resolve => { finish = resolve; });
        return { ok: true, json: async () => ({ redirect: '/admin/expense' }) };
    });
    const pending = app.submit();
    assert.equal(app.ids.state.classList.contains('hidden'), false);
    assert.equal(app.ids['expense-save-button'].disabled, true);
    await app.submit();
    assert.equal(requests, 1);
    finish({ ok: false, status: 422, json: async () => ({ errors: {
        gst_amount: ['GST must not exceed the total.'], splits: ['Allocations must equal the total excluding GST.'],
    } }) });
    await pending;
    assert.deepEqual(app.notifications[0], ['The expense could not be saved',
        'GST must not exceed the total.\nAllocations must equal the total excluding GST.', 'error']);
    assert.equal(app.errors.gst_amount.hidden, false);
    assert.equal(app.errors.gst_amount.textContent, 'GST must not exceed the total.');
    assert.equal(app.controls.gst_amount.getAttribute('aria-invalid'), 'true');
    assert.equal(app.controls.gst_amount.getAttribute('aria-describedby'), 'gst_amount-error');
    assert.equal(app.controls.gst_amount.classList.contains('border-red-600'), true);
    assert.equal(app.errors.splits.hidden, false);
    assert.equal(app.errors.splits.textContent, 'Allocations must equal the total excluding GST.');
    assert.equal(app.ids.state.classList.contains('hidden'), true);
    assert.equal(app.ids['expense-save-button'].disabled, false);
    assert.equal(app.input.files[0], app.file);
    assert.equal(app.redirect(), undefined);
    await app.submit();
    assert.equal(app.errors.gst_amount.hidden, true);
    assert.equal(app.controls.gst_amount.getAttribute('aria-invalid'), null);
    assert.equal(app.controls.gst_amount.classList.contains('border-red-600'), false);
    assert.equal(app.redirect(), '/admin/expense');
});

for (const [name, fetch] of [
    ['network failure', async () => { throw new Error('Failed to fetch'); }],
    ['non-JSON server failure', async () => ({ ok: false, json: async () => { throw new Error('Invalid JSON'); } })],
]) {
    test(`${name} shows an error and resets upload and save controls`, async () => {
        const app = setup(fetch);
        await app.submit();
        assert.equal(app.notifications[0][2], 'error');
        assert.ok(app.notifications[0][1]);
        assert.equal(app.ids.state.classList.contains('hidden'), true);
        assert.equal(app.ids['expense-save-button'].disabled, false);
        assert.equal(app.input.files[0], app.file);
        assert.equal(app.redirect(), undefined);
    });
}


test('normal receipt uploads rebuild file bytes and preserve every form field', async () => {
    let sent;
    const app = setup(async (_, request) => {
        sent = request.body;
        return { ok: true, json: async () => ({ redirect: '/admin/expense' }) };
    });
    let reads = 0;
    const read = app.file.arrayBuffer.bind(app.file);
    app.file.arrayBuffer = () => { reads++; return read(); };
    await app.submit();
    assert.equal(reads, 1);
    const receipt = sent.get('receipt_document_file');
    assert.notEqual(receipt, app.file);
    assert.equal(await receipt.text(), 'receipt bytes');
    assert.equal(receipt.name, 'Scan.jpeg');
    assert.equal(receipt.type, 'image/jpeg');
    assert.equal(receipt.lastModified, 123456);
    for (const field of ['supplier', 'description', 'invoice_id', 'paid_on', 'total_amount', 'gst_amount']) {
        assert.equal(sent.get(field), app.controls[field].value);
    }
    assert.ok((await new Response(sent).arrayBuffer()).byteLength > receipt.size);
});

test('unreadable receipts show an attachment error without sending an empty request', async () => {
    let sent = false;
    const app = setup(async () => { sent = true; });
    app.file.arrayBuffer = async () => { throw new Error('Unreadable file'); };
    await app.submit();
    assert.equal(sent, false);
    assert.equal(app.errors.receipt_document_file.hidden, false);
    assert.equal(app.ids.dropzone.classList.contains('border-red-600'), true);
    assert.match(app.errors.receipt_document_file.textContent, /attach it again/);
    assert.equal(app.notifications[0][2], 'error');
    assert.equal(app.ids.state.classList.contains('hidden'), true);
    assert.equal(app.ids['expense-save-button'].disabled, false);
});


test('required field failures mark each affected input and use the standard error notification', async () => {
    const names = ['supplier', 'description', 'invoice_id', 'total_amount', 'gst_amount'];
    const errors = Object.fromEntries(names.map(name => [name, [`The ${name} field is required.`]]));
    const app = setup(async () => ({ ok: false, status: 422, json: async () => ({ errors }) }));
    await app.submit();
    for (const name of names) {
        assert.equal(app.errors[name].hidden, false);
        assert.equal(app.errors[name].textContent, errors[name][0]);
        assert.equal(app.controls[name].getAttribute('aria-invalid'), 'true');
        assert.equal(app.controls[name].classList.contains('border-red-600'), true);
    }
    assert.equal(app.notifications.length, 1);
    assert.equal(app.notifications[0][2], 'error');
});

test('Mail cid attachment names are still normalized when materialized', async () => {
    let receipt;
    const app = setup(async (_, request) => {
        receipt = request.body.get('receipt_document_file');
        return { ok: true, json: async () => ({ redirect: '/admin/expense' }) };
    });
    app.input.files = [new File(['mail receipt'], 'cid:<receipt.pdf>', { type: 'application/pdf' })];
    await app.submit();
    assert.equal(receipt.name, 'receipt.pdf');
    assert.equal(await receipt.text(), 'mail receipt');
});
