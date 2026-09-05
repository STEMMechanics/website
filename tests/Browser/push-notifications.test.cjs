const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const code = fs.readFileSync('resources/js/push-notifications.js', 'utf8');

async function setup({ devices = [], permission = 'default', dismissed = false } = {}) {
    const button = () => ({ addEventListener(_, callback) { this.click = callback; } });
    const enable = button(), disable = button(), later = button(), status = {};
    const prompt = { hidden: true };
    const root = {
        dataset: { user: 'admin', endpoint: '/admin/push-devices', prompt: '1' },
        querySelector: selector => selector === '[data-push-prompt]' ? prompt : status,
        querySelectorAll: selector => ({ '[data-push-enable]': [enable], '[data-push-disable]': [disable], '[data-push-later]': [later] })[selector] || [],
    };
    const stored = new Map([['sm-push-device', 'device']]);
    const session = new Map(dismissed ? [['sm-push-dismissed:admin', '1']] : []);
    const writes = [], events = [];
    const subscription = { toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/example' }) };
    const registration = { pushManager: { getSubscription: async () => subscription } };
    const context = {
        document: { readyState: 'complete', querySelector: selector => selector === '[data-push-root]' ? root : { content: 'csrf' }, querySelectorAll: () => [] },
        window: { isSecureContext: true, PushManager: {}, Notification: {}, matchMedia: () => ({ matches: false }) },
        Notification: { permission, requestPermission: async () => { events.push('permission'); return 'granted'; } },
        navigator: { userAgent: 'Chrome', platform: 'Mac', serviceWorker: { ready: Promise.resolve(registration), register: async () => { events.push('worker'); return registration; }, getRegistration: async () => registration } },
        localStorage: { getItem: key => stored.get(key), setItem: (key, value) => stored.set(key, value) },
        sessionStorage: { getItem: key => session.get(key), setItem: (key, value) => session.set(key, value) },
        fetch: async (_, options) => {
            if (options.method === 'PUT') { const data = JSON.parse(options.body); writes.push(data); devices = [data]; }
            return { ok: true, json: async () => ({ devices, publicKey: 'key' }) };
        },
    };
    await vm.runInNewContext(code, context);
    return { prompt, enable, disable, later, writes, events, session };
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
test('blocked permission suppresses prompt and disables enable button', async () => {
    const app = await setup({ permission: 'denied' }); assert.equal(app.prompt.hidden, true); assert.equal(app.enable.disabled, true);
});
