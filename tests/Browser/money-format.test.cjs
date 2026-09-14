const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('money inputs format on blur without inline handlers, including dynamically added fields', () => {
    let blur;
    class Input {
        constructor(value, money = true) { this.value = value; this.money = money; }
        hasAttribute(name) { return name === 'data-money-format' && this.money; }
    }
    const source = fs.readFileSync('public/script.js', 'utf8').split('// Format money fields without native inline event handlers.')[1];
    vm.runInNewContext(source, {
        HTMLInputElement: Input,
        document: { addEventListener(name, handler, capture) {
            assert.equal(name, 'blur');
            assert.equal(capture, true);
            blur = handler;
        } },
    });
    for (const [raw, expected] of [['12.1', '12.10'], ['0', '0.00'], ['', ''], ['-1', ''], ['bad', '']]) {
        const input = new Input(raw);
        blur({ target: input });
        assert.equal(input.value, expected);
    }
    const text = new Input('12.1', false);
    blur({ target: text });
    assert.equal(text.value, '12.1');
    blur({ target: {} });
});
