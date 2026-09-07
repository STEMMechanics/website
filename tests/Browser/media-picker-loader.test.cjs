const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const opened = [], banners = [];
    const context = { window: { SM: { banner: (...args) => banners.push(args) } } };
    let loads = 0, reject = false;
    context.loadPicker = async () => {
        loads++;
        if (reject) throw new Error('Network unavailable');
        context.window.SMMediaPicker = { open: (...args) => opened.push(args) };
    };
    vm.runInNewContext(fs.readFileSync('resources/js/media-picker-loader.js', 'utf8').replace("import('./media-picker.js')", 'loadPicker()'), context);
    return { window: context.window, opened, banners, loads: () => loads, reject: value => { reject = value; } };
}

test('picker is downloaded only on first use and preserves selections and callback', async () => {
    const app = setup(), callback = () => {};
    assert.equal(app.loads(), 0);
    await app.window.SMMediaPicker.open(['photo'], { allow_camera: true }, callback);
    await app.window.SMMediaPicker.open([], {}, callback);
    assert.equal(app.loads(), 1);
    assert.deepEqual(app.opened[0], [['photo'], { allow_camera: true }, callback]);
    assert.equal(app.opened.length, 2);
});

test('failed download shows feedback and can be retried', async () => {
    const app = setup();
    app.reject(true);
    await app.window.SMMediaPicker.open([]);
    assert.equal(app.banners.length, 1);
    assert.equal(app.opened.length, 0);
    app.reject(false);
    await app.window.SMMediaPicker.open([]);
    assert.equal(app.loads(), 2);
    assert.equal(app.opened.length, 1);
});
