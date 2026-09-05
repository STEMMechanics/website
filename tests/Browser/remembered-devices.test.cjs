const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

const view = fs.readFileSync('resources/views/account.blade.php', 'utf8');
const code = view.slice(view.indexOf('const initRememberedDeviceActions ='), view.indexOf("document.addEventListener('DOMContentLoaded', () => {"));

function setup(fetchResponse, { confirmed = true } = {}) {
    class Element {
        classList = { toggle: (name, value) => { this[name] = value; } };
    }
    class Button extends Element {
        dataset = { deviceId: 'device-id' };
        disabled = false;
        addEventListener(_, callback) { this.click = callback; }
        closest() { return row; }
    }
    class Input extends Element { checked = true; }
    const row = new Element();
    row.remove = () => { row.removed = true; };
    const button = new Button();
    const checkbox = new Input();
    const list = new Element();
    list.dataset = { currentTokenId: 'device-id' };
    list.querySelectorAll = () => row.removed ? [] : [row];
    const empty = new Element(), count = new Element();
    const alerts = [], requests = [];
    let pending, timeoutCallback;
    const SM = {
        confirm: (_, __, ___, callback) => { pending = callback(confirmed); },
        alert: (...args) => alerts.push(args),
    };
    vm.runInNewContext(`${code}\ninitRememberedDeviceActions();`, {
        HTMLButtonElement: Button, HTMLElement: Element, HTMLInputElement: Input,
        AbortController,
        setTimeout: callback => { timeoutCallback = callback; return 1; },
        clearTimeout: () => { timeoutCallback = null; },
        window: { SM }, SM,
        document: {
            getElementById: id => ({
                'remembered-devices-list': list,
                'remembered-devices-empty': empty,
                'keep_signed_in_device': checkbox,
            })[id],
            querySelector: selector => selector === 'meta[name="csrf-token"]' ? { content: 'csrf-token' } : count,
            querySelectorAll: selector => selector === '[data-device-remove]' ? [button] : [],
        },
        // Deliberately omit Axios: removal must work even if the main bundle fails.
        fetch: (...args) => { requests.push(args); return fetchResponse(...args); },
    });
    return { button, row, checkbox, list, empty, count, alerts, requests,
        remove: () => { button.click(); return pending; },
        expire: () => timeoutCallback(),
    };
}

test('removal works without Axios and updates the current device, count and empty state', async () => {
    const app = setup(async () => ({ ok: true, json: async () => ({ success: true }) }));
    await app.remove();
    assert.equal(app.row.removed, true);
    assert.equal(app.checkbox.checked, false);
    assert.equal(app.list.dataset.currentTokenId, '');
    assert.equal(app.count.textContent, '0 saved');
    assert.equal(app.list.hidden, true);
    assert.equal(app.empty.hidden, false);
    assert.equal(app.requests[0][0], '/account/devices/device-id');
    assert.equal(app.requests[0][1].method, 'DELETE');
    assert.equal(app.requests[0][1].headers['X-CSRF-TOKEN'], 'csrf-token');
});

for (const [name, response] of [
    ['HTTP failure', async () => ({ ok: false })],
    ['unsuccessful response', async () => ({ ok: true, json: async () => ({ success: false }) })],
    ['synchronous request failure', () => { throw new Error('Unavailable'); }],
]) {
    test(`${name} leaves the device visible and allows retry`, async () => {
        const app = setup(response);
        await app.remove();
        assert.equal(app.row.removed, undefined);
        assert.equal(app.button.disabled, false);
        assert.equal(app.count.textContent, '1 saved');
        assert.equal(app.alerts[0][0], 'Remove failed');
    });
}

test('a stalled request times out and restores the remove button', async () => {
    const app = setup((_, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => reject(new Error('Timeout')));
    }));
    const pending = app.remove();
    assert.equal(app.button.disabled, true);
    app.expire();
    await pending;
    assert.equal(app.button.disabled, false);
    assert.equal(app.row.removed, undefined);
    assert.equal(app.alerts[0][0], 'Remove failed');
});

test('cancelling confirmation does not send a request', async () => {
    const app = setup(() => {}, { confirmed: false });
    await app.remove();
    assert.equal(app.requests.length, 0);
    assert.equal(app.button.disabled, false);
});
