const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function element() {
    const listeners = {};
    const classes = new Set(['hidden']);
    return {
        hidden: true, disabled: false, files: [], value: '',
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
        'expense-save-loading', 'expense-save-loading-text', 'expense-save-errors', 'expense-save-error-list',
        'dropzone', 'filename', 'meta', 'state', 'clear',
    ].map(id => [id, element()]));
    const form = ids['expense-form'];
    form.hasAttribute = name => name === 'data-sm-file-upload-managed';
    form.action = '/admin/expense';
    const input = ids['expense-receipt-file'];
    const file = { name: 'receipt.pdf', size: 1024 };
    input.files = [file];
    input.closest = () => form;
    const ready = [];
    let redirect;
    const context = {
        URLSearchParams, Blob, Error, console,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        FormData: class { constructor() { this.file = input.files[0]; } },
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
        ids, input, file, redirect: () => redirect,
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
    assert.equal(app.ids['expense-save-errors'].hidden, false);
    assert.equal(app.ids['expense-save-errors'].focused, true);
    assert.deepEqual(app.ids['expense-save-error-list'].children.map(child => child.textContent), [
        'GST must not exceed the total.', 'Allocations must equal the total excluding GST.',
    ]);
    assert.equal(app.ids.state.classList.contains('hidden'), true);
    assert.equal(app.ids['expense-save-button'].disabled, false);
    assert.equal(app.input.files[0], app.file);
    assert.equal(app.redirect(), undefined);
    await app.submit();
    assert.equal(app.ids['expense-save-errors'].hidden, true);
    assert.equal(app.redirect(), '/admin/expense');
});

for (const [name, fetch] of [
    ['network failure', async () => { throw new Error('Failed to fetch'); }],
    ['non-JSON server failure', async () => ({ ok: false, json: async () => { throw new Error('Invalid JSON'); } })],
]) {
    test(`${name} shows an error and resets upload and save controls`, async () => {
        const app = setup(fetch);
        await app.submit();
        assert.equal(app.ids['expense-save-errors'].hidden, false);
        assert.ok(app.ids['expense-save-error-list'].children[0].textContent);
        assert.equal(app.ids.state.classList.contains('hidden'), true);
        assert.equal(app.ids['expense-save-button'].disabled, false);
        assert.equal(app.input.files[0], app.file);
        assert.equal(app.redirect(), undefined);
    });
}
