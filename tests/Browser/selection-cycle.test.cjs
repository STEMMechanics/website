const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup(initial = [], matching = ['a', 'b', 'c']) {
    let change, selected = initial, loadCount = 0;
    const header = { isConnected: true, attrs: {}, addEventListener: (_, callback) => { change = callback; }, setAttribute(key, value) { this.attrs[key] = value; } };
    const context = { URL, api: {} };
    vm.runInNewContext(fs.readFileSync('resources/js/selection-cycle.js', 'utf8').replaceAll('export ', '')+'\napi.bind = bindSelectionCycle; api.key = selectionKey;', context);
    const errors = [];
    const config = { header, pageIds: ['a', 'b'], getSelected: () => selected, setSelected: value => { selected = Array.from(value); }, loadMatching: async () => { loadCount++; return matching; }, key: 'filter', onError: error => errors.push(error) };
    const render = context.api.bind(config);
    return { header, change: () => change(), selected: () => selected, loads: () => loadCount, render, errors, config, bind: context.api.bind, key: context.api.key };
}
test('page → all matching → none preserves selections outside the filter', async () => {
    const app = setup(['outside']);
    await app.change();
    assert.deepEqual(app.selected(), ['outside', 'a', 'b']);
    assert.equal(app.header.indeterminate, true);
    assert.equal(app.header.checked, false);
    await app.change();
    assert.deepEqual(app.selected(), ['outside', 'a', 'b', 'c']);
    assert.equal(app.header.checked, true);
    await app.change();
    assert.deepEqual(app.selected(), ['outside']);
    assert.equal(app.header.checked, false);
    assert.equal(app.header.indeterminate, false);
});
test('a fully selected visible page is mixed until all matching rows are selected', async () => {
    const app = setup(['a', 'b']);
    assert.equal(app.header.indeterminate, true);
    assert.equal(app.header.checked, false);
    await app.change();
    assert.equal(app.header.checked, true);
});
test('all-selection survives pagination and is lost when an individual row is cleared', async () => {
    const app = setup();
    await app.change(); await app.change();
    app.config.pageIds = ['c'];
    const render = app.bind(app.config);
    assert.equal(app.header.checked, true);
    app.config.setSelected(['a', 'b']); render();
    assert.equal(app.header.checked, false);
    assert.equal(app.header.indeterminate, true);
});
test('one-page filters still cycle through mixed, checked and unchecked', async () => {
    const app = setup([], ['a', 'b']);
    await app.change(); assert.equal(app.header.indeterminate, true);
    await app.change(); assert.equal(app.header.checked, true);
    await app.change(); assert.deepEqual(app.selected(), []);
});
test('filter identity ignores pagination and sorting but keeps filters', () => {
    const app = setup();
    assert.equal(app.key('https://example.test/media?type=image&page=2&sort=name'), app.key('https://example.test/media?page=3&type=image'));
    assert.notEqual(app.key('https://example.test/media?type=image'), app.key('https://example.test/media?type=video'));
});
