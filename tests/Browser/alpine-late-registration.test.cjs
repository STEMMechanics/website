const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/script.js'), 'utf8');
const helperStart = source.indexOf('window.SM.whenAlpineReady = (callback) => {');
const helperEnd = source.indexOf('\n};\n\nwindow.SM.whenAlpineReady', helperStart) + 3;
const helperSource = source.slice(helperStart, helperEnd);

function createElement(expression, parent = null) {
    return {
        _x_marker: 1,
        contains(element) {
            return element.parent === this || (element.parent && this.contains(element.parent));
        },
        getAttribute(name) {
            return name === 'x-data' ? expression : null;
        },
        parent,
    };
}

function createHarness(elements = []) {
    const providers = new Map();
    const destroyed = [];
    const initialized = [];
    const listeners = new Map();
    const alpine = {
        data(name, provider) {
            if (arguments.length === 1) return providers.get(name);
            providers.set(name, provider);
            return provider;
        },
        destroyTree(element) {
            destroyed.push(element);
            element._x_marker = undefined;
        },
        initTree(element) {
            initialized.push(element);
            element._x_marker = 1;
        },
    };
    const document = {
        body: {},
        addEventListener(name, callback, options) {
            listeners.set(name, { callback, options });
        },
        querySelectorAll(selector) {
            assert.equal(selector, '[x-data]');
            return elements;
        },
    };
    const context = { Alpine: alpine, document, window: { Alpine: alpine, SM: {} } };
    vm.runInNewContext(helperSource, context);

    return { alpine, context, destroyed, initialized, listeners, providers };
}

test('late Alpine data registrations reinitialize only matching initialized roots', () => {
    const root = createElement('workshopEditor');
    const child = createElement('workshopPricing({ initial: true })', root);
    const unrelated = createElement('shopCart');
    const harness = createHarness([root, child, unrelated]);

    harness.context.window.SM.whenAlpineReady(() => {
        harness.context.Alpine.data('workshopEditor', () => ({}));
        harness.context.Alpine.data('workshopPricing', () => ({}));
    });

    assert.equal(harness.providers.has('workshopEditor'), true);
    assert.equal(harness.providers.has('workshopPricing'), true);
    assert.deepEqual(harness.destroyed, [root]);
    assert.deepEqual(harness.initialized, [root]);
    assert.equal(unrelated._x_marker, 1);
});

test('registrations before Alpine startup wait for alpine:init without reinitializing the page', () => {
    const root = createElement('workshopEditor');
    root._x_marker = undefined;
    const harness = createHarness([root]);
    harness.context.window.Alpine = undefined;
    harness.context.Alpine = undefined;

    let registrationCalls = 0;
    harness.context.window.SM.whenAlpineReady(() => {
        registrationCalls += 1;
        harness.context.Alpine.data('workshopEditor', () => ({}));
    });

    assert.equal(registrationCalls, 0);
    assert.equal(harness.listeners.get('alpine:init').options.once, true);

    harness.context.window.Alpine = harness.alpine;
    harness.context.Alpine = harness.alpine;
    harness.listeners.get('alpine:init').callback();

    assert.equal(registrationCalls, 1);
    assert.equal(harness.providers.has('workshopEditor'), true);
    assert.deepEqual(harness.destroyed, []);
    assert.deepEqual(harness.initialized, []);
});

test('late registration repairs an initialized component on Livewire’s CSP Alpine build', () => {
    const { JSDOM, VirtualConsole } = require('jsdom');
    const virtualConsole = new VirtualConsole();
    virtualConsole.sendTo({
        log() {},
        error() {},
        warn() {},
        info() {},
        dir() {},
    });
    const dom = new JSDOM(
        '<!doctype html><body><form id="workshop" x-data="workshopEditor"><span x-text="editorStep"></span></form></body>',
        { runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole },
    );

    try {
        const { window } = dom;
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync(path.join(__dirname, '../../vendor/livewire/livewire/dist/livewire.csp.js'), 'utf8'));
        window.Alpine.start();

        window.SM = {};
        window.eval(helperSource);
        window.SM.whenAlpineReady(() => {
            window.Alpine.data('workshopEditor', () => ({ editorStep: 'details' }));
        });

        const form = window.document.getElementById('workshop');
        assert.equal(window.Alpine.$data(form).editorStep, 'details');
        assert.equal(form.querySelector('span').textContent, 'details');
    } finally {
        dom.window.close();
    }
});
