const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function navbar(bookings = []) {
    let now = Date.parse('2026-09-21T00:00:00Z');
    let tick;
    let cleared = false;
    const notices = [];
    const listeners = new Map();
    const context = {
        window: {
            SM: { banner: (...args) => notices.push(args) },
            addEventListener: (name, callback) => listeners.set(name, callback),
            removeEventListener: name => listeners.delete(name),
        },
        Date: { now: () => now, parse: Date.parse },
        setInterval: callback => { tick = callback; return 1; },
        clearInterval: () => { cleared = true; },
    };
    const source = fs.readFileSync('resources/views/components/navbar.blade.php', 'utf8');
    vm.runInNewContext(source.slice(source.indexOf('    function shopNavbarController(config) {'), source.lastIndexOf('</script>')), context);
    const controller = context.shopNavbarController({workshopBookings: bookings, cartState: {summary: {item_count: 3}}});
    controller.$watch = () => {};
    controller.syncScrollLock = () => {};
    controller.init();
    return { controller, notices, listeners, tick: milliseconds => { now += milliseconds; tick(); }, isCleared: () => cleared };
}
const booking = (id, minutes = 10) => ({ id, count: 2, expires_at: `2026-09-21T00:${String(minutes).padStart(2, '0')}:00Z` });

test('expiring tickets notify once, retain other bookings and leave store items alone', () => {
    const {controller, notices, tick} = navbar([booking('first'), booking('second', 15)]);
    tick(600000);
    assert.equal(notices.length, 1);
    assert.equal(notices[0][0], 'Workshop tickets expired');
    assert.match(notices[0][1], /select your workshops again/);
    assert.equal(notices[0][2], 'warning');
    assert.equal(controller.workshopBookings.length, 1);
    assert.equal(controller.workshopBookings[0].id, 'second');
    assert.equal(controller.cartCount(), 5);
    tick(1000);
    assert.equal(notices.length, 1);
    tick(300000);
    assert.equal(notices.length, 2);
    assert.equal(controller.cartCount(), 3);
    assert.match(controller.workshopExpiryNotice, /expired/);
});

test('bookings added after an empty cart are monitored and fresh reservations clear old notices', () => {
    const {controller, notices, listeners, tick, isCleared} = navbar();
    const update = listeners.get('workshop-cart-updated');
    update({detail: [booking('first')]});
    tick(600000);
    assert.equal(notices.length, 1);
    update({detail: [booking('second', 15)]});
    assert.equal(controller.workshopExpiryNotice, '');
    tick(300000);
    assert.equal(notices.length, 2);
    controller.destroy();
    assert.equal(isCleared(), true);
    assert.equal(listeners.has('workshop-cart-updated'), false);
});

test('extended reservations use the new deadline and removed bookings do not trigger expiry', () => {
    const {controller, notices, listeners, tick} = navbar([booking('first')]);
    const update = listeners.get('workshop-cart-updated');
    tick(300000);
    update({detail: [booking('first', 15)]});
    tick(300000);
    assert.equal(notices.length, 0);
    assert.equal(controller.workshopTicketCount(), 2);
    update({detail: []});
    tick(600000);
    assert.equal(notices.length, 0);
});

test('review selections update only that booking count without claiming additional reservations', () => {
    const {controller, listeners, tick} = navbar([booking('first'), booking('second')]);
    listeners.get('workshop-selection-updated')({detail:{bookingId:'first', count:5}});
    assert.equal(controller.cartCount(), 10);
    assert.equal(controller.workshopBookings[0].count, 2);
    assert.equal(controller.workshopDisplayCount(controller.workshopBookings[0]), 5);
    listeners.get('workshop-selection-updated')({detail:{bookingId:'first', count:0}});
    assert.equal(controller.cartCount(), 5);
    tick(600000);
    assert.equal(controller.cartCount(), 3);
});
