const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup(ok) {
    const handlers = {}, notices = [], button = { disabled: false }, panel = { innerHTML: 'original' };
    const form = { dataset: {}, action: '/allocation', parentElement: panel, matches: () => true, querySelector: () => button, setAttribute() {}, removeAttribute() {} };
    vm.runInNewContext(fs.readFileSync('resources/js/invoice-allocation-editor.js', 'utf8'), {
        document: { addEventListener: (name, handler) => handlers[name] = handler },
        FormData: class {}, SM: { banner: (...args) => notices.push(args) },
        fetch: async () => ({ ok, headers: { get: () => 'application/json' }, json: async () => ok ? { html: '<form>updated allocation</form>', message: 'Saved' } : { errors: { targets: ['Check amounts'] } } }),
    });
    return { handlers, form, panel, button, notices };
}
test('inline save updates only allocation panel and restores submit state', async () => {
    const state = setup(true);
    let prevented = false;
    await state.handlers.submit({ target: state.form, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.panel.innerHTML, '<form>updated allocation</form>');
    assert.equal(state.button.disabled, false);
    assert.equal(state.notices[0][2], 'success');
});
test('failed allocation save keeps entered values and shows themed error', async () => {
    const state = setup(false);
    await state.handlers.submit({ target: state.form, preventDefault() {} });
    assert.equal(state.panel.innerHTML, 'original');
    assert.equal(state.button.disabled, false);
    assert.equal(state.notices[0][1], 'Check amounts');
});
