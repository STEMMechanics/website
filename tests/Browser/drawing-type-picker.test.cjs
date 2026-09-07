const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
    const requests = [], urls = [], alerts = [];
    const window = { location: { href: 'https://example.test/admin/timesheet?tab=drawings&purpose=time&page=3' }, history: { state: {}, pushState: (_, __, url) => urls.push(url) }, SM: { banner: (...args) => alerts.push(args) } };
    const context = { window, AbortController, URL, Intl, fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })), DOMParser: class { parseFromString(text) { return { querySelector: () => ({ childNodes: [text] }) }; } } };
    vm.runInNewContext(fs.readFileSync('resources/js/drawing-type-picker.js', 'utf8'), context);
    const picker = window.SM.drawingTypePicker('time', { time: { outstanding: 30000, available: 10000 }, contribution: { outstanding: 700000, available: 20000 } });
    let content = 'old';
    picker.$refs = { history: { replaceChildren: value => content = value } };
    return { picker, requests, urls, alerts, content: () => content };
}
test('switch updates state immediately and replaces only history after loading', async () => {
    const { picker, requests, urls, content } = setup();
    picker.purpose = 'contribution';
    const task = picker.changeType();
    assert.equal(picker.loading, true);
    assert.equal(picker.totals[picker.purpose].outstanding, 700000);
    assert.equal(content(), 'old');
    assert.equal(requests[0].url.searchParams.has('page'), false);
    requests[0].resolve({ ok: true, text: async () => 'contribution history' });
    await task;
    assert.equal(content(), 'contribution history');
    assert.equal(picker.loading, false);
    assert.match(urls[0], /purpose=contribution/);
});
test('rapid switching discards stale history and failure restores displayed type', async () => {
    const { picker, requests, urls, content, alerts } = setup();
    picker.purpose = 'contribution';
    const first = picker.changeType();
    picker.purpose = 'time';
    const second = picker.changeType();
    assert.equal(requests[0].options.signal.aborted, true);
    requests[0].resolve({ ok: true, text: async () => 'stale' });
    await first;
    assert.equal(content(), 'old');
    requests[1].resolve({ ok: false });
    await second;
    assert.equal(picker.purpose, 'time');
    assert.equal(picker.loading, false);
    assert.equal(alerts.length, 1);
    assert.equal(urls.length, 0);
});
