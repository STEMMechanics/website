const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function setup(ok) {
    const events = [], notices = [], requests = [];
    const context = { window: { SM: {banner: (...args) => notices.push(args)}, dispatchEvent: e => events.push(e) }, document: {querySelector: () => ({content: 'csrf'})}, CustomEvent: class {constructor(type, data) {this.type=type;this.detail=data.detail;}}, fetch: async (url, options) => {requests.push(JSON.parse(options.body));return {ok, json: async () => ok ? {checked: requests.at(-1).checked} : {message: 'Failed'}};} };
    context.SM = context.window.SM;
    vm.runInNewContext(fs.readFileSync('resources/js/workplan-checkoff.js','utf8'),context);
    return {state:context.window.SM.workplanCheckoff('/checkoff',false),events,notices,requests};
}
test('task checkbox persists completion and adjusts outstanding count', async () => {
    const app=setup(true);app.state.checked=true;await app.state.save(true);
    assert.equal(app.state.checked,true);assert.equal(app.events[0].detail.change,-1);
    app.state.checked=false;await app.state.save(true);assert.equal(app.events[1].detail.change,1);
});
test('workshop readiness does not change task count', async () => {
    const app=setup(true);app.state.checked=true;await app.state.save();assert.equal(app.events.length,0);
});
test('failed update restores checkbox and reports error', async () => {
    const app=setup(false);app.state.checked=true;await app.state.save(true);
    assert.equal(app.state.checked,false);assert.equal(app.state.saving,false);assert.equal(app.events.length,0);assert.equal(app.notices.length,1);
});
