const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function load(fetch) {
    const events = [];
    const window = {dispatchEvent: e => events.push(e), location: {assign() { throw new Error('Unexpected navigation'); }}};
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-booking.js', 'utf8'), {window, fetch, Intl, CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}}});
    return {SM: window.SM, events};
}

test('participants choose independent workshops and totals follow their selections', () => {
    const {SM} = load();
    const review = SM.workshopBookingReview({surname:'Example', participants:[
        {firstname:'Young', workshops:['junior']}, {firstname:'Older', workshops:['senior']},
    ], prices:{junior:{price:12, early_price:null, early_places:0}, senior:{price:20, early_price:15, early_places:1}}});
    assert.equal(review.quantity('junior'), 1);
    assert.equal(review.quantity('senior'), 1);
    assert.equal(review.total, 27);
    review.participants[0].workshops.push('senior');
    assert.equal(review.total, 47);
    review.participants.splice(1, 1);
    assert.equal(review.total, 27);
    review.addParticipant();
    assert.equal(review.participants[1].workshops.length, 0);
});

test('adding and removing workshops updates selection, timer and cart without navigating', async () => {
    let received;
    const {SM, events} = load(async (url, options) => {
        received = JSON.parse(options.body);
        return {ok:true, json:async () => ({selected:['anchor','other'], expires_at:'deadline', bookings:[{count:3}]})};
    });
    const suggestions = SM.workshopSuggestions({url:'/cart', csrf:'token', bookingId:'anchor', selected:['anchor']});
    await suggestions.change('other');
    assert.equal(received.action, 'add');
    assert.equal(suggestions.selected.includes('other'), true);
    assert.equal(events[0].detail.booking_id, 'anchor');
    assert.equal(events[1].type, 'workshop-cart-updated');
    await suggestions.change('other');
    assert.equal(received.action, 'remove');
    assert.equal(suggestions.busy, false);
});

test('a capacity error keeps the selection unchanged and displays the server message', async () => {
    const {SM, events} = load(async () => ({ok:false, json:async () => ({errors:{workshop_id:['No places remain.']}})}));
    const suggestions = SM.workshopSuggestions({selected:['anchor']});
    await suggestions.change('full');
    assert.equal(suggestions.selected.length, 1);
    assert.equal(suggestions.error, 'No places remain.');
    assert.equal(suggestions.busy, false);
    assert.equal(events.length, 0);
});
