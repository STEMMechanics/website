const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('checkout countdown keeps the original deadline across pages and stops at expiry', () => {
    let now = Date.parse('2026-09-09T00:00:00Z');
    let tick;
    let cleared = 0;
    const context = {
        window: {}, Date: {parse: Date.parse, now: () => now},
        setInterval: callback => { tick = callback; return 1; },
        clearInterval: () => { cleared++; },
    };
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-hold-countdown.js', 'utf8'), context);
    const create = () => context.window.SM.workshopHoldCountdown('2026-09-09T00:10:00Z');
    const equipment = create();
    equipment.init();
    assert.equal(equipment.timeRemaining, '10:00');
    now += 185000;
    tick();
    assert.equal(equipment.timeRemaining, '06:55');
    equipment.destroy();
    assert.equal(cleared, 1);
    const delivery = create();
    delivery.init();
    assert.equal(delivery.timeRemaining, '06:55');
    now += 600000;
    tick();
    assert.equal(delivery.timeRemaining, '00:00');
    assert.equal(delivery.remainingSeconds, 0);
    assert.equal(cleared, 2);
});
