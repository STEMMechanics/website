const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function notifications(overrides = {}) {
    const calls = [];
    const context = { window: {}, Swal: { fire(config) { calls.push(config); return Promise.resolve({ isConfirmed: true }); } }, ...overrides };
    const source = fs.readFileSync('public/script.js', 'utf8').split('window.SM = SM;')[0];
    vm.runInNewContext(`${source}\nwindow.SM = SM;`, context);
    return { SM: context.window.SM, calls };
}

test('themed notifications stay below navigation and keep their dismissal controls', async () => {
    const { SM, calls } = notifications();
    SM.alert('Saved', 'Your changes were saved.', 'success');
    await SM.notice('Updated', 'Device updated.', 'success', { toast: true });
    for (const config of calls) {
        assert.equal(config.position, 'top');
        assert.equal(config.toast, true);
        assert.equal(config.showCloseButton, true);
    }
    assert.equal(calls[0].customClass.container, 'sm-feedback-host success');
});

test('confirmation dialogs are centred and still invoke their confirmation callback', async () => {
    const { SM, calls } = notifications();
    let confirmed = false;
    await SM.confirm('Remove device?', 'Remove this device.', 'Remove', result => { confirmed = result; });
    await SM.notice('Attention', 'Please review this.', 'info');
    assert.equal(confirmed, true);
    assert.equal(calls[0].position, 'center');
    assert.equal(calls[1].position, 'center');
});


test('errors persist and ordinary messages are passed as text', () => {
    const { SM, calls } = notifications();
    SM.alert('Blocked', '<b>Check permissions</b>', 'danger');
    assert.equal(calls[0].timer, 0);
    assert.equal(calls[0].text, '<b>Check permissions</b>');
    assert.equal(calls[0].html, undefined);
});

test('inline feedback uses text and can be dismissed without an overlay', () => {
    const { SM, calls } = notifications();
    const fields = {};
    const region = { dataset: {}, hidden: true, querySelector: key => fields[key] ||= {} };
    SM.feedback(region, 'Saved!', '<b>Device updated</b>');
    assert.equal(region.hidden, false);
    assert.equal(fields['[data-feedback-message]'].textContent, '<b>Device updated</b>');
    assert.equal(calls.length, 0);
    fields['[data-feedback-dismiss]'].onclick();
    assert.equal(region.hidden, true);
});


test('banners follow navbar height, pause during interaction and clean up when replaced', () => {
    const listeners = {}, popupListeners = {}, positions = [];
    let bottom = 96, disconnected = false, stopped = 0, resumed = 0, config;
    const nav = { getBoundingClientRect: () => ({ bottom }) };
    const { SM } = notifications({
        window: {
            addEventListener: (name, callback) => { listeners[name] = callback; },
            removeEventListener: (name, callback) => { if (listeners[name] === callback) delete listeners[name]; },
        },
        document: { querySelector: () => nav, activeElement: null },
        ResizeObserver: class {
            constructor(callback) { this.callback = callback; }
            observe(element) { assert.equal(element, nav); }
            disconnect() { disconnected = true; }
        },
        Swal: {
            fire: options => { config = options; },
            getContainer: () => ({ style: { setProperty: (_, value) => positions.push(value) } }),
            stopTimer: () => stopped++, resumeTimer: () => resumed++,
        },
    });
    SM.alert('Saved!', 'Device saved.', 'success');
    config.didOpen({ addEventListener: (name, callback) => { popupListeners[name] = callback; }, contains: () => false });
    assert.equal(positions.at(-1), '108px');
    bottom = -20;
    listeners.scroll();
    assert.equal(positions.at(-1), '12px');
    popupListeners.focusin();
    popupListeners.focusout({ relatedTarget: null });
    assert.equal(stopped, 1);
    assert.equal(resumed, 1);
    config.didDestroy();
    assert.equal(disconnected, true);
    assert.deepEqual(listeners, {});
});

test('success notices auto-hide while errors remain available to read', () => {
    const { SM, calls } = notifications();
    SM.alert('Saved', 'Changes saved.', 'success');
    SM.alert('Failed', 'Please try again.', 'danger');
    SM.banner('Preview', 'Preview message.', 'info', { timer: 1200 });
    assert.equal(calls[0].timer, 7000);
    assert.equal(calls[1].timer, 0);
    assert.equal(calls[2].timer, 1200);
    assert.notEqual(calls[0].showClass.popup, calls[0].hideClass.popup);
});

test('decorative circle motion is faster and stays within the notification', () => {
    const { SM } = notifications();
    const properties = {};
    SM.decorateFeedback({ style: { setProperty: (name, value) => { properties[name] = value; } } });
    for (const dot of ['a', 'b']) {
        const duration = parseFloat(properties[`--feedback-${dot}-duration`]);
        assert.ok(duration >= 3 && duration <= 6);
        assert.ok(Math.abs(parseFloat(properties[`--feedback-${dot}-x`])) <= 16);
        assert.ok(Math.abs(parseFloat(properties[`--feedback-${dot}-y`])) <= 10);
    }
});


test('dynamic page initialisers run only for their listing and cancel previous global listeners', () => {
    const listeners = {};
    const signals = [];
    const { SM } = notifications({ AbortController, document: {
        readyState: 'complete', addEventListener: (name, callback) => { listeners[name] = callback; },
    } });
    SM.onDynamicList('media', signal => signals.push(signal));
    assert.equal(signals.length, 1);
    listeners['sm:list-updated']({ detail: { root: { dataset: { dynamicList: 'products' } } } });
    assert.equal(signals.length, 1);
    listeners['sm:list-updated']({ detail: { root: { dataset: { dynamicList: 'media' } } } });
    assert.equal(signals.length, 2);
    assert.equal(signals[0].aborted, true);
    assert.equal(signals[1].aborted, false);
});


test('feedback can appear inside a native dialog without interpreting error text as HTML', () => {
    const { SM, calls } = notifications();
    const target = { id: 'edit-dialog' };
    SM.banner('Could not save', '<img src=x onerror=alert(1)>', 'danger', { target });
    assert.equal(calls[0].target, target);
    assert.equal(calls[0].text, '<img src=x onerror=alert(1)>');
    assert.equal(calls[0].html, undefined);
});

test('background countdown handles delayed timers, pauses and cleanup without affecting persistent notices', () => {
    let remaining, nextFrame, cancelled;
    const properties = {}, classes = new Set();
    const { SM } = notifications({
        window: {
            requestAnimationFrame: callback => { nextFrame = callback; return 42; },
            cancelAnimationFrame: frame => { cancelled = frame; },
        },
        Swal: { getTimerLeft: () => remaining },
    });
    const popup = {
        classList: { add: name => classes.add(name), remove: name => classes.delete(name) },
        style: { setProperty: (name, value) => { properties[name] = value; }, removeProperty: name => { delete properties[name]; } },
    };
    SM.feedbackCountdown(popup, 0)();
    assert.equal(classes.size, 0);
    const cleanup = SM.feedbackCountdown(popup, 7000);
    assert.equal(typeof nextFrame, 'function');
    assert.equal(properties['--feedback-remaining'], '100%');
    remaining = 3500;
    nextFrame();
    assert.equal(properties['--feedback-remaining'], '50%');
    nextFrame();
    assert.equal(properties['--feedback-remaining'], '50%');
    remaining = 0;
    nextFrame();
    assert.equal(properties['--feedback-remaining'], '0%');
    cleanup();
    assert.equal(cancelled, 42);
    assert.equal(classes.size, 0);
    assert.equal(properties['--feedback-remaining'], undefined);
});
