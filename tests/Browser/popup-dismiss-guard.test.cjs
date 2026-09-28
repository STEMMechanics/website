const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup({ nativeDialog = false, backdropOverlay = false, selfBackdrop = false } = {}) {
    const listeners = new Map();
    const timers = new Map();
    let nextTimer = 1;
    const text = { nodeType: 3 };
    const outside = { nodeType: 1 };
    const backdrop = { nodeType: 1, className: 'absolute inset-0', attributes: [{ name: 'x-on:click' }] };
    const popup = {
        tagName: nativeDialog ? 'DIALOG' : 'DIV',
        open: true,
        attributes: nativeDialog ? [] : backdropOverlay ? [{ name: 'x-show' }] : selfBackdrop ? [{ name: 'x-show' }, { name: 'x-on:click.self' }] : [{ name: 'x-on:click.outside' }],
        className: backdropOverlay || selfBackdrop ? 'fixed inset-0' : '',
        hasAttribute(name) { return (name === 'data-list-dialog' && nativeDialog) || (name === 'x-show' && (backdropOverlay || selfBackdrop)); },
        contains(node) { return node === text || (backdropOverlay && node === backdrop) || node === popup; },
        getBoundingClientRect() { return { left: 10, top: 10, right: 300, bottom: 300 }; },
    };
    const document = {
        addEventListener(name, callback) { listeners.set(name, callback); },
        removeEventListener(name) { listeners.delete(name); },
    };
    const window = {
        setTimeout(callback) { const id = nextTimer++; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    const context = { document, window };
    const source = fs.readFileSync('public/popup-dismiss-guard.js', 'utf8');
    vm.createContext(context);
    vm.runInContext(source, context);
    const cleanup = () => [...listeners.keys()].forEach((name) => document.removeEventListener(name, true));
    const path = (target) => target === text ? [text, popup, document]
        : target === backdrop ? [backdrop, popup, document]
            : target === popup ? [popup, document] : [target, document];
    const pointer = (target, x, y) => ({
        button: 0,
        pointerId: 1,
        pointerType: 'mouse',
        clientX: x,
        clientY: y,
        target,
        composedPath: () => path(target),
    });
    const mouse = (target, x, y) => ({
        button: 0,
        clientX: x,
        clientY: y,
        target,
        composedPath: () => path(target),
    });
    const click = (target, x, y, detail = 1) => ({
        detail,
        clientX: x,
        clientY: y,
        target,
        prevented: false,
        stopped: false,
        preventDefault() { this.prevented = true; },
        stopImmediatePropagation() { this.stopped = true; },
    });

    return { document, outside, backdrop, popup, text, listeners, pointer, mouse, click, cleanup };
}

test('a press begun inside an Alpine popup and released outside does not trigger its outside-click close', () => {
    const state = setup();
    state.listeners.get('pointerdown')(state.pointer(state.text, 40, 40));
    state.listeners.get('pointerup')(state.pointer(state.outside, 360, 40));

    const click = state.click(state.outside, 360, 40);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});

test('a press begun inside a native dialog and released on its backdrop does not dismiss it', () => {
    const state = setup({ nativeDialog: true });
    state.listeners.get('pointerdown')(state.pointer(state.text, 40, 40));
    state.listeners.get('pointerup')(state.pointer(state.popup, 360, 40));

    const click = state.click(state.popup, 360, 40);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});

test('a text-selection drag still stays open when pointer events are cancelled and the zero-detail mouse click lands outside', () => {
    const state = setup({ nativeDialog: true });
    state.listeners.get('pointerdown')(state.pointer(state.text, 40, 40));
    state.listeners.get('mousedown')(state.mouse(state.text, 40, 40));
    state.listeners.get('pointercancel')();
    state.listeners.get('mouseup')(state.mouse(state.popup, 360, 40));

    const click = state.click(state.popup, 360, 40, 0);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});

test('a press begun inside a full-screen popup and released on its backdrop does not close it', () => {
    const state = setup({ backdropOverlay: true });
    state.listeners.get('pointerdown')(state.pointer(state.text, 40, 40));
    state.listeners.get('pointerup')(state.pointer(state.backdrop, 360, 40));

    const click = state.click(state.backdrop, 360, 40);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});

test('a press begun inside a click-self overlay and released outside does not close it', () => {
    const state = setup({ selfBackdrop: true });
    state.listeners.get('pointerdown')(state.pointer(state.text, 40, 40));
    state.listeners.get('pointerup')(state.pointer(state.popup, 360, 40));

    const click = state.click(state.popup, 360, 40);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});

test('ordinary outside clicks remain unaffected, but inside-to-outside presses are suppressed', () => {
    const outsideClickState = setup();
    outsideClickState.listeners.get('pointerdown')(outsideClickState.pointer(outsideClickState.outside, 360, 40));
    outsideClickState.listeners.get('pointerup')(outsideClickState.pointer(outsideClickState.outside, 360, 40));
    const outsideClick = outsideClickState.click(outsideClickState.outside, 360, 40);
    outsideClickState.listeners.get('click')(outsideClick);
    assert.equal(outsideClick.stopped, false);
    outsideClickState.cleanup();

    const insideToOutside = setup();
    insideToOutside.listeners.get('pointerdown')(insideToOutside.pointer(insideToOutside.text, 40, 40));
    insideToOutside.listeners.get('pointerup')(insideToOutside.pointer(insideToOutside.outside, 360, 40));
    const releasedOutside = insideToOutside.click(insideToOutside.outside, 360, 40);
    insideToOutside.listeners.get('click')(releasedOutside);
    assert.equal(releasedOutside.prevented, true);
    assert.equal(releasedOutside.stopped, true);
    insideToOutside.cleanup();
});

test('pointer capture cannot hide an outside release when the event target stays inside the dialog', () => {
    const state = setup();
    state.listeners.get('pointerdown')(state.pointer(state.text, 40, 40));
    state.listeners.get('pointerup')(state.pointer(state.text, 360, 40));

    const click = state.click(state.text, 360, 40);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});

test('mouse-only browsers also suppress a drag-release click outside a popup', () => {
    const state = setup();
    const mouseEvent = (target, x, y) => ({ button: 0, clientX: x, clientY: y, target, composedPath: () => target === state.text ? [state.text, state.popup, state.document] : [target, state.document] });
    state.listeners.get('mousedown')(mouseEvent(state.text, 40, 40));
    state.listeners.get('mouseup')(mouseEvent(state.outside, 360, 40));

    const click = state.click(state.outside, 360, 40);
    state.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    state.cleanup();
});
