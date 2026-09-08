const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const surface = () => ({ events: {}, addEventListener(name, callback) { this.events[name] = callback; }, removeEventListener(name) { delete this.events[name]; } });
    const header = Object.assign(surface(), { captured: null, setPointerCapture(id) { this.captured = id; }, hasPointerCapture(id) { return this.captured === id; }, releasePointerCapture() { this.captured = null; } });
    const dialog = Object.assign(surface(), { open: true, style: { removeProperty(key) { delete this[key]; } }, querySelector: () => header, getBoundingClientRect: () => ({ left: 200, top: 100, width: 600, height: 500 }) });
    const window = Object.assign(surface(), { innerWidth: 1200, innerHeight: 900 });
    let observed, disconnected = false;
    const context = { window, ResizeObserver: class { constructor(callback) { observed = callback; } observe() {} disconnect() { disconnected = true; } } };
    vm.runInNewContext(fs.readFileSync('resources/js/draggable-dialog.js', 'utf8').replace('export function', 'function'), context);
    const cleanup = context.attachDraggableDialog(dialog);
    const pointer = (x, y, interactive = false) => ({ button: 0, pointerId: 1, clientX: x, clientY: y, target: { closest: () => interactive }, preventDefault() {} });
    return { header, dialog, window, cleanup, pointer, resize: () => observed(), disconnected: () => disconnected };
}
test('drag is clamped to the viewport and remains reachable after resizing', () => {
    const { header, dialog, window, pointer, resize, cleanup, disconnected } = setup();
    header.events.pointerdown(pointer(250, 120));
    header.events.pointermove(pointer(900, 800));
    assert.equal(dialog.style.left, '584px');
    assert.equal(dialog.style.top, '384px');
    header.events.pointerup();
    assert.equal(header.captured, null);
    window.innerWidth = 900;
    resize();
    assert.equal(dialog.style.left, '284px');
    window.innerWidth = 390;
    window.events.resize();
    assert.equal(dialog.style.left, undefined);
    assert.equal(dialog.style.margin, undefined);
    cleanup();
    assert.equal(disconnected(), true);
    assert.equal(Object.keys(header.events).length, 0);
    assert.equal(Object.keys(window.events).length, 0);
});
test('header controls and mobile sheets do not start dragging; closing releases capture', () => {
    const { header, dialog, window, pointer } = setup();
    header.events.pointerdown(pointer(250, 120, true));
    assert.equal(header.captured, null);
    window.innerWidth = 390;
    header.events.pointerdown(pointer(250, 120));
    assert.equal(header.captured, null);
    window.innerWidth = 1200;
    header.events.pointerdown(pointer(250, 120));
    assert.equal(header.captured, 1);
    dialog.events.close();
    assert.equal(header.captured, null);
});
