const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const code = fs.readFileSync('resources/js/device-preferences.js', 'utf8');

async function setup({ devices = [], permission = 'default', dismissed = false, confirmed = true, deleteFails = false, loadFails = false, testFails = false, requestedPermission = 'granted', userAgent = 'Chrome' } = {}) {
    const button = () => ({ addEventListener(_, callback) { this.click = callback; } });
    const enable = button(), disable = button(), later = button(), status = {};
    const settingsEnable = button(), empty = {}, count = {};
    const makeRow = () => {
        const fields = { '[data-push-device-name]': {}, '[data-push-current]': {}, '[data-push-device-status]': {}, '[data-push-test]': button(), '[data-push-remove]': button() };
        return { querySelector: selector => fields[selector] };
    };
    const template = { content: { firstElementChild: { cloneNode: makeRow } } };
    const list = { rows: [], closest: () => settings, replaceChildren() { this.rows = []; }, append(row) { this.rows.push(row); } };
    const settings = {
        querySelector: selector => ({ '[data-push-device-template]': template, '[data-push-empty]': empty, '[data-push-status]': status, '[data-push-count]': count })[selector],
        querySelectorAll: selector => ({ '[data-push-enable]': [settingsEnable] })[selector] || [],
    };
    const prompt = { hidden: true };
    const root = {
        dataset: { user: 'admin', endpoint: '/admin/push-devices', prompt: '1' },
        querySelector: selector => selector === '[data-push-prompt]' ? prompt : status,
        querySelectorAll: selector => ({ '[data-push-enable]': [enable], '[data-push-disable]': [disable], '[data-push-later]': [later] })[selector] || [],
    };
    const stored = new Map([['sm-push-device', 'device']]);
    const session = new Map(dismissed ? [['sm-push-dismissed:admin', '1']] : []);
    const writes = [], events = [], alerts = [];
    const subscription = { toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/example' }) };
    const registration = { pushManager: { getSubscription: async () => subscription } };
    const context = {
        document: { readyState: 'complete', querySelector: selector => selector === '[data-push-root]' ? root : { content: 'csrf' }, querySelectorAll: selector => selector === '[data-push-settings]' ? [settings] : selector === '[data-push-devices]' ? [list] : [] },
        window: { SM: { alert: (...args) => alerts.push(args) }, confirm: () => confirmed, isSecureContext: true, PushManager: {}, Notification: {}, matchMedia: () => ({ matches: false }) },
        Notification: { permission, requestPermission: async () => { events.push('permission'); context.Notification.permission = requestedPermission; return requestedPermission; } },
        navigator: { userAgent, platform: 'Mac', serviceWorker: { ready: Promise.resolve(registration), register: async path => { assert.equal(path, '/site-worker.js'); events.push('worker'); return registration; }, getRegistration: async path => { assert.equal(path, '/site-worker.js'); return registration; } } },
        localStorage: { getItem: key => stored.get(key), setItem: (key, value) => stored.set(key, value) },
        sessionStorage: { getItem: key => session.get(key), setItem: (key, value) => session.set(key, value) },
        fetch: async (url, options) => {
            if (options.method === 'POST') { assert.equal(url, '/admin/push-devices/test'); writes.push(JSON.parse(options.body)); return { ok: !testFails, json: async () => ({ success: !testFails, message: 'Could not send test.' }) }; }
            if (loadFails && options.method === 'GET') throw new Error('Could not load settings.');
            if (options.method === 'DELETE') { const data = JSON.parse(options.body); writes.push(data); if (deleteFails) return { ok: false, json: async () => ({ message: 'Removal failed' }) }; devices = devices.filter(device => device.device_id !== data.device_id); }
            if (options.method === 'PUT') { const data = JSON.parse(options.body); writes.push(data); devices = [data]; }
            return { ok: true, json: async () => ({ devices, publicKey: 'key' }) };
        },
    };
    await vm.runInNewContext(code, context);
    return { prompt, enable, disable, settingsEnable, list, empty, status, later, writes, events, session, alerts };
}

test('undecided device sees prompt without native permission request', async () => {
    const app = await setup(); assert.equal(app.prompt.hidden, false); assert.deepEqual(app.events, []);
});
test('saved off choice suppresses prompt', async () => {
    const app = await setup({ devices: [{ device_id: 'device', enabled: false }] }); assert.equal(app.prompt.hidden, true);
});
test('Not now only dismisses for session', async () => {
    const app = await setup(); app.later.click(); assert.equal(app.prompt.hidden, true); assert.equal(app.writes.length, 0); assert.equal(app.session.get('sm-push-dismissed:admin'), '1');
});
test('permanent dismissal saves off preference', async () => {
    const app = await setup(); await app.disable.click(); assert.equal(app.writes[0].enabled, false); assert.equal(app.prompt.hidden, true);
});
test('enable requests native permission before registering worker and saves subscription', async () => {
    const app = await setup(); await app.enable.click(); assert.deepEqual(app.events, ['permission', 'worker']); assert.equal(app.writes[0].enabled, true); assert.ok(app.writes[0].subscription); assert.equal(app.prompt.hidden, true);
});
test('denied permission suppresses the unsolicited prompt but allows a user-initiated request', async () => {
    const app = await setup({ permission: 'denied' }); assert.equal(app.prompt.hidden, true); assert.equal(app.enable.disabled, false); await app.enable.click(); assert.equal(app.events[0], 'permission');
});

test('a saved off device allows requesting permission and removal', async () => {
    const app = await setup({ permission: 'denied', devices: [{ device_id: 'device', enabled: false }] });
    assert.equal(app.settingsEnable.disabled, false);
    assert.equal(app.settingsEnable.textContent, 'Enable on this device');
    assert.match(app.list.rows[0].querySelector('[data-push-device-status]').textContent, /^Blocked by browser/);
    assert.equal(app.list.rows[0].querySelector('[data-push-remove]').disabled, false);
});

test('a saved off device with browser permission can be enabled', async () => {
    const app = await setup({ permission: 'granted', devices: [{ device_id: 'device', enabled: false }] });
    assert.equal(app.settingsEnable.disabled, false);
    assert.equal(app.settingsEnable.textContent, 'Enable on this device');
    assert.equal(app.list.rows[0].querySelector('[data-push-device-status]').textContent, 'Notifications off');
});

test('an undecided device sees an empty state and can enable', async () => {
    const app = await setup();
    assert.equal(app.settingsEnable.disabled, false);
    assert.equal(app.empty.hidden, false);
    assert.equal(app.list.hidden, true);
    assert.equal(app.disable.disabled, false);
});

test('enabling adds a row and removing it restores the empty state and enable button', async () => {
    const app = await setup();
    await app.settingsEnable.click();
    assert.equal(app.settingsEnable.disabled, true);
    assert.equal(app.list.rows.length, 1);
    assert.equal(app.list.rows[0].querySelector('[data-push-current]').hidden, false);
    await app.list.rows[0].querySelector('[data-push-remove]').click();
    assert.equal(app.list.rows.length, 0);
    assert.equal(app.settingsEnable.disabled, false);
    assert.equal(app.empty.hidden, false);
    assert.equal(app.session.get('sm-push-dismissed:admin'), '1');
});

test('removing another device leaves this device enabled', async () => {
    const app = await setup({ permission: 'granted', devices: [
        { device_id: 'device', name: 'Current browser', enabled: true },
        { device_id: 'other', name: 'Phone', enabled: false },
    ] });
    assert.equal(app.list.rows[1].querySelector('[data-push-device-name]').textContent, 'Phone');
    await app.list.rows[1].querySelector('[data-push-remove]').click();
    assert.equal(app.list.rows.length, 1);
    assert.equal(app.settingsEnable.disabled, true);
    assert.equal(app.writes[0].device_id, 'other');
});

test('cancelling removal leaves the saved device untouched', async () => {
    const app = await setup({ confirmed: false, devices: [{ device_id: 'other', enabled: false }] });
    await app.list.rows[0].querySelector('[data-push-remove]').click();
    assert.equal(app.writes.length, 0);
    assert.equal(app.list.rows.length, 1);
});

test('failed removal keeps the row and restores its remove button', async () => {
    const app = await setup({ deleteFails: true, devices: [{ device_id: 'other', enabled: false }] });
    await app.list.rows[0].querySelector('[data-push-remove]').click();
    assert.equal(app.list.rows.length, 1);
    assert.equal(app.list.rows[0].querySelector('[data-push-remove]').disabled, false);
    assert.deepEqual(app.alerts, [['Notification error', 'Removal failed', 'danger']]);
    assert.notEqual(app.status.textContent, 'Removal failed');
});

test('permission refusal shows a generic themed alert without registering a device', async () => {
    const app = await setup({ permission: 'denied', requestedPermission: 'denied', userAgent: 'Version/26 Safari/605' });
    await app.settingsEnable.click();
    assert.deepEqual(app.events, ['permission']);
    assert.equal(app.writes.length, 0);
    assert.equal(app.list.rows.length, 0);
    assert.equal(app.settingsEnable.disabled, false);
    assert.deepEqual(app.alerts, [['Notification error', 'Notifications were blocked by your browser. Check your browser permissions and try again.', 'danger']]);
    assert.equal(app.status.textContent, 'Notifications are off for this device.');
});

test('loading errors use the themed alert and clear the loading paragraph', async () => {
    const app = await setup({ loadFails: true });
    assert.deepEqual(app.alerts, [['Notification error', 'Could not load settings.', 'danger']]);
    assert.equal(app.status.textContent, '');
});

test('test button sends to the selected device and uses a themed success alert', async () => {
    const app = await setup({ devices: [{ device_id: 'other', enabled: true, can_enable: true }] });
    assert.equal(app.list.rows[0].querySelector('[data-push-test]').disabled, false);
    await app.list.rows[0].querySelector('[data-push-test]').click();
    assert.equal(app.writes[0].device_id, 'other');
    assert.equal(app.alerts[0][0], 'Test notification sent');
    assert.equal(app.alerts[0][2], 'success');
});

test('test is disabled for an inactive device', async () => {
    const app = await setup({ devices: [{ device_id: 'other', enabled: false, can_enable: true }] });
    assert.equal(app.list.rows[0].querySelector('[data-push-test]').disabled, true);
});

test('test delivery failure uses a themed error and allows retry', async () => {
    const app = await setup({ testFails: true, devices: [{ device_id: 'other', enabled: true, can_enable: true }] });
    await app.list.rows[0].querySelector('[data-push-test]').click();
    assert.equal(app.alerts[0][2], 'danger');
    assert.equal(app.list.rows[0].querySelector('[data-push-test]').disabled, false);
});
