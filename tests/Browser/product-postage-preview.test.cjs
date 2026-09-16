const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const measurements = { product_type: 'physical', box_only: false, length_mm: 200, width_mm: 100, height_mm: 50, weight_grams: 500 };
function editor(fetch) {
    const context = { window: {}, fetch, AbortController, Intl, queueMicrotask, setTimeout: () => 1, clearTimeout: () => {} };
    vm.runInNewContext(fs.readFileSync('resources/js/product-postage-preview.js', 'utf8'), context);
    return context.window.SM.productPostagePreview('/preview', 'token');
}
test('missing, invalid and digital measurements do not start a preview', () => {
    const preview = editor();
    for (const value of ['', null, 'abc', -1, 0, 1.5]) {
        preview.queue({ ...measurements, weight_grams: value });
        assert.equal(preview.busy, false);
        assert.match(preview.message, /Enter valid packed/);
    }
    preview.queue({ ...measurements, product_type: 'digital' });
    assert.equal(preview.busy, false);
    assert.equal(preview.message, '');
    preview.queue(measurements);
    assert.equal(preview.busy, true);
});
test('a stale response cannot replace the preview for newer measurements', async () => {
    let finish;
    const preview = editor(() => new Promise(resolve => { finish = resolve; }));
    preview.queue(measurements);
    const loading = preview.load(measurements, preview.version);
    preview.queue({ ...measurements, weight_grams: 900 });
    finish({ ok: true, json: async () => ({ options: [{ package: 'Old box' }] }) });
    await loading;
    assert.equal(preview.options.length, 0);
    assert.equal(preview.busy, true);
});
test('preview errors leave product editing available and show a message', async () => {
    const preview = editor(async () => { throw new Error('offline'); });
    preview.queue(measurements);
    await preview.load(measurements, preview.version);
    assert.equal(preview.busy, false);
    assert.match(preview.message, /Could not check postage/);
    preview.destroy();
    preview.queue({ ...measurements, weight_grams: 900 });
    assert.equal(preview.busy, false);
});
