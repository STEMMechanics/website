const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(selected = []) {
    const checkbox = value => ({ value, checked: false, addEventListener(name, fn) { this.change = fn; } });
    const items = ['1', '2', '1', '2'].map(checkbox);
    const headers = [checkbox(''), checkbox('')];
    const button = {}, inputs = { replaceChildren(...children) { this.children = children; } };
    const allocate = { href: 'https://example.test/admin/allocation-overrides/expenses', setAttribute(key, value) { this[key] = value; }, addEventListener(name, fn) { this.click = fn; } };
    const controls = { dataset: {} };
    const form = { querySelector: key => key.startsWith('button') ? button : inputs };
    let stored = JSON.stringify(selected);
    const context = {
        URL,
        SM: { onDynamicList: (name, callback) => callback() },
        sessionStorage: { getItem: () => stored, setItem: (_, value) => { stored = value; } },
        document: {
            querySelectorAll: key => key === '.admin-expense-select-item' ? items : headers,
            getElementById: key => key === 'admin-expense-export-form' ? form : key === 'admin-expense-allocate' ? allocate : controls,
            createElement: () => ({}),
        },
    };
    const source = fs.readFileSync('resources/views/admin/expense/index.blade.php', 'utf8').split('<script>')[1].split('</script>')[0];
    vm.runInNewContext(source, context);
    return { allocate, items, headers, button, inputs, controls, stored: () => JSON.parse(stored) };
}

test('export label counts unique selections across pages and uses singular for one', () => {
    const app = setup(Array.from({ length: 14 }, (_, i) => String(i + 1)));
    assert.equal(app.button.textContent, 'Export 14 items');
    assert.equal(app.inputs.children.length, 14);
    app.headers[0].checked = false;
    app.headers[0].change();
    app.items[0].checked = true;
    app.items[0].change();
    assert.equal(app.button.textContent, 'Export 1 item');
    assert.equal(app.items[2].checked, true);
});

test('the header clears off-page selections and synchronises the mobile checkbox', () => {
    const app = setup(['1', '2', '14']);
    assert.equal(app.headers[0].checked, true);
    app.headers[0].checked = false;
    app.headers[0].change();
    assert.deepEqual(app.stored(), []);
    assert.equal(app.headers[1].checked, false);
    assert.equal(app.headers[1].indeterminate, false);
    assert.equal(app.button.disabled, true);
    assert.equal(app.inputs.children.length, 0);
});


test('bulk allocation links include cross-page selections and cap at 200 records', () => {
    const app = setup(['1', '14']);
    assert.deepEqual(new URL(app.allocate.href).searchParams.getAll('ids[]'), ['1', '14']);
    assert.equal(app.allocate['aria-disabled'], 'false');
    const large = setup(Array.from({ length: 201 }, (_, i) => String(i + 1)));
    let prevented = false;
    large.allocate.click({ preventDefault() { prevented = true; }, stopImmediatePropagation() {} });
    assert.equal(prevented, true);
    assert.equal(large.allocate['aria-disabled'], 'true');
});
