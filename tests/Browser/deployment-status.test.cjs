const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup(check = async () => ({ ok: true, status: 200 })) {
    const context = { window: {}, Date, AbortSignal };
    vm.runInNewContext(fs.readFileSync('resources/js/deployment-status.js', 'utf8').replace('export function', 'function'), context);
    const element = { textContent: '', dataset: {} }, notices = [];
    const monitor = context.window.SM.deploymentStatus({ element, notify: (...args) => notices.push(args), check });
    return { ...monitor, element, notices };
}
test('progress then verified completion notifies once', async () => {
    const app = setup();
    await app.update('Deployment status: Building website assets');
    assert.equal(app.element.textContent, 'Building website assets');
    await app.update('Deployment finished at main@123');
    await app.update('Deployment finished at main@123');
    assert.equal(app.notices.length, 1);
    assert.equal(app.notices[0][2], 'success');
});
test('maintenance is distinct from 500 and recovery is announced', async () => {
    const app = setup();
    app.error({status: 503});
    assert.match(app.element.textContent, /Maintenance mode/);
    assert.equal(app.notices.length, 0);
    app.error({status: 500}); app.error({status: 500});
    assert.equal(app.notices.length, 1);
    await app.update('Deployment finished at main@123');
    assert.equal(app.notices[1][2], 'success');
});
test('nonzero exit and failing home page never report success', async () => {
    const app = setup(async () => ({ ok: false, status: 500 }));
    await app.update('Deployment process exited with code 1');
    assert.match(app.element.textContent, /failed/);
    await app.update('Deployment finished at main@123');
    assert.match(app.element.textContent, /HTTP 500/);
    assert.ok(app.notices.every(n => n[2] === 'danger'));
});
test('old completion is displayed without a new completion notification', async () => {
    const app = setup();
    await app.update('Deployment finished at main@old');
    assert.equal(app.notices.length, 0);
    assert.match(app.element.textContent, /successfully/);
});
