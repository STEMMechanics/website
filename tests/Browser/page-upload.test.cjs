const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(upload) {
    const events = {}, inputEvents = {};
    const overlay = { hidden: true };
    const input = { disabled: false, value: '', addEventListener: (name, fn) => { inputEvents[name] = fn; } };
    const button = { disabled: false, addEventListener() {} };
    const root = { querySelector: selector => selector === 'input[type="file"]' ? input : selector === '[data-page-drop-overlay]' ? overlay : button };
    const context = { document: { addEventListener: (name, fn) => { events[name] = fn; } }, window: { addEventListener: (name, fn) => { events[name] = fn; } } };
    vm.runInNewContext(fs.readFileSync('resources/js/page-upload.js', 'utf8').replace('export ', '') + '\nthis.init = initialisePageUpload;', context);
    context.init(root, upload);
    const event = (types = ['Files']) => ({ dataTransfer: { types, files: [{ name: 'file.png', size: 123 }] }, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
    return { events, overlay, input, button, event };
}

test('page drop only accepts files and handles nested drag targets without flickering', () => {
    const app = setup(async () => {});
    app.events.dragenter(app.event(['text/plain']));
    assert.equal(app.overlay.hidden, true);
    app.events.dragenter(app.event()); app.events.dragenter(app.event());
    app.events.dragleave(app.event());
    assert.equal(app.overlay.hidden, false);
    app.events.dragleave(app.event());
    assert.equal(app.overlay.hidden, true);
});

test('a file drop starts one upload and blocks another until it finishes', async () => {
    let count = 0, finish;
    const app = setup(() => { count++; return new Promise(resolve => { finish = resolve; }); });
    app.events.dragenter(app.event());
    const drop = app.event(); app.events.drop(drop);
    assert.equal(drop.defaultPrevented, true);
    assert.equal(app.overlay.hidden, true);
    assert.equal(app.input.disabled, true);
    app.events.drop(app.event());
    assert.equal(count, 1);
    finish(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(app.input.disabled, false);
    assert.equal(app.button.disabled, false);
});

test('a drop already handled by another upload control does not upload twice', () => {
    let count = 0;
    const app = setup(async () => { count++; });
    const drop = app.event(); drop.defaultPrevented = true;
    app.events.drop(drop);
    assert.equal(count, 0);
});
