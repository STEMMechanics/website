const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const listeners = new Map();
    const dialog = {
        open: true,
        closed: false,
        matches(selector) { return selector === '[data-list-dialog]'; },
        closest(selector) { return selector === '[data-list-dialog]' ? this : null; },
        getBoundingClientRect() { return { left: 100, top: 100, right: 400, bottom: 400 }; },
        close() { this.closed = true; },
    };
    const child = {
        matches() { return false; },
        closest(selector) { return selector === '[data-list-dialog]' ? dialog : null; },
    };
    const document = { addEventListener(name, callback) { listeners.set(name, callback); } };
    const context = { document, window: { innerWidth: 1200 } };
    const source = fs.readFileSync('resources/js/list-controls.js', 'utf8').replace('export function', 'function');
    vm.createContext(context);
    vm.runInContext(source, context);

    const mouseDown = (target, x, y) => listeners.get('mousedown')({ target, clientX: x, clientY: y });
    const click = (target, x, y) => listeners.get('click')({
        target,
        clientX: x,
        clientY: y,
        preventDefault() {},
    });

    return { dialog, child, mouseDown, click };
}

test('backdrop dismissal uses where the mouse press began, regardless of the later click coordinates', () => {
    const insidePress = setup();
    insidePress.mouseDown(insidePress.child, 150, 150);
    insidePress.click(insidePress.dialog, 450, 150);
    assert.equal(insidePress.dialog.closed, false);

    const outsidePress = setup();
    outsidePress.mouseDown(outsidePress.dialog, 450, 150);
    outsidePress.click(outsidePress.dialog, 150, 150);
    assert.equal(outsidePress.dialog.closed, true);
});

test('keyboard or synthetic backdrop clicks without a mouse press do not dismiss the dialog', () => {
    const state = setup();
    state.click(state.dialog, 450, 150);
    assert.equal(state.dialog.closed, false);
});
